<?php

namespace VanillIA\Core;

use PDO;
use Throwable;

class Migrator
{
    private static bool $checked = false;

    public static function ensureSchema(PDO $db): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            $colPin = $db->query("SHOW COLUMNS FROM conversations LIKE 'is_pinned'")->fetch();
            if (!$colPin) {
                $db->exec("ALTER TABLE conversations ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER system_prompt");
            }

            $colLang = $db->query("SHOW COLUMNS FROM users LIKE 'preferred_lang'")->fetch();
            if (!$colLang) {
                $db->exec("ALTER TABLE users ADD COLUMN preferred_lang VARCHAR(5) NOT NULL DEFAULT 'fr' AFTER default_model_id");
            }

            $colTheme = $db->query("SHOW COLUMNS FROM users LIKE 'preferred_theme'")->fetch();
            if (!$colTheme) {
                $db->exec("ALTER TABLE users ADD COLUMN preferred_theme VARCHAR(10) NOT NULL DEFAULT 'dark' AFTER preferred_lang");
            }
        } catch (Throwable $e) {
            // Ignorer si droits restreints ou déjà migré
        }
    }
}
