# Vanill-IA Chat

> **Lightweight, dependency-free Enterprise AI Chat Workspace** built with native PHP 8.2+, MySQL/MariaDB, Bootstrap 5.3, and Vanilla ES6+ JavaScript.

🌐 **Official Cloud Instance:** [https://chat.vanill-ia.com](https://chat.vanill-ia.com)  
🇫🇷 **Documentation en français :** [README.fr.md](./README.fr.md)


<img width="1896" height="1077" alt="image" src="https://github.com/user-attachments/assets/c6773716-397d-4507-9abc-58cf5ca39a09" />

---

## ✨ Key Features

- **Zero Composer / Zero Node.js Build Step**: Runs directly on standard shared hosting (Infomaniak, Apache, Nginx, cPanel, Plesk) or VPS with PHP 8.2+ and MySQL/MariaDB.
- **Multi-Provider LLM Streaming (SSE)**:
  - **Google AI Studio** (`google_ai`)
  - **Google Cloud Vertex AI** (`vertex_gemini` with EU regional endpoints like `europe-west1` and Service Account JWT OAuth2)
  - **Anthropic Claude** (`anthropic`)
  - **OpenAI GPT** (`openai`)
- **Bilingual Interface (EN / FR)** & **Dark / Light Theme Switcher** persisted per user (Cookie + LocalStorage + DB).
- **Enterprise Cost & Quota Control**:
  - Real-time token billing (Input / Output EUR per 1M tokens).
  - Monthly user credit limits with automatic 30-day refill (`auto_refill`) and instant manual reset.
  - Personal consumption dashboard for users & full 30-day breakdown + CSV export for admins.
- **RBAC (Role-Based Access Control)**: Restrict expensive or sensitive models to `admin` users while keeping fast models available to `user` accounts.
- **Microsoft Entra ID (Azure AD) SSO**: Optional OIDC authentication alongside Argon2id local accounts.
- **Built-in Memory Engine & Shared RAG**:
  - Automatic & manual per-user memory injection (`<memory_save>`).
  - Shared company Knowledge Base (`.docx`, `.xlsx`, `.txt`, `.md`, `.csv`, or pasted text).
- **Multimodal Attachments**: Upload images (`jpeg`, `png`, `webp`), PDFs, Word (`.docx`), Excel (`.xlsx`), CSV, and source code directly into the chat (drag & drop supported).
- **Encrypted API Keys at Rest**: Model-specific API keys stored in MySQL are encrypted using `AES-256-GCM` derived from `APP_SECRET`.

---

## 📋 Server Requirements

- **PHP**: `8.2` or higher (`8.3` / `8.4` recommended)
- **PHP Extensions**: `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `zip`, `mbstring`, `json`
- **Database**: MySQL `8.0+` or MariaDB `10.6+`
- **Web Server**: Apache (`mod_rewrite` enabled) or Nginx

---

## 🚀 Installation Guide

### Option 1: Web Setup Wizard (Recommended)

1. **Clone or download** this repository onto your server:
   ```bash
   git clone https://github.com/YOUR_ORG/vanill-ia-chat.git
   ```
2. **Configure your web root (DocumentRoot)** to point strictly to the `public/` directory (never expose the root folder or `storage/` directly!).
   - *Infomaniak / Apache shared hosting*: Set the site directory to `/path/to/vanill-ia-chat/public`.
3. **Ensure write permissions** on the root folder (only during setup to create `.env`) and `storage/`:
   ```bash
   chmod -R 750 storage/
   ```
4. **Create an empty MySQL/MariaDB database** (`utf8mb4_unicode_ci`).
5. **Open the setup wizard** in your browser:
   ```
   https://your-domain.com/install
   ```
6. Fill in your MySQL credentials and Administrator account details, then submit.
   - The wizard creates `.env`, initializes all tables, seeds starter models, creates your Admin user, and locks itself via `storage/installed.lock`.
7. **Sign in** at `/login` and visit `/admin` to configure your LLM API keys.

---

### Option 2: Manual CLI / Production Installation

1. Copy `.env.example` to `.env` and generate a 64-char hex `APP_SECRET`:
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
2. Edit `.env` with your `APP_URL`, `DB_*` credentials, and optional API keys.
3. Import the database schema:
   ```bash
   mysql -u your_user -p your_database < database/schema.sql
   ```
4. Create `storage/installed.lock` to disable `/install`:
   ```bash
   date -Iseconds > storage/installed.lock
   ```
5. Create an initial admin user via SQL or temporarily run `/install` once.

---

## 🌐 Web Server Configuration

### Apache
A `.htaccess` file is already included in `public/.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```
Make sure `AllowOverride All` is enabled for `public/`.

### Nginx
Because Vanill-IA Chat uses **Server-Sent Events (SSE)** for real-time AI streaming, disable proxy/fastcgi buffering:

```nginx
server {
    listen 443 ssl http2;
    server_name chat.vanill-ia.com;
    root /var/www/vanill-ia-chat/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_buffering off;
        fastcgi_read_timeout 180s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## ⚙️ Configuration (`.env`)

| Variable | Description |
| :--- | :--- |
| `APP_NAME` | Application name (`Vanill-IA Chat`) |
| `APP_ENV` | `production` or `local` |
| `APP_DEFAULT_LANG` | Default UI language (`fr` or `en`) |
| `APP_URL` | Public URL without trailing slash (e.g. `https://chat.vanill-ia.com`) |
| `APP_SECRET` | 64-char hex secret used for `AES-256-GCM` encryption of DB API keys |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL / MariaDB connection |
| `DEFAULT_START_CREDITS` | Initial credits for new Entra ID users (`1000000` = `1.00 EUR`) |
| `DEFAULT_MONTHLY_CREDITS` | Monthly refill cap (`5000000` = `5.00 EUR`) |
| `ENTRA_*` | Microsoft Entra ID OIDC SSO parameters |
| `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY` | Fallback provider keys if not overridden per model in `/admin` |
| `VERTEX_PROJECT_ID`, `VERTEX_REGION`, `VERTEX_KEY_PATH` | Google Cloud Vertex AI service account settings |

---

## 🔐 Microsoft Entra ID (SSO) Setup

1. In Azure Portal → **Microsoft Entra ID** → **App registrations** → **New registration**.
2. Add a **Web** Redirect URI:
   ```
   https://your-domain.com/auth/callback
   ```
3. Under **Certificates & secrets**, create a Client Secret.
4. Set `ENTRA_ENABLED=true`, `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET`, and `ENTRA_REDIRECT_URI` in `.env`.
5. First-time Microsoft logins automatically provision a `user` account with default monthly credits.

---

## 🔄 Upgrading an Existing Installation

1. Back up your `.env`, `storage/`, and MySQL database.
2. Replace `public/` and `src/` files with the new release (do **not** overwrite `.env` or `storage/`).
3. Schema upgrades run automatically via `VanillIA\Core\Migrator::ensureSchema()`, or you can manually apply SQL files from `database/migrations/`.

---

## 📁 Project Structure

```text
├── database/
│   ├── schema.sql                  # Full MySQL schema
│   └── migrations/                 # Incremental SQL migrations
├── public/                         # Web DocumentRoot
│   ├── .htaccess
│   ├── index.php                   # Front controller & router
│   └── install.php                 # Setup wizard
├── src/
│   ├── Controllers/                # Auth, Chat, Admin, File, Memory
│   ├── Core/                       # Database, Env, I18n, Migrator, Router, Security
│   ├── Lang/                       # en.php, fr.php translations
│   ├── Models/                     # User model & monthly quota refill
│   ├── Providers/                  # VertexGemini, Anthropic, OpenAI SSE drivers
│   ├── Services/                   # FileProcessorService, MemoryService (RAG)
│   └── Views/                      # login.php, chat.php, admin.php
├── storage/                        # Private (outside DocumentRoot)
│   ├── keys/                       # GCP Service Account JSON
│   ├── knowledge/
│   ├── logs/
│   └── uploads/
├── .env.example
├── .gitignore
└── LICENSE
```

---

## 🛡️ Security & GDPR Notes (Self-Hosted)

- Keep `storage/` and `.env` strictly outside `public/` (DocumentRoot).
- Never commit `.env`, `storage/installed.lock`, or `storage/keys/*.json` to Git.
- For strict **EU GDPR** compliance, prefer **Google Cloud Vertex AI** (`europe-west1` / EU data residency) or enterprise zero-data-retention API tiers over free consumer endpoints.

---

## 📄 License

Distributed under the MIT License. See [`LICENSE`](./LICENSE) for more information.
