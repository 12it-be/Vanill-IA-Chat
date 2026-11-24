<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Langue (FR / EN) pour l'installeur standalone
$lang = $_GET['lang'] ?? $_COOKIE['vanillia_lang'] ?? 'fr';
if (!in_array($lang, ['fr', 'en'], true)) {
    $lang = 'fr';
}
if (!empty($_GET['lang']) && !headers_sent()) {
    setcookie('vanillia_lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}

$theme = $_COOKIE['vanillia_theme'] ?? 'dark';
if (!in_array($theme, ['dark', 'light'], true)) {
    $theme = 'dark';
}

$texts = [
    'fr' => [
        'title' => 'Installation — Vanill-IA Chat',
        'subtitle' => 'Assistant d\'installation & Initialisation',
        'already' => 'Vanill-IA Chat est déjà installé. Supprimez storage/installed.lock sur le serveur (ou connectez-vous en tant qu\'administrateur avec ?force_reset=1).',
        'err_email' => 'Veuillez saisir une adresse email administrateur valide.',
        'err_pass' => 'Le mot de passe administrateur doit contenir au moins 8 caractères.',
        'err_db' => 'Veuillez renseigner le nom de la base de données.',
        'success_title' => 'Installation terminée avec succès !',
        'success_desc' => 'La base de données, les tables (RAG, Settings, RBAC, Préférences) et votre compte Administrateur sont prêts.',
        'success_btn' => 'Accéder à la connexion',
        'sec_db' => '1. Serveur MySQL / MariaDB',
        'db_host' => 'Hôte MySQL',
        'db_port' => 'Port',
        'db_name' => 'Nom de la base',
        'db_user' => 'Utilisateur MySQL',
        'db_pass' => 'Mot de passe MySQL',
        'reset_db' => 'Réinitialiser toutes les tables existantes (DROP & RECREATE — Installation fraîche)',
        'sec_admin' => '2. Compte Administrateur',
        'admin_name' => 'Nom d\'affichage',
        'admin_email' => 'Email Administrateur',
        'admin_pass' => 'Mot de passe (min. 8 caractères)',
        'submit' => 'Installer et initialiser Vanill-IA Chat',
    ],
    'en' => [
        'title' => 'Setup — Vanill-IA Chat',
        'subtitle' => 'Installation & Initialization Wizard',
        'already' => 'Vanill-IA Chat is already installed. Delete storage/installed.lock on the server (or sign in as an administrator with ?force_reset=1).',
        'err_email' => 'Please enter a valid administrator email address.',
        'err_pass' => 'Administrator password must be at least 8 characters long.',
        'err_db' => 'Please provide the database name.',
        'success_title' => 'Installation completed successfully!',
        'success_desc' => 'The database, tables (RAG, Settings, RBAC, Preferences), and your Administrator account are ready.',
        'success_btn' => 'Go to Sign In',
        'sec_db' => '1. MySQL / MariaDB Server',
        'db_host' => 'MySQL Host',
        'db_port' => 'Port',
        'db_name' => 'Database Name',
        'db_user' => 'MySQL User',
        'db_pass' => 'MySQL Password',
        'reset_db' => 'Reset all existing tables (DROP & RECREATE — Fresh install)',
        'sec_admin' => '2. Administrator Account',
        'admin_name' => 'Display Name',
        'admin_email' => 'Administrator Email',
        'admin_pass' => 'Password (min. 8 characters)',
        'submit' => 'Install & Initialize Vanill-IA Chat',
    ],
];
$T = $texts[$lang];

$rootDir    = dirname(__DIR__);
$storageDir = $rootDir . '/storage';
$lockFile   = $storageDir . '/installed.lock';
$envFile    = $rootDir . '/.env';

$isAdminSession = ($_SESSION['role'] ?? '') === 'admin';

if (file_exists($lockFile) && (!$isAdminSession || !isset($_GET['force_reset']))) {
    http_response_code(403);
    die($T['already']);
}

$error = '';
$step  = 'form';

$dbHost     = trim($_POST['db_host'] ?? '127.0.0.1');
$dbPort     = trim($_POST['db_port'] ?? '3306');
$dbName     = trim($_POST['db_name'] ?? 'vanillia_chat_db');
$dbUser     = trim($_POST['db_user'] ?? 'root');
$dbPass     = $_POST['db_pass'] ?? '';
$adminName  = trim($_POST['admin_name'] ?? 'Admin');
$adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
$resetDb    = !empty($_POST['reset_db']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminPass = $_POST['admin_pass'] ?? '';

    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = $T['err_email'];
    } elseif (strlen($adminPass) < 8) {
        $error = $T['err_pass'];
    } elseif (empty($dbName)) {
        $error = $T['err_db'];
    } else {
        try {
            foreach (['keys', 'uploads', 'logs', 'knowledge'] as $subDir) {
                $dirPath = $storageDir . '/' . $subDir;
                if (!is_dir($dirPath) && !@mkdir($dirPath, 0750, true)) {
                    throw new RuntimeException("Impossible de créer le dossier " . $dirPath . ". Vérifiez les permissions.");
                }
            }

            if (!is_writable($rootDir)) {
                throw new RuntimeException("Le dossier racine (" . $rootDir . ") n'est pas accessible en écriture pour générer le fichier .env.");
            }

            $dsn = "mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            if ($resetDb) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (['usage_logs', 'user_memories', 'attachments', 'messages', 'conversations', 'knowledge_docs', 'app_settings', 'ai_models', 'users'] as $tbl) {
                    $pdo->exec("DROP TABLE IF EXISTS `" . $tbl . "`");
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            $appSecret = bin2hex(random_bytes(32));
            $isHttps   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $protocol  = $isHttps ? 'https' : 'http';
            $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $appUrl    = $protocol . "://" . $host;

            $escapedDbPass = addcslashes($dbPass, '"\\');
            $keyPath       = str_replace('\\', '/', $storageDir) . '/keys/gcp-service-account.json';

            $envLines = [
                'APP_NAME="Vanill-IA Chat"',
                'APP_ENV="production"',
                'APP_DEFAULT_LANG="' . $lang . '"',
                'APP_URL="' . $appUrl . '"',
                'APP_SECRET="' . $appSecret . '"',
                '',
                '# Base de donnees MySQL',
                'DB_HOST="' . $dbHost . '"',
                'DB_PORT="' . $dbPort . '"',
                'DB_NAME="' . $dbName . '"',
                'DB_USER="' . $dbUser . '"',
                'DB_PASS="' . $escapedDbPass . '"',
                'DB_CHARSET="utf8mb4"',
                '',
                '# Quotas par defaut (1 000 000 credits = 1.00 EUR)',
                'DEFAULT_START_CREDITS=5000000',
                'DEFAULT_MONTHLY_CREDITS=5000000',
                '',
                '# Microsoft Entra ID (OIDC SSO)',
                'ENTRA_ENABLED=false',
                'ENTRA_TENANT_ID=""',
                'ENTRA_CLIENT_ID=""',
                'ENTRA_CLIENT_SECRET=""',
                'ENTRA_REDIRECT_URI="' . $appUrl . '/auth/callback"',
                '',
                '# Fournisseurs IA',
                'VERTEX_PROJECT_ID=""',
                'VERTEX_REGION="europe-west1"',
                'VERTEX_KEY_PATH="' . $keyPath . '"',
                'GEMINI_API_KEY=""',
                'ANTHROPIC_API_KEY=""',
                'OPENAI_API_KEY=""',
            ];

            if (file_put_contents($envFile, implode("\n", $envLines) . "\n") === false) {
                throw new RuntimeException("Impossible d'écrire le fichier .env à la racine du projet.");
            }

            $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                email VARCHAR(191) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NULL,
                entra_oid VARCHAR(100) NULL UNIQUE,
                display_name VARCHAR(100) NOT NULL,
                role ENUM('user', 'admin') DEFAULT 'user',
                is_active TINYINT(1) DEFAULT 1,
                credits_balance BIGINT DEFAULT 5000000,
                credits_monthly_limit BIGINT DEFAULT 5000000,
                auto_refill TINYINT(1) DEFAULT 1,
                last_refill_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                auto_memory_enabled TINYINT(1) DEFAULT 1,
                default_model_id INT UNSIGNED NULL,
                preferred_lang VARCHAR(5) NOT NULL DEFAULT 'fr',
                preferred_theme VARCHAR(10) NOT NULL DEFAULT 'dark',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ai_models (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider ENUM('google_ai', 'vertex_gemini', 'anthropic', 'openai') NOT NULL DEFAULT 'google_ai',
                model_identifier VARCHAR(100) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                min_role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                is_enabled TINYINT(1) DEFAULT 1,
                is_default TINYINT(1) DEFAULT 0,
                supports_vision TINYINT(1) DEFAULT 1,
                supports_documents TINYINT(1) DEFAULT 1,
                cost_input_per_1m BIGINT NOT NULL DEFAULT 150000,
                cost_output_per_1m BIGINT NOT NULL DEFAULT 600000,
                max_context_tokens INT UNSIGNED DEFAULT 128000,
                max_output_tokens INT UNSIGNED DEFAULT 8192,
                config_json TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value LONGTEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS knowledge_docs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                content_text LONGTEXT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS conversations (
                id CHAR(36) PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) DEFAULT 'Nouvelle conversation',
                model_id INT UNSIGNED NOT NULL,
                system_prompt TEXT NULL,
                is_pinned TINYINT(1) DEFAULT 0,
                is_archived TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id CHAR(36) NOT NULL,
                role ENUM('system', 'user', 'assistant') NOT NULL,
                content LONGTEXT NOT NULL,
                model_id INT UNSIGNED NULL,
                tokens_input INT UNSIGNED DEFAULT 0,
                tokens_output INT UNSIGNED DEFAULT 0,
                credits_deducted BIGINT UNSIGNED DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS attachments (
                id CHAR(36) PRIMARY KEY,
                message_id BIGINT UNSIGNED NULL,
                user_id INT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                extracted_text LONGTEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS user_memories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                memory_key VARCHAR(100) NULL,
                content TEXT NOT NULL,
                source ENUM('manual', 'auto') DEFAULT 'manual',
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS usage_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                model_id INT UNSIGNED NOT NULL,
                conversation_id CHAR(36) NULL,
                tokens_input INT UNSIGNED NOT NULL,
                tokens_output INT UNSIGNED NOT NULL,
                provider_cost_raw BIGINT NOT NULL,
                client_credits_billed BIGINT NOT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

            $pdo->exec($sql);

            $modelCount = (int)$pdo->query("SELECT COUNT(*) FROM ai_models")->fetchColumn();
            if ($modelCount === 0) {
                $pdo->exec(<<<'SQL'
                INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m) VALUES
                    ('google_ai', 'gemini-2.5-flash', 'Gemini 2.5 Flash (AI Studio)', 'user', 1, 1, 150000, 600000),
                    ('google_ai', 'gemini-2.0-flash', 'Gemini 2.0 Flash (AI Studio)', 'user', 1, 0, 100000, 400000),
                    ('google_ai', 'gemini-2.5-pro', 'Gemini 2.5 Pro (AI Studio)', 'admin', 1, 0, 1250000, 10000000),
                    ('anthropic', 'claude-3-7-sonnet-latest', 'Claude 3.7 Sonnet', 'admin', 1, 0, 3000000, 15000000),
                    ('openai', 'gpt-4o', 'ChatGPT (GPT-4o)', 'user', 1, 0, 2500000, 10000000)
SQL
                );
            }

            $defaultPrompt = $lang === 'en'
                ? "You are the enterprise AI assistant of Vanill-IA Chat. Answer accurately, clearly, and in a well-structured manner."
                : "Tu es l'assistant IA professionnel de l'entreprise sur Vanill-IA Chat. Réponds de manière précise, structurée et fiable en français.";
            $stmtSetting = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('global_system_prompt', ?)");
            $stmtSetting->execute([$defaultPrompt]);

            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff), random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
            );

            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $optionsHash = ($algo === PASSWORD_ARGON2ID)
                ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
                : [];
            $passwordHash = password_hash($adminPass, $algo, $optionsHash);

            $stmt = $pdo->prepare(<<<'SQL'
                INSERT INTO users (uuid, email, password_hash, display_name, role, credits_balance, credits_monthly_limit, preferred_lang, preferred_theme)
                VALUES (?, ?, ?, ?, 'admin', 50000000, 50000000, ?, ?)
                ON DUPLICATE KEY UPDATE
                    password_hash = VALUES(password_hash),
                    display_name = VALUES(display_name),
                    role = 'admin',
                    preferred_lang = VALUES(preferred_lang),
                    preferred_theme = VALUES(preferred_theme)
SQL
            );
            $stmt->execute([$uuid, $adminEmail, $passwordHash, $adminName, $lang, $theme]);

            file_put_contents($lockFile, date('c'));
            $step = 'success';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($T['title']) ?></title>
    <script>
        (function () {
            const saved = localStorage.getItem('vanillia_theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', saved);
            }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root[data-bs-theme="dark"] {
            --app-bg: #121316;
            --card-bg: #1c1e24;
            --card-border: rgba(255, 255, 255, 0.08);
        }
        :root[data-bs-theme="light"] {
            --app-bg: #f4f6f9;
            --card-bg: #ffffff;
            --card-border: rgba(15, 23, 42, 0.10);
        }
        body { background-color: var(--app-bg); font-family: system-ui, -apple-system, sans-serif; }
        .setup-card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; max-width: 580px; width: 100%; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-4 px-3 position-relative">
    <div class="position-absolute top-0 end-0 p-3 d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm">
            <a href="?lang=fr<?= isset($_GET['force_reset']) ? '&force_reset=1' : '' ?>" class="btn <?= $lang === 'fr' ? 'btn-primary' : 'btn-outline-secondary' ?>">FR</a>
            <a href="?lang=en<?= isset($_GET['force_reset']) ? '&force_reset=1' : '' ?>" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?>">EN</a>
        </div>
        <button type="button" id="themeToggleBtn" class="btn btn-sm btn-outline-secondary" onclick="toggleInstallTheme()">
            <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?>"></i>
        </button>
    </div>

    <div class="setup-card p-4 p-md-5 shadow-lg">
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1">Vanill-<span class="text-primary">IA</span> <span class="fw-normal text-body-secondary">Chat</span></h3>
            <p class="text-body-secondary small mb-0"><?= htmlspecialchars($T['subtitle']) ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'success'): ?>
            <div class="alert alert-success py-3 small mb-4 text-center">
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($T['success_title']) ?></h6>
                <?= htmlspecialchars($T['success_desc']) ?>
            </div>
            <a href="/login" class="btn btn-primary w-100 fw-semibold py-2"><?= htmlspecialchars($T['success_btn']) ?></a>
        <?php else: ?>
            <form method="POST" autocomplete="off">
                <h6 class="fw-bold mb-3 pb-2 border-bottom border-secondary border-opacity-25"><?= htmlspecialchars($T['sec_db']) ?></h6>
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['db_host']) ?></label>
                        <input type="text" name="db_host" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($dbHost) ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['db_port']) ?></label>
                        <input type="text" name="db_port" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($dbPort) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['db_name']) ?></label>
                        <input type="text" name="db_name" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($dbName) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['db_user']) ?></label>
                        <input type="text" name="db_user" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($dbUser) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['db_pass']) ?></label>
                        <input type="password" name="db_pass" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($dbPass) ?>">
                    </div>
                    <div class="col-12 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reset_db" value="1" id="resetDbCheck">
                            <label class="form-check-label small text-warning" for="resetDbCheck">
                                <?= htmlspecialchars($T['reset_db']) ?>
                            </label>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 pt-2 pb-2 border-bottom border-secondary border-opacity-25"><?= htmlspecialchars($T['sec_admin']) ?></h6>
                <div class="row g-2 mb-4">
                    <div class="col-12">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['admin_name']) ?></label>
                        <input type="text" name="admin_name" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" value="<?= htmlspecialchars($adminName) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['admin_email']) ?></label>
                        <input type="email" name="admin_email" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" placeholder="admin@domain.com" value="<?= htmlspecialchars($adminEmail) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-body-secondary"><?= htmlspecialchars($T['admin_pass']) ?></label>
                        <input type="password" name="admin_pass" class="form-control form-control-sm bg-body-tertiary border-secondary border-opacity-25" required minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                    <?= htmlspecialchars($T['submit']) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function toggleInstallTheme() {
            const current = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('vanillia_theme', next);
            document.cookie = `vanillia_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
            const icon = document.querySelector('#themeToggleBtn i');
            if (icon) icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
    </script>
</body>
</html>
