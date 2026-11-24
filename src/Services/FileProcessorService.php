<?php

namespace VanillIA\Services;

use RuntimeException;
use VanillIA\Core\Database;
use VanillIA\Models\User;
use ZipArchive;

class FileProcessorService
{
    private const MAX_FILE_SIZE = 15 * 1024 * 1024;

    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv', 'text/markdown', 'application/json',
        'text/x-php', 'application/javascript', 'text/html', 'text/css', 'application/sql'
    ];

    public static function handleUpload(int $userId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Erreur lors du transfert du fichier.");
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException("Le fichier dépasse la limite autorisée de 15 Mo.");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $textExtensions = ['txt', 'csv', 'md', 'json', 'php', 'js', 'ts', 'html', 'css', 'sql', 'log', 'xml', 'yml', 'yaml', 'ini', 'sh', 'ps1'];

        if (!in_array($mime, self::ALLOWED_MIMES, true) && !in_array($ext, $textExtensions, true)) {
            throw new RuntimeException("Type de fichier non supporté ({$mime}).");
        }

        $uuid = User::generateUuid();
        $userDir = __DIR__ . '/../../storage/uploads/' . $userId;
        if (!is_dir($userDir)) {
            mkdir($userDir, 0750, true);
        }

        $safeFilename = $uuid . '.' . $ext;
        $destPath = $userDir . '/' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException("Impossible d'enregistrer le fichier sur le serveur.");
        }

        $extractedText = self::extractText($destPath, $mime, $ext);

        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO attachments (id, user_id, original_name, storage_path, mime_type, file_size, extracted_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $uuid,
            $userId,
            basename($file['name']),
            $destPath,
            $mime,
            (int)$file['size'],
            $extractedText
        ]);

        return [
            'id'            => $uuid,
            'original_name' => basename($file['name']),
            'mime_type'     => $mime,
            'file_size'     => (int)$file['size']
        ];
    }

    public static function prepareAttachmentsForLLM(int $userId, array $attachmentIds): array
    {
        if (empty($attachmentIds)) {
            return [];
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
        $params = array_merge([$userId], $attachmentIds);

        $stmt = $db->prepare("SELECT * FROM attachments WHERE user_id = ? AND id IN ({$placeholders})");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $prepared = [];
        foreach ($rows as $row) {
            $item = [
                'id'             => $row['id'],
                'original_name'  => $row['original_name'],
                'mime_type'      => $row['mime_type'],
                'extracted_text' => $row['extracted_text'],
                'base64_data'    => null
            ];

            if (file_exists($row['storage_path']) && in_array($row['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                $item['base64_data'] = base64_encode(file_get_contents($row['storage_path']));
            }

            $prepared[] = $item;
        }

        return $prepared;
    }

    private static function extractText(string $filePath, string $mime, string $ext): ?string
    {
        if ($ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
                    return trim(strip_tags($xml));
                }
            }
        }

        if ($ext === 'xlsx' || $mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName('xl/sharedStrings.xml');
                $zip->close();
                if ($xml !== false) {
                    $xml = str_replace('</si>', "\n", $xml);
                    return trim(strip_tags($xml));
                }
            }
        }

        $textExtensions = ['txt', 'csv', 'md', 'json', 'php', 'js', 'ts', 'html', 'css', 'sql', 'log', 'xml', 'yml', 'yaml', 'ini', 'sh', 'ps1'];
        if (str_starts_with($mime, 'text/') || in_array($ext, $textExtensions, true)) {
            $content = file_get_contents($filePath);
            return mb_substr($content, 0, 150000);
        }

        return null;
    }
}