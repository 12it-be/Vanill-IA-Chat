<?php

namespace VanillIA\Services;

use PDO;
use VanillIA\Core\Database;

class MemoryService
{
    public static function buildSystemPrompt(int $userId, bool $autoMemoryEnabled = true, ?string $customPrompt = null, string $currentUserQuery = ''): string
    {
        $db = Database::getInstance();

        $globalPrompt = $db->query("SELECT setting_value FROM app_settings WHERE setting_key = 'global_system_prompt' LIMIT 1")->fetchColumn();
        if (empty($globalPrompt)) {
            $globalPrompt = "Tu es l'assistant IA professionnel de la plateforme Vanill-IA Chat. Réponds de manière claire, structurée et pertinente.";
        }

        $blocks = [];
        $blocks[] = trim((string)$globalPrompt);

        if (!empty($customPrompt)) {
            $blocks[] = "[INSTRUCTIONS SPÉCIFIQUES À CETTE CONVERSATION]\n" . trim($customPrompt);
        }

        $ragContext = self::retrieveRelevantKnowledge($currentUserQuery);
        if (!empty($ragContext)) {
            $blocks[] = "[BASE DE CONNAISSANCES INTERNE DE L'ENTREPRISE (RAG)]\nAppuie-toi en priorité sur ces documents internes pour répondre :\n" . $ragContext;
        }

        $stmt = $db->prepare("SELECT content FROM user_memories WHERE user_id = ? AND is_active = 1 ORDER BY id ASC");
        $stmt->execute([$userId]);
        $memories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($memories)) {
            $memList = implode("\n- ", $memories);
            $blocks[] = "[SOUVENIRS & CONTEXTE UTILISATEUR MÉMORISÉ]\nUtilise ces informations sur l'utilisateur :\n- " . $memList;
        }

        if ($autoMemoryEnabled) {
            $blocks[] = "[INSTRUCTION DE MÉMORISATION AUTOMATIQUE]\nSi l'utilisateur mentionne un fait durable important sur lui (son métier, ses projets, ses préférences de travail) qui n'est pas encore dans tes souvenirs, ajoute tout à la fin de ta réponse une balise unique sous la forme exacte : <memory_save>Le fait concis à mémoriser</memory_save>.";
        }

        return implode("\n\n", $blocks);
    }

    private static function retrieveRelevantKnowledge(string $query): string
    {
        if (trim($query) === '') {
            return '';
        }

        $db = Database::getInstance();
        $docs = $db->query("SELECT title, content_text FROM knowledge_docs WHERE is_active = 1 ORDER BY id DESC")->fetchAll();
        if (empty($docs)) {
            return '';
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 3));

        if (empty($keywords)) {
            return '';
        }

        $scoredDocs = [];
        foreach ($docs as $doc) {
            $score = 0;
            $titleLower = mb_strtolower($doc['title']);
            $haystack   = $titleLower . ' ' . mb_strtolower($doc['content_text']);

            foreach ($keywords as $kw) {
                $count = substr_count($haystack, $kw);
                if ($count > 0) {
                    $score += ($count * 5);
                    if (str_contains($titleLower, $kw)) {
                        $score += 10;
                    }
                }
            }

            if ($score > 0) {
                $scoredDocs[] = [
                    'title'   => $doc['title'],
                    'content' => mb_substr($doc['content_text'], 0, 12000),
                    'score'   => $score
                ];
            }
        }

        if (empty($scoredDocs)) {
            return '';
        }

        usort($scoredDocs, fn($a, $b) => $b['score'] <=> $a['score']);
        $topDocs = array_slice($scoredDocs, 0, 3);

        $out = [];
        foreach ($topDocs as $d) {
            $out[] = "--- Document : " . $d['title'] . " ---\n" . $d['content'];
        }

        return implode("\n\n", $out);
    }

    public static function extractAndSaveAutoMemories(int $userId, string &$fullResponseText): array
    {
        $saved = [];
        if (preg_match_all('/<memory_save>(.*?)<\/memory_save>/is', $fullResponseText, $matches)) {
            $db = Database::getInstance();
            foreach ($matches[1] as $fact) {
                $fact = trim(strip_tags($fact));
                if (mb_strlen($fact) > 5 && mb_strlen($fact) <= 500) {
                    $chk = $db->prepare("SELECT COUNT(*) FROM user_memories WHERE user_id = ? AND content = ?");
                    $chk->execute([$userId, $fact]);
                    if ((int)$chk->fetchColumn() === 0) {
                        $ins = $db->prepare("INSERT INTO user_memories (user_id, content, source, is_active) VALUES (?, ?, 'auto', 1)");
                        $ins->execute([$userId, $fact]);
                        $saved[] = $fact;
                    }
                }
            }
            $fullResponseText = trim(preg_replace('/<memory_save>.*?<\/memory_save>/is', '', $fullResponseText));
        }

        return $saved;
    }
}
