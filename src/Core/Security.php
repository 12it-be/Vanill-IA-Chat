<?php

namespace VanillIA\Core;

class Security
{
    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $options = ($algo === PASSWORD_ARGON2ID)
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
            : [];
        return password_hash($password, $algo, $options);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $options = ($algo === PASSWORD_ARGON2ID)
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
            : [];
        return password_needs_rehash($hash, $algo, $options);
    }

    public static function generateCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function encrypt(string $data): string
    {
        $key = hash('sha256', Env::get('APP_SECRET', 'fallback_secret_32_bytes_length!!'), true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload);
        if (strlen($raw) < 28) {
            return null;
        }
        $key = hash('sha256', Env::get('APP_SECRET', 'fallback_secret_32_bytes_length!!'), true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : null;
    }
}