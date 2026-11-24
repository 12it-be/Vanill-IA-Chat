<?php

namespace VanillIA\Providers;

interface LLMProviderInterface
{
    public function streamChat(array $messages, array $attachments, array $options, callable $onChunk): array;
}