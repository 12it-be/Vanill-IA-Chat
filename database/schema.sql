-- Vanill-IA Chat — Full MySQL / MariaDB Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.6+ (utf8mb4)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;

-- Default starter models & settings (safe on re-import)
INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m)
SELECT 'google_ai', 'gemini-2.5-flash', 'Gemini 2.5 Flash (AI Studio)', 'user', 1, 1, 150000, 600000
WHERE NOT EXISTS (SELECT 1 FROM ai_models);

INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m)
SELECT 'google_ai', 'gemini-2.0-flash', 'Gemini 2.0 Flash (AI Studio)', 'user', 1, 0, 100000, 400000
WHERE (SELECT COUNT(*) FROM ai_models) = 1;

INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m)
SELECT 'google_ai', 'gemini-2.5-pro', 'Gemini 2.5 Pro (AI Studio)', 'admin', 1, 0, 1250000, 10000000
WHERE (SELECT COUNT(*) FROM ai_models) = 2;

INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m)
SELECT 'anthropic', 'claude-3-7-sonnet-latest', 'Claude 3.7 Sonnet', 'admin', 1, 0, 3000000, 15000000
WHERE (SELECT COUNT(*) FROM ai_models) = 3;

INSERT INTO ai_models (provider, model_identifier, display_name, min_role, is_enabled, is_default, cost_input_per_1m, cost_output_per_1m)
SELECT 'openai', 'gpt-4o', 'ChatGPT (GPT-4o)', 'user', 1, 0, 2500000, 10000000
WHERE (SELECT COUNT(*) FROM ai_models) = 4;

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('global_system_prompt', 'Tu es l''assistant IA professionnel de l''entreprise sur Vanill-IA Chat. Réponds de manière précise, structurée et fiable.');
