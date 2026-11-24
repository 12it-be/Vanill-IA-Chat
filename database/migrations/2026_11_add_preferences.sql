-- Migration script for existing Vanill-IA -> Vanill-IA Chat installations
-- Note: Vanill-IA Chat also runs Migrator::ensureSchema() automatically at runtime.

ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER system_prompt;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS preferred_lang VARCHAR(5) NOT NULL DEFAULT 'fr' AFTER default_model_id,
    ADD COLUMN IF NOT EXISTS preferred_theme VARCHAR(10) NOT NULL DEFAULT 'dark' AFTER preferred_lang;
