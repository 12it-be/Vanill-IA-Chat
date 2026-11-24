<?php

namespace VanillIA\Models;

use VanillIA\Core\Database;
use VanillIA\Core\Env;

class User
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $user = self::checkAndApplyMonthlyRefill($user);
        }
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findOrCreateByEntra(string $oid, string $email, string $displayName): array
    {
        $db = Database::getInstance();
        $email = strtolower(trim($email));

        $stmt = $db->prepare("SELECT * FROM users WHERE entra_oid = ? OR email = ? LIMIT 1");
        $stmt->execute([$oid, $email]);
        $user = $stmt->fetch();

        if ($user) {
            if (empty($user['entra_oid'])) {
                $upd = $db->prepare("UPDATE users SET entra_oid = ? WHERE id = ?");
                $upd->execute([$oid, $user['id']]);
                $user['entra_oid'] = $oid;
            }
            return self::checkAndApplyMonthlyRefill($user);
        }

        $uuid = self::generateUuid();
        $startCredits = (int)Env::get('DEFAULT_START_CREDITS', 5000000);
        $monthlyLimit = (int)Env::get('DEFAULT_MONTHLY_CREDITS', 5000000);

        $ins = $db->prepare("INSERT INTO users (uuid, email, entra_oid, display_name, role, credits_balance, credits_monthly_limit) VALUES (?, ?, ?, ?, 'user', ?, ?)");
        $ins->execute([$uuid, $email, $oid, $displayName, $startCredits, $monthlyLimit]);

        return self::findById((int)$db->lastInsertId());
    }

    public static function checkAndApplyMonthlyRefill(array $user): array
    {
        if ((int)$user['auto_refill'] !== 1) {
            return $user;
        }

        $lastRefill = strtotime($user['last_refill_at']);
        if (time() - $lastRefill >= (30 * 86400)) {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET credits_balance = credits_monthly_limit, last_refill_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            $user['credits_balance'] = $user['credits_monthly_limit'];
            $user['last_refill_at'] = date('Y-m-d H:i:s');
        }

        return $user;
    }

    public static function deductCredits(int $userId, int $credits): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET credits_balance = GREATEST(0, credits_balance - ?) WHERE id = ?");
        $stmt->execute([$credits, $userId]);
    }

    public static function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}