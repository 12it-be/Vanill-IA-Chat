<?php

namespace VanillIA\Providers;

use RuntimeException;
use VanillIA\Core\Env;

class OpenAIProvider implements LLMProviderInterface
{
    private string $modelId;
    private array $config;

    public function __construct(string $modelId, array $config = [])
    {
        $this->modelId = trim($modelId);
        $this->config  = $config;
    }

    public function streamChat(array $messages, array $attachments, array $options, callable $onChunk): array
    {
        $apiKey = trim((string)($this->config['api_key'] ?? Env::get('OPENAI_API_KEY', '')));
        if (empty($apiKey)) {
            throw new RuntimeException("Clé API OpenAI non configurée.");
        }

        $formatted = [];
        if (!empty($options['system_prompt'])) {
            $formatted[] = ['role' => 'system', 'content' => $options['system_prompt']];
        }

        $total = count($messages);
        foreach ($messages as $idx => $msg) {
            if ($msg['role'] === 'system') {
                continue;
            }
            $role = ($msg['role'] === 'assistant') ? 'assistant' : 'user';

            if ($idx === $total - 1 && $role === 'user' && !empty($attachments)) {
                $contentParts = [['type' => 'text', 'text' => $msg['content']]];
                foreach ($attachments as $att) {
                    if (!empty($att['base64_data']) && str_starts_with($att['mime_type'], 'image/')) {
                        $contentParts[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$att['mime_type']};base64,{$att['base64_data']}"
                            ]
                        ];
                    } elseif (!empty($att['extracted_text'])) {
                        $contentParts[] = [
                            'type' => 'text',
                            'text' => "\n\n--- Fichier joint ({$att['original_name']}) ---\n" . $att['extracted_text']
                        ];
                    }
                }
                $formatted[] = ['role' => 'user', 'content' => $contentParts];
            } else {
                $formatted[] = ['role' => $role, 'content' => $msg['content']];
            }
        }

        $payload = [
            'model'                 => $this->modelId,
            'messages'              => $formatted,
            'max_completion_tokens' => (int)($options['max_tokens'] ?? 4096),
            'stream'                => true,
            'stream_options'        => ['include_usage' => true]
        ];

        $inputTokens  = 0;
        $outputTokens = 0;
        $buffer       = '';
        $rawErrorBody = '';

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$apiKey}",
                "Content-Type: application/json"
            ],
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
                        $delta = $data['choices'][0]['delta']['content'] ?? '';
                        if ($delta !== '') {
                            $onChunk($delta);
                        }
                        if (!empty($data['usage'])) {
                            $inputTokens  = (int)($data['usage']['prompt_tokens'] ?? 0);
                            $outputTokens = (int)($data['usage']['completion_tokens'] ?? 0);
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
                $apiMsg = $decodedErr['error']['message'] ?? substr(strip_tags($rawErrorBody), 0, 300);
            }
            throw new RuntimeException("Erreur API OpenAI (HTTP {$httpCode}) : " . $apiMsg);
        }

        return [
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens
        ];
    }
}
