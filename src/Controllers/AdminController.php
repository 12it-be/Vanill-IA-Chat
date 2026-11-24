<?php

namespace VanillIA\Controllers;

use Throwable;
use VanillIA\Core\Database;
use VanillIA\Core\I18n;
use VanillIA\Core\Migrator;
use VanillIA\Core\Security;
use VanillIA\Models\User;
use VanillIA\Services\FileProcessorService;

class AdminController
{
    private function requireAdmin(): array
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        $user = User::findById((int)$_SESSION['user_id']);
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            die('Accès réservé aux administrateurs.');
        }
        return $user;
    }

    public function index(): void
    {
        $db = Database::getInstance();
        Migrator::ensureSchema($db);
        $admin = $this->requireAdmin();
        $lang = I18n::init($admin['preferred_lang'] ?? null);
        $theme = I18n::getTheme($admin['preferred_theme'] ?? null);

        // Utilisateurs avec agrégation de leur consommation sur les 30 derniers jours + infos de reset
        $users = $db->query("
            SELECT 
                u.id,
                u.email,
                u.display_name,
                u.role,
                u.is_active,
                u.credits_balance,
                u.credits_monthly_limit,
                u.auto_refill,
                u.last_refill_at,
                u.entra_oid,
                u.created_at,
                COUNT(l.id) as requests_30d,
                COALESCE(SUM(l.tokens_input), 0) as tokens_in_30d,
                COALESCE(SUM(l.tokens_output), 0) as tokens_out_30d,
                COALESCE(SUM(l.tokens_input + l.tokens_output), 0) as tokens_total_30d,
                COALESCE(SUM(l.client_credits_billed), 0) as credits_spent_30d,
                MAX(l.created_at) as last_used_at
            FROM users u
            LEFT JOIN usage_logs l ON l.user_id = u.id AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id
            ORDER BY u.id ASC
        ")->fetchAll();

        $models = $db->query("SELECT * FROM ai_models ORDER BY is_default DESC, id ASC")->fetchAll();
        $knowledgeDocs = $db->query("SELECT id, title, original_name, CHAR_LENGTH(content_text) as char_len, is_active, created_at FROM knowledge_docs ORDER BY id DESC")->fetchAll();
        $globalPrompt = (string)$db->query("SELECT setting_value FROM app_settings WHERE setting_key = 'global_system_prompt' LIMIT 1")->fetchColumn();

        $stats = $db->query("
            SELECT 
                COUNT(DISTINCT user_id) as active_users,
                COUNT(*) as total_requests,
                COALESCE(SUM(tokens_input + tokens_output), 0) as total_tokens,
                COALESCE(SUM(client_credits_billed), 0) as total_credits_spent
            FROM usage_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetch();

        // Historique global jour par jour (30 derniers jours)
        $dailyGlobalStats = $db->query("
            SELECT 
                DATE(created_at) as usage_date,
                COUNT(*) as requests_count,
                COUNT(DISTINCT user_id) as active_users,
                COALESCE(SUM(tokens_input), 0) as tokens_in,
                COALESCE(SUM(tokens_output), 0) as tokens_out,
                COALESCE(SUM(tokens_input + tokens_output), 0) as tokens_total,
                COALESCE(SUM(client_credits_billed), 0) as credits_spent
            FROM usage_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY usage_date DESC
        ")->fetchAll();

        $csrfToken = Security::generateCsrf();
        require __DIR__ . '/../Views/admin.php';
    }

    public function userUsageDetails(array $params): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $targetUserId = (int)($params['id'] ?? 0);
        $user = User::findById($targetUserId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable']);
            return;
        }

        $db = Database::getInstance();

        $lastRefillTs = !empty($user['last_refill_at']) ? strtotime($user['last_refill_at']) : time();
        $nextRefillTs = $lastRefillTs + (30 * 86400);
        $daysRemaining = max(0, (int)ceil(($nextRefillTs - time()) / 86400));

        // 1. Consommation jour par jour (30 derniers jours)
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
        $stmtDaily->execute([$targetUserId]);
        $daily = $stmtDaily->fetchAll();

        // 2. Répartition par modèle IA (30 derniers jours)
        $stmtModels = $db->prepare("
            SELECT 
                COALESCE(m.display_name, 'Modèle supprimé') as model_name,
                COUNT(*) as requests_count,
                COALESCE(SUM(l.tokens_input), 0) as tokens_in,
                COALESCE(SUM(l.tokens_output), 0) as tokens_out,
                COALESCE(SUM(l.tokens_input + l.tokens_output), 0) as tokens_total,
                ROUND(COALESCE(SUM(l.client_credits_billed), 0) / 1000000, 4) as cost_eur
            FROM usage_logs l
            LEFT JOIN ai_models m ON l.model_id = m.id
            WHERE l.user_id = ? AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY l.model_id, m.display_name
            ORDER BY cost_eur DESC
        ");
        $stmtModels->execute([$targetUserId]);
        $byModel = $stmtModels->fetchAll();

        // 3. Derniers appels détaillés (30 derniers)
        $stmtRecent = $db->prepare("
            SELECT 
                l.created_at,
                COALESCE(m.display_name, 'Modèle supprimé') as model_name,
                l.tokens_input,
                l.tokens_output,
                ROUND(l.client_credits_billed / 1000000, 5) as cost_eur,
                l.ip_address
            FROM usage_logs l
            LEFT JOIN ai_models m ON l.model_id = m.id
            WHERE l.user_id = ?
            ORDER BY l.id DESC
            LIMIT 30
        ");
        $stmtRecent->execute([$targetUserId]);
        $recentLogs = $stmtRecent->fetchAll();

        echo json_encode([
            'user' => [
                'id'               => (int)$user['id'],
                'display_name'     => $user['display_name'],
                'email'            => $user['email'],
                'role'             => $user['role'],
                'balance_eur'      => round((int)$user['credits_balance'] / 1000000, 4),
                'limit_eur'        => round((int)$user['credits_monthly_limit'] / 1000000, 2),
                'auto_refill'      => (int)$user['auto_refill'] === 1,
                'last_refill_at'   => date('d/m/Y à H:i', $lastRefillTs),
                'next_refill_at'   => (int)$user['auto_refill'] === 1 ? date('d/m/Y à H:i', $nextRefillTs) : 'Désactivé',
                'days_until_reset' => (int)$user['auto_refill'] === 1 ? $daysRemaining : null,
            ],
            'daily'       => $daily,
            'by_model'    => $byModel,
            'recent_logs' => $recentLogs
        ]);
    }

    public function updateUser(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!Security::validateCsrf($input['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $db = Database::getInstance();
        $action = $input['action'] ?? 'update';

        if ($action === 'create') {
            $email = strtolower(trim($input['email'] ?? ''));
            $name  = trim($input['display_name'] ?? '');
            $pass  = $input['password'] ?? '';
            $role  = ($input['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $limit = (int)((float)($input['monthly_eur'] ?? 5) * 1000000);

            $uuid = User::generateUuid();
            $hash = Security::hashPassword($pass);
            $stmt = $db->prepare("INSERT INTO users (uuid, email, password_hash, display_name, role, credits_balance, credits_monthly_limit, auto_refill, last_refill_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$uuid, $email, $hash, $name, $role, $limit, $limit]);
            echo json_encode(['success' => true]);
            return;
        }

        if ($action === 'reset_quota') {
            $userId = (int)($input['user_id'] ?? 0);
            $stmt = $db->prepare("UPDATE users SET credits_balance = credits_monthly_limit, last_refill_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
            return;
        }

        $userId     = (int)($input['user_id'] ?? 0);
        $balance    = (int)((float)($input['balance_eur'] ?? 0) * 1000000);
        $limit      = (int)((float)($input['limit_eur'] ?? 5) * 1000000);
        $autoRefill = (int)($input['auto_refill'] ?? 1);
        $active     = (int)($input['is_active'] ?? 1);
        $role       = ($input['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        $stmt = $db->prepare("UPDATE users SET credits_balance = ?, credits_monthly_limit = ?, auto_refill = ?, is_active = ?, role = ? WHERE id = ?");
        $stmt->execute([$balance, $limit, $autoRefill, $active, $role, $userId]);

        echo json_encode(['success' => true]);
    }

    public function updateModel(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!Security::validateCsrf($input['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $db = Database::getInstance();
        $action = $input['action'] ?? 'update';

        if ($action === 'delete') {
            $modelId = (int)($input['model_id'] ?? 0);
            $db->prepare("DELETE FROM ai_models WHERE id = ?")->execute([$modelId]);
            echo json_encode(['success' => true]);
            return;
        }

        if ($action === 'create') {
            $provider   = in_array($input['provider'] ?? '', ['google_ai', 'vertex_gemini', 'anthropic', 'openai']) ? $input['provider'] : 'google_ai';
            $identifier = trim($input['model_identifier'] ?? '');
            $name       = trim($input['display_name'] ?? $identifier);
            $minRole    = ($input['min_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $costIn     = (int)((float)($input['cost_in_eur'] ?? 0.15) * 1000000);
            $costOut    = (int)((float)($input['cost_out_eur'] ?? 0.60) * 1000000);
            $apiKey     = trim($input['api_key'] ?? '');
            $cfgJson    = $apiKey !== '' ? Security::encrypt(json_encode(['api_key' => $apiKey])) : null;

            $stmt = $db->prepare("INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m, config_json) VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?)");
            $stmt->execute([$provider, $identifier, $name, $minRole, $costIn, $costOut, $cfgJson]);
            echo json_encode(['success' => true]);
            return;
        }

        $modelId    = (int)($input['model_id'] ?? 0);
        $provider   = in_array($input['provider'] ?? '', ['google_ai', 'vertex_gemini', 'anthropic', 'openai']) ? $input['provider'] : 'google_ai';
        $identifier = trim($input['model_identifier'] ?? '');
        $name       = trim($input['display_name'] ?? '');
        $minRole    = ($input['min_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $enabled    = (int)($input['is_enabled'] ?? 1);
        $isDefault  = (int)($input['is_default'] ?? 0);
        $costIn     = (int)((float)($input['cost_in_eur'] ?? 0.15) * 1000000);
        $costOut    = (int)((float)($input['cost_out_eur'] ?? 0.60) * 1000000);
        $apiKey     = trim($input['api_key'] ?? '');

        if ($isDefault === 1) {
            $db->exec("UPDATE ai_models SET is_default = 0");
        }

        if ($apiKey !== '') {
            $encryptedConfig = Security::encrypt(json_encode(['api_key' => $apiKey]));
            $stmt = $db->prepare("UPDATE ai_models SET provider = ?, model_identifier = ?, display_name = ?, min_role = ?, is_enabled = ?, is_default = ?, cost_input_per_1m = ?, cost_output_per_1m = ?, config_json = ? WHERE id = ?");
            $stmt->execute([$provider, $identifier, $name, $minRole, $enabled, $isDefault, $costIn, $costOut, $encryptedConfig, $modelId]);
        } else {
            $stmt = $db->prepare("UPDATE ai_models SET provider = ?, model_identifier = ?, display_name = ?, min_role = ?, is_enabled = ?, is_default = ?, cost_input_per_1m = ?, cost_output_per_1m = ? WHERE id = ?");
            $stmt->execute([$provider, $identifier, $name, $minRole, $enabled, $isDefault, $costIn, $costOut, $modelId]);
        }

        echo json_encode(['success' => true]);
    }

    public function updateSettings(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!Security::validateCsrf($input['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $globalPrompt = trim($input['global_system_prompt'] ?? '');
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('global_system_prompt', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$globalPrompt]);

        echo json_encode(['success' => true]);
    }

    public function uploadKnowledge(): void
    {
        $admin = $this->requireAdmin();
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $title = trim($_POST['title'] ?? '');

            if (!empty($_FILES['file']['tmp_name'])) {
                $uploaded = FileProcessorService::handleUpload((int)$admin['id'], $_FILES['file']);
                $attStmt = $db->prepare("SELECT original_name, extracted_text FROM attachments WHERE id = ?");
                $attStmt->execute([$uploaded['id']]);
                $att = $attStmt->fetch();

                $contentText = trim($att['extracted_text'] ?? '');
                if ($contentText === '') {
                    throw new \RuntimeException("Aucun texte extractible trouvé dans ce fichier (DOCX, XLSX, TXT, MD, CSV recommandés).");
                }
                $docTitle = $title !== '' ? $title : $att['original_name'];
                $stmt = $db->prepare("INSERT INTO knowledge_docs (title, original_name, content_text, is_active) VALUES (?, ?, ?, 1)");
                $stmt->execute([$docTitle, $att['original_name'], $contentText]);
            } else {
                $manualText = trim($_POST['content_text'] ?? '');
                if ($title === '' || $manualText === '') {
                    throw new \RuntimeException("Veuillez fournir un titre et un contenu.");
                }
                $stmt = $db->prepare("INSERT INTO knowledge_docs (title, original_name, content_text, is_active) VALUES (?, 'saisie-manuelle.txt', ?, 1)");
                $stmt->execute([$title, $manualText]);
            }

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteKnowledge(array $params): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $docId = (int)($params['id'] ?? 0);

        $db = Database::getInstance();
        $db->prepare("DELETE FROM knowledge_docs WHERE id = ?")->execute([$docId]);
        echo json_encode(['success' => true]);
    }

    public function exportLogsCsv(): void
    {
        $this->requireAdmin();
        $db = Database::getInstance();

        $rows = $db->query("
            SELECT 
                l.created_at,
                u.display_name,
                u.email,
                m.display_name as model_name,
                l.tokens_input,
                l.tokens_output,
                ROUND(l.client_credits_billed / 1000000, 6) as cost_eur,
                l.ip_address
            FROM usage_logs l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN ai_models m ON l.model_id = m.id
            ORDER BY l.id DESC
            LIMIT 5000
        ")->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="vanillia_chat_usage_logs_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Date', 'Utilisateur', 'Email', 'Modele IA', 'Tokens Input', 'Tokens Output', 'Cout Facture (EUR)', 'IP'], ';');

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['created_at'],
                $r['display_name'],
                $r['email'],
                $r['model_name'],
                $r['tokens_input'],
                $r['tokens_output'],
                str_replace('.', ',', (string)$r['cost_eur']),
                $r['ip_address']
            ], ';');
        }
        fclose($out);
        exit;
    }
}
