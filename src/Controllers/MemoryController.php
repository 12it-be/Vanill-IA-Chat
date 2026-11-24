<?php

namespace VanillIA\Controllers;

use VanillIA\Core\Database;

class MemoryController
{
    private function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    public function list(): void
    {
        $userId = $this->requireAuth();
        header('Content-Type: application/json');

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, content, source, is_active, created_at FROM user_memories WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);

        echo json_encode(['memories' => $stmt->fetchAll()]);
    }

    public function create(): void
    {
        $userId = $this->requireAuth();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $content = trim(strip_tags($input['content'] ?? ''));

        if (mb_strlen($content) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Le souvenir est trop court.']);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO user_memories (user_id, content, source, is_active) VALUES (?, ?, 'manual', 1)");
        $stmt->execute([$userId, mb_substr($content, 0, 500)]);

        echo json_encode([
            'success'   => true,
            'id'        => (int)$db->lastInsertId(),
            'content'   => $content,
            'source'    => 'manual',
            'is_active' => 1
        ]);
    }

    public function toggle(array $params): void
    {
        $userId = $this->requireAuth();
        header('Content-Type: application/json');
        $memId = (int)($params['id'] ?? 0);

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE user_memories SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
        $stmt->execute([$memId, $userId]);

        echo json_encode(['success' => true]);
    }

    public function delete(array $params): void
    {
        $userId = $this->requireAuth();
        header('Content-Type: application/json');
        $memId = (int)($params['id'] ?? 0);

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM user_memories WHERE id = ? AND user_id = ?");
        $stmt->execute([$memId, $userId]);

        echo json_encode(['success' => true]);
    }
}