<?php

namespace VanillIA\Providers;

use RuntimeException;
use VanillIA\Core\Env;

class VertexGeminiProvider implements LLMProviderInterface
{
    private string $modelId;
    private array $config;
    private string $providerType;

    public function __construct(string $modelId, array $config = [], string $providerType = 'google_ai')
    {
        $cleanId = trim($modelId);
        if (str_starts_with($cleanId, 'models/')) {
            $cleanId = substr($cleanId, 7);
        }
        $this->modelId      = $cleanId;
        $this->config       = $config;
        $this->providerType = $providerType;
    }

    public function streamChat(array $messages, array $attachments, array $options, callable $onChunk): array
    {
        $projectId = trim((string)($this->config['project_id'] ?? Env::get('VERTEX_PROJECT_ID', '')));
        $region    = trim((string)($this->config['region'] ?? Env::get('VERTEX_REGION', 'europe-west1')));
        $keyPath   = trim((string)($this->config['key_path'] ?? Env::get('VERTEX_KEY_PATH', '')));
        $apiKey    = trim((string)($this->config['api_key'] ?? Env::get('GEMINI_API_KEY', '')));

        if ($this->providerType === 'google_ai' || !empty($apiKey)) {
            if (empty($apiKey)) {
                throw new RuntimeException("Cle API Google AI Studio manquante. Renseignez-la dans l'Admin ou dans GEMINI_API_KEY.");
            }
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->modelId . ":streamGenerateContent?alt=sse&key=" . urlencode($apiKey);
            $headers = ["Content-Type: application/json"];
        } elseif ($this->providerType === 'vertex_gemini' && !empty($projectId) && !empty($keyPath) && file_exists($keyPath)) {
            $accessToken = $this->getVertexAccessToken($keyPath);
            $url = "https://" . $region . "-aiplatform.googleapis.com/v1/projects/" . $projectId . "/locations/" . $region . "/publishers/google/models/" . $this->modelId . ":streamGenerateContent?alt=sse";
            $headers = [
                "Authorization: Bearer " . $accessToken,
                "Content-Type: application/json"
            ];
        } else {
            throw new RuntimeException("Configuration Gemini incomplete : cle API Google AI Studio ou compte de service Vertex AI requis.");
        }

        $contents = [];
        $totalMessages = count($messages);

        foreach ($messages as $idx => $msg) {
            if ($msg['role'] === 'system') {
                continue;
            }
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $parts = [['text' => $msg['content']]];

            if ($idx === $totalMessages - 1 && $role === 'user' && !empty($attachments)) {
                foreach ($attachments as $att) {
                    if (!empty($att['base64_data']) && in_array($att['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])) {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => $att['mime_type'],
                                'data'     => $att['base64_data']
                            ]
                        ];
                    } elseif (!empty($att['extracted_text'])) {
                        $parts[] = [
                            'text' => "\n\n--- Fichier joint (" . $att['original_name'] . ") ---\n" . $att['extracted_text'] . "\n--- Fin ---\n"
                        ];
                    }
                }
            }

            $contents[] = ['role' => $role, 'parts' => $parts];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => (float)($options['temperature'] ?? 0.7),
                'maxOutputTokens' => (int)($options['max_tokens'] ?? 8192),
            ]
        ];

        if (!empty($options['system_prompt'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system_prompt']]]
            ];
        }

        $inputTokens  = 0;
        $outputTokens = 0;
        $buffer       = '';
        $rawErrorBody = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, &$rawErrorBody, &$inputTokens, &$outputTokens, $onChunk) {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode >= 400) {
                    $rawErrorBody .= $chunk;
                    return strlen($chunk);
                }

                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if (str_starts_with($line, 'data: ')) {
                        $jsonStr = substr($line, 6);
                        if ($jsonStr === '[DONE]') {
                            continue;
                        }
                        $data = json_decode($jsonStr, true);
                        if (!is_array($data)) {
                            continue;
                        }

                        $textDelta = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        if ($textDelta !== '') {
                            $onChunk($textDelta);
                        }

                        if (isset($data['usageMetadata'])) {
                            $inputTokens  = (int)($data['usageMetadata']['promptTokenCount'] ?? $inputTokens);
                            $outputTokens = (int)($data['usageMetadata']['candidatesTokenCount'] ?? $outputTokens);
                        }
                    }
                }
                return strlen($chunk);
            }
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode >= 400) {
            $apiMsg = $curlErr;
            if (!empty($rawErrorBody)) {
                $decodedErr = json_decode($rawErrorBody, true);
                $apiMsg = $decodedErr['error']['message']
                    ?? $decodedErr[0]['error']['message']
                    ?? substr(strip_tags($rawErrorBody), 0, 300);
            }
            throw new RuntimeException("Erreur API Gemini (HTTP " . $httpCode . ") : " . $apiMsg);
        }

        return [
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens
        ];
    }

    private function getVertexAccessToken(string $keyPath): string
    {
        $cacheFile = __DIR__ . '/../../storage/logs/vertex_token_cache.json';
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached['access_token']) && ($cached['expires_at'] ?? 0) > time() + 60) {
                return $cached['access_token'];
            }
        }

        $sa = json_decode(file_get_contents($keyPath), true);
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException("Fichier JSON Service Account Google invalide.");
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim  = $this->base64UrlEncode(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ]));

        $signatureInput = $header . "." . $claim;
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException("Echec de la signature JWT OpenSSL RS256.");
        }

        $jwt = $signatureInput . "." . $this->base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ])
        ]);

        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($res['access_token'])) {
            throw new RuntimeException("Impossible d'obtenir le token OAuth2 Vertex AI.");
        }

        @file_put_contents($cacheFile, json_encode([
            'access_token' => $res['access_token'],
            'expires_at'   => $now + (int)($res['expires_in'] ?? 3500)
        ]));

        return $res['access_token'];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
