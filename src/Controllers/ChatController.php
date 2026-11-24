<?php

namespace VanillIA\Controllers;

use PDO;
use Throwable;
use VanillIA\Core\Database;
use VanillIA\Core\I18n;
use VanillIA\Core\Migrator;
use VanillIA\Core\Security;
use VanillIA\Models\User;
use VanillIA\Providers\AnthropicProvider;
use VanillIA\Providers\LLMProviderInterface;
use VanillIA\Providers\OpenAIProvider;
use VanillIA\Providers\VertexGeminiProvider;
use VanillIA\Services\FileProcessorService;
use VanillIA\Services\MemoryService;

class ChatController
{
    private static bool $schemaChecked = false;

    private function ensurePinnedColumn(PDO $db): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;
        Migrator::ensureSchema($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $db = Database::getInstance();
        $this->ensurePinnedColumn($db);

        $user = User::findById((int)$_SESSION['user_id']);
        if (!$user || (int)$user['is_active'] !== 1) {
            session_destroy();
            header('Location: /login');
            exit;
        }

        $lang = I18n::init($user['preferred_lang'] ?? null);
        $theme = I18n::getTheme($user['preferred_theme'] ?? null);

        if ($user['role'] === 'admin') {
            $models = $db->query("SELECT id, provider, model_identifier, display_name, min_role, is_default, cost_input_per_1m, cost_output_per_1m FROM ai_models WHERE is_enabled = 1 ORDER BY is_default DESC, id ASC")->fetchAll();
        } else {
            $models = $db->query("SELECT id, provider, model_identifier, display_name, min_role, is_default, cost_input_per_1m, cost_output_per_1m FROM ai_models WHERE is_enabled = 1 AND min_role = 'user' ORDER BY is_default DESC, id ASC")->fetchAll();
        }

        $stmtConv = $db->prepare("SELECT id, title, model_id, system_prompt, is_pinned, updated_at FROM conversations WHERE user_id = ? AND is_archived = 0 ORDER BY is_pinned DESC, updated_at DESC LIMIT 50");
        $stmtConv->execute([$user['id']]);
        $conversations = $stmtConv->fetchAll();

        $csrfToken = Security::generateCsrf();
        require __DIR__ . '/../Views/chat.php';
    }

    public function myUsage(): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non connecté']);
            return;
        }

        $user = User::findById((int)$_SESSION['user_id']);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable']);
            return;
        }

        $db = Database::getInstance();

        $lastRefillTs = !empty($user['last_refill_at']) ? strtotime($user['last_refill_at']) : time();
        $nextRefillTs = $lastRefillTs + (30 * 86400);
        $daysRemaining = max(0, (int)ceil(($nextRefillTs - time()) / 86400));

        // Consommation par jour sur les 30 derniers jours
        $stmtDaily = $db->prepare("
            SELECT 
                DATE(created_at) as usage_date,
                COUNT(*) as requests_count,
                COALESCE(SUM(tokens_input), 0) as tokens_in,
                COALESCE(SUM(tokens_output), 0) as tokens_out,
                COALESCE(SUM(tokens_input + tokens_output), 0) as tokens_total,
                ROUND(COALESCE(SUM(client_credits_billed), 0) / 1000000, 4) as cost_eur
            FROM usage_logs
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY usage_date DESC
        ");
        $stmtDaily->execute([$user['id']]);
        $daily = $stmtDaily->fetchAll();

        // Répartition par modèle sur les 30 derniers jours
        $stmtModels = $db->prepare("
            SELECT 
                COALESCE(m.display_name, 'Modèle supprimé') as model_name,
                COUNT(*) as requests_count,
                COALESCE(SUM(l.tokens_input + l.tokens_output), 0) as tokens_total,
                ROUND(COALESCE(SUM(l.client_credits_billed), 0) / 1000000, 4) as cost_eur
            FROM usage_logs l
            LEFT JOIN ai_models m ON l.model_id = m.id
            WHERE l.user_id = ? AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY l.model_id, m.display_name
            ORDER BY cost_eur DESC
        ");
        $stmtModels->execute([$user['id']]);
        $byModel = $stmtModels->fetchAll();

        echo json_encode([
            'user' => [
                'display_name'          => $user['display_name'],
                'credits_balance'       => (int)$user['credits_balance'],
                'credits_monthly_limit' => (int)$user['credits_monthly_limit'],
                'balance_eur'           => round((int)$user['credits_balance'] / 1000000, 4),
                'limit_eur'             => round((int)$user['credits_monthly_limit'] / 1000000, 2),
                'auto_refill'           => (int)$user['auto_refill'] === 1,
                'last_refill_at'        => date('d/m/Y à H:i', $lastRefillTs),
                'next_refill_at'        => (int)$user['auto_refill'] === 1 ? date('d/m/Y à H:i', $nextRefillTs) : null,
                'days_until_reset'      => (int)$user['auto_refill'] === 1 ? $daysRemaining : null,
            ],
            'daily'    => $daily,
            'by_model' => $byModel
        ]);
    }

    public function listConversations(): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non connecté']);
            return;
        }

        $db = Database::getInstance();
        $this->ensurePinnedColumn($db);

        $stmt = $db->prepare("SELECT id, title, model_id, system_prompt, is_pinned, updated_at FROM conversations WHERE user_id = ? AND is_archived = 0 ORDER BY is_pinned DESC, updated_at DESC LIMIT 50");
        $stmt->execute([(int)$_SESSION['user_id']]);
        echo json_encode(['conversations' => $stmt->fetchAll()]);
    }

    public function getConversation(array $params): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non connecté']);
            return;
        }

        $convId = $params['id'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$convId, $userId]);
        $conv = $stmt->fetch();

        if (!$conv) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation introuvable']);
            return;
        }

        $msgStmt = $db->prepare("SELECT id, role, content, tokens_input, tokens_output, credits_deducted, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC");
        $msgStmt->execute([$convId]);
        $messages = $msgStmt->fetchAll();

        echo json_encode([
            'conversation' => $conv,
            'messages'     => $messages
        ]);
    }

    public function updateConversationPrompt(array $params): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            return;
        }

        $convId = $params['id'] ?? '';
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $prompt = trim($input['system_prompt'] ?? '');

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE conversations SET system_prompt = ?, updated_at = updated_at WHERE id = ? AND user_id = ?");
        $stmt->execute([$prompt ?: null, $convId, (int)$_SESSION['user_id']]);

        echo json_encode(['success' => true]);
    }

    public function renameConversation(array $params): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non connecté']);
            return;
        }

        $convId = $params['id'] ?? '';
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $title  = mb_substr(trim( preg_replace('/\s+/', ' ', (string)($input['title'] ?? '')) ), 0, 120);

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Le titre ne peut pas être vide.']);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE conversations SET title = ?, updated_at = updated_at WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $convId, (int)$_SESSION['user_id']]);

        echo json_encode(['success' => true, 'title' => $title]);
    }

    public function togglePinConversation(array $params): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non connecté']);
            return;
        }

        $convId = $params['id'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();
        $this->ensurePinnedColumn($db);

        $stmt = $db->prepare("SELECT is_pinned FROM conversations WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$convId, $userId]);
        $conv = $stmt->fetch();

        if (!$conv) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation introuvable']);
            return;
        }

        $newPin = ((int)$conv['is_pinned'] === 1) ? 0 : 1;
        $upd = $db->prepare("UPDATE conversations SET is_pinned = ?, updated_at = updated_at WHERE id = ? AND user_id = ?");
        $upd->execute([$newPin, $convId, $userId]);

        echo json_encode(['success' => true, 'is_pinned' => $newPin]);
    }

    public function exportConversation(array $params): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $convId = $params['id'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$convId, $userId]);
        $conv = $stmt->fetch();

        if (!$conv) {
            http_response_code(404);
            die('Conversation introuvable.');
        }

        $msgStmt = $db->prepare("SELECT role, content, created_at, credits_deducted FROM messages WHERE conversation_id = ? ORDER BY id ASC");
        $msgStmt->execute([$convId]);
        $messages = $msgStmt->fetchAll();

        I18n::init();
        $md = "# " . $conv['title'] . "\n";
        $md .= "*" . I18n::t('export_subtitle', ['date' => date('d/m/Y H:i')]) . "*\n\n---\n\n";

        foreach ($messages as $m) {
            $author = ($m['role'] === 'user') ? I18n::t('export_user') : I18n::t('export_assistant');
            $md .= "### " . $author . " *(" . $m['created_at'] . ")*\n\n";
            $md .= $m['content'] . "\n\n---\n\n";
        }

        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($conv['title'])) ?: 'conversation';
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.md"');
        echo $md;
        exit;
    }

    public function deleteConversation(array $params): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            return;
        }
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$params['id'] ?? '', (int)$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    }

    public function stream(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        session_write_close();

        $user = User::findById($userId);
        if (!$user || (int)$user['credits_balance'] <= 0) {
            http_response_code(402);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Votre quota de crédits IA est épuisé pour ce mois. Contactez votre administrateur.']);
            return;
        }

        $input          = json_decode(file_get_contents('php://input'), true) ?? [];
        $prompt         = trim($input['prompt'] ?? '');
        $conversationId = trim($input['conversation_id'] ?? '');
        $modelId        = (int)($input['model_id'] ?? $user['default_model_id'] ?? 1);
        $customPrompt   = trim($input['system_prompt'] ?? '');
        $attachmentIds  = is_array($input['attachments'] ?? null) ? $input['attachments'] : [];

        if ($prompt === '' && empty($attachmentIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message vide.']);
            return;
        }

        $db = Database::getInstance();
        $this->ensurePinnedColumn($db);

        $stmtModel = $db->prepare("SELECT * FROM ai_models WHERE id = ? AND is_enabled = 1 LIMIT 1");
        $stmtModel->execute([$modelId]);
        $model = $stmtModel->fetch();

        if (!$model || ($model['min_role'] === 'admin' && $user['role'] !== 'admin')) {
            $model = $db->query("SELECT * FROM ai_models WHERE is_enabled = 1 AND min_role = 'user' ORDER BY is_default DESC LIMIT 1")->fetch();
        }

        $isNewConv = false;
        if (empty($conversationId)) {
            $isNewConv = true;
            $conversationId = User::generateUuid();
            $title = mb_substr(preg_replace('/\s+/', ' ', $prompt), 0, 55) ?: 'Analyse de document';
            $insConv = $db->prepare("INSERT INTO conversations (id, user_id, title, model_id, system_prompt, is_pinned) VALUES (?, ?, ?, ?, ?, 0)");
            $insConv->execute([$conversationId, $userId, $title, $model['id'], $customPrompt ?: null]);
        } else {
            $chkConv = $db->prepare("SELECT id, system_prompt FROM conversations WHERE id = ? AND user_id = ?");
            $chkConv->execute([$conversationId, $userId]);
            $existingConv = $chkConv->fetch();
            if (!$existingConv) {
                http_response_code(403);
                return;
            }
            if ($customPrompt === '' && !empty($existingConv['system_prompt'])) {
                $customPrompt = $existingConv['system_prompt'];
            }
            // Mettre à jour immédiatement updated_at pour remonter la conversation
            $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        }

        $userMsgCreatedAt = date('Y-m-d H:i:s');
        $insMsg = $db->prepare("INSERT INTO messages (conversation_id, role, content, model_id, created_at) VALUES (?, 'user', ?, ?, ?)");
        $insMsg->execute([$conversationId, $prompt, $model['id'], $userMsgCreatedAt]);
        $userMessageId = (int)$db->lastInsertId();

        if (!empty($attachmentIds)) {
            $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
            $updAtt = $db->prepare("UPDATE attachments SET message_id = ? WHERE user_id = ? AND id IN (" . $placeholders . ")");
            $updAtt->execute(array_merge([$userMessageId, $userId], $attachmentIds));
        }

        $histStmt = $db->prepare("SELECT role, content FROM (SELECT id, role, content FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 20) sub ORDER BY id ASC");
        $histStmt->execute([$conversationId]);
        $history = $histStmt->fetchAll();

        $preparedAttachments = FileProcessorService::prepareAttachmentsForLLM($userId, $attachmentIds);
        $systemPrompt = MemoryService::buildSystemPrompt($userId, (bool)$user['auto_memory_enabled'], $customPrompt, $prompt);

        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
        ignore_user_abort(true);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $this->sendSseEvent('meta', [
            'conversation_id' => $conversationId,
            'is_new'          => $isNewConv,
            'title'           => $isNewConv ? ($title ?? 'Nouvelle conversation') : null,
            'model_name'      => $model['display_name'],
            'user_created_at' => $userMsgCreatedAt,
            'updated_at'      => $userMsgCreatedAt
        ]);

        $fullAiText = '';

        try {
            $provider = $this->instantiateProvider($model);

            $usage = $provider->streamChat(
                $history,
                $preparedAttachments,
                [
                    'system_prompt' => $systemPrompt,
                    'max_tokens'    => (int)$model['max_output_tokens']
                ],
                function (string $delta) use (&$fullAiText) {
                    $fullAiText .= $delta;
                    $this->sendSseEvent('delta', ['text' => $delta]);
                }
            );

            $db = Database::reconnect();
            $newMemories = MemoryService::extractAndSaveAutoMemories($userId, $fullAiText);
            if (!empty($newMemories)) {
                $this->sendSseEvent('memory_saved', ['facts' => $newMemories]);
            }

            $inTokens  = $usage['input_tokens'] > 0 ? $usage['input_tokens'] : max(1, (int)ceil((mb_strlen($prompt) + mb_strlen($systemPrompt)) / 3.6));
            $outTokens = $usage['output_tokens'] > 0 ? $usage['output_tokens'] : max(1, (int)ceil(mb_strlen($fullAiText) / 3.6));

            $costIn  = (int)round(($inTokens / 1000000) * (int)$model['cost_input_per_1m']);
            $costOut = (int)round(($outTokens / 1000000) * (int)$model['cost_output_per_1m']);
            $totalCreditsBilled = max(1, $costIn + $costOut);

            $aiMsgCreatedAt = date('Y-m-d H:i:s');
            $insAi = $db->prepare("INSERT INTO messages (conversation_id, role, content, model_id, tokens_input, tokens_output, credits_deducted, created_at) VALUES (?, 'assistant', ?, ?, ?, ?, ?, ?)");
            $insAi->execute([$conversationId, $fullAiText, $model['id'], $inTokens, $outTokens, $totalCreditsBilled, $aiMsgCreatedAt]);

            $db->prepare("UPDATE conversations SET updated_at = ? WHERE id = ?")->execute([$aiMsgCreatedAt, $conversationId]);

            // Déduction directe en BDD
            $updUser = $db->prepare("UPDATE users SET credits_balance = GREATEST(0, credits_balance - ?) WHERE id = ?");
            $updUser->execute([$totalCreditsBilled, $userId]);

            $logStmt = $db->prepare("INSERT INTO usage_logs (user_id, model_id, conversation_id, tokens_input, tokens_output, provider_cost_raw, client_credits_billed, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $logStmt->execute([$userId, $model['id'], $conversationId, $inTokens, $outTokens, $totalCreditsBilled, $totalCreditsBilled, $_SERVER['REMOTE_ADDR'] ?? null]);

            $updatedUser = User::findById($userId);

            $this->sendSseEvent('done', [
                'tokens_in'        => $inTokens,
                'tokens_out'       => $outTokens,
                'credits_deducted' => $totalCreditsBilled,
                'credits_balance'  => (int)$updatedUser['credits_balance'],
                'credits_limit'    => (int)$updatedUser['credits_monthly_limit'],
                'ai_created_at'    => $aiMsgCreatedAt
            ]);
        } catch (Throwable $e) {
            error_log('[Stream Error] ' . $e->getMessage());
            $this->sendSseEvent('error', ['message' => $e->getMessage()]);
        }
    }

    private function instantiateProvider(array $model): LLMProviderInterface
    {
        $config = [];
        if (!empty($model['config_json'])) {
            $decrypted = Security::decrypt($model['config_json']);
            if ($decrypted) {
                $config = json_decode($decrypted, true) ?? [];
            }
        }

        return match ($model['provider']) {
            'vertex_gemini', 'google_ai' => new VertexGeminiProvider($model['model_identifier'], $config, $model['provider']),
            'anthropic'                  => new AnthropicProvider($model['model_identifier'], $config),
            'openai'                     => new OpenAIProvider($model['model_identifier'], $config),
            default                      => new VertexGeminiProvider($model['model_identifier'], $config, 'google_ai'),
        };
    }

    private function sendSseEvent(string $event, array $data): void
    {
        echo "event: " . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}
