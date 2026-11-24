<?php

namespace VanillIA\Controllers;

use Throwable;
use VanillIA\Services\FileProcessorService;

class FileController
{
    public function upload(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié.']);
            return;
        }

        if (empty($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Aucun fichier reçu.']);
            return;
        }

        try {
            $result = FileProcessorService::handleUpload((int)$_SESSION['user_id'], $_FILES['file']);
            echo json_encode(['success' => true, 'file' => $result]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}