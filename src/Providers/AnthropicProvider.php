<?php

namespace VanillIA\Providers;

use RuntimeException;
use VanillIA\Core\Env;

class AnthropicProvider implements LLMProviderInterface
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
        $apiKey = trim((string)($this->config['api_key'] ?? Env::get('ANTHROPIC_API_KEY', '')));
        if (empty($apiKey)) {
            throw new RuntimeException("Clé API Anthropic (Claude) non configurée.");
        }

        $formattedMessages = [];
        $total = count($messages);

        foreach ($messages as $idx => $msg) {
            if ($msg['role'] === 'system') {
                continue;
            }
            $role = ($msg['role'] === 'assistant') ? 'assistant' : 'user';
            $contentBlocks = [];

            if ($idx === $total - 1 && $role === 'user' && !empty($attachments)) {
                foreach ($attachments as $att) {
                    if (!empty($att['base64_data']) && str_starts_with($att['mime_type'], 'image/')) {
                        $contentBlocks[] = [
                            'type' => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $att['mime_type'],
                                'data'       => $att['base64_data']
                            ]
                        ];
                    } elseif (!empty($att['base64_data']) && $att['mime_type'] === 'application/pdf') {
                        $contentBlocks[] = [
                            'type' => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $att['base64_data']
                            ]
                        ];
                    } elseif (!empty($att['extracted_text'])) {
                        $contentBlocks[] = [
                            'type' => 'text',
                            'text' => "Fichier joint ({$att['original_name']}) :\n" . $att['extracted_text']
                        ];
                    }
                }
            }

            $contentBlocks[] = ['type' => 'text', 'text' => $msg['content']];
            $formattedMessages[] = ['role' => $role, 'content' => $contentBlocks];
        }

        $payload = [
            'model'      => $this->modelId,
            'max_tokens' => (int)($options['max_tokens'] ?? 4096),
            'messages'   => $formattedMessages,
            'stream'     => true,
        ];

        if (!empty($options['system_prompt'])) {
            $payload['system'] = $options['system_prompt'];
        }

        $inputTokens  = 0;
        $outputTokens = 0;
        $buffer       = '';
        $rawErrorBody = '';

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "x-api-key: {$apiKey}",
                "anthropic-version: 2023-06-01",
                "content-type: application/json"
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
                        $data = json_decode(substr($line, 6), true);
                        if (!is_array($data)) {
                            continue;
                        }
                        $type = $data['type'] ?? '';
                        if ($type === 'message_start') {
                            $inputTokens = (int)($data['message']['usage']['input_tokens'] ?? 0);
                        } elseif ($type === 'content_block_delta') {
                            $delta = $data['delta']['text'] ?? '';
                            if ($delta !== '') {
                                $onChunk($delta);
                            }
                        } elseif ($type === 'message_delta') {
                            $outputTokens = (int)($data['usage']['output_tokens'] ?? $outputTokens);
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
            throw new RuntimeException("Erreur API Anthropic Claude (HTTP {$httpCode}) : " . $apiMsg);
        }

        return [
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens
        ];
    }
}
