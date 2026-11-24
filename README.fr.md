# Vanill-IA Chat

> **Espace de chat IA d'entreprise léger et sans dépendances**, conçu en PHP 8.2+ natif, MySQL/MariaDB, Bootstrap 5.3 et Vanilla JS ES6+.

🌐 **Instance Cloud officielle :** [https://chat.vanill-ia.com](https://chat.vanill-ia.com)  
🇬🇧 **English documentation:** [README.md](./README.md)

---

## ✨ Fonctionnalités principales

- **Zéro Composer / Zéro Node.js** : Déployable directement par FTP/Git sur hébergement mutualisé (Infomaniak, Apache, Nginx, Plesk) ou VPS avec PHP 8.2+ et MySQL/MariaDB.
- **Streaming SSE Multi-Fournisseurs** :
  - **Google AI Studio** (`google_ai`)
  - **Google Cloud Vertex AI** (`vertex_gemini`, avec régions UE comme `europe-west1` et JWT OAuth2 via Service Account)
  - **Anthropic Claude** (`anthropic`)
  - **OpenAI GPT** (`openai`)
- **Interface Bilingue (FR / EN)** & **Thème Sombre / Clair** persistés par utilisateur (Cookie + LocalStorage + Base de données).
- **Contrôle des Coûts & Quotas** :
  - Facturation temps réel aux tokens (Input / Output en EUR / 1M tokens).
  - Plafonds mensuels par utilisateur avec rechargement automatique tous les 30 jours (`auto_refill`) et reset manuel instantané.
  - Suivi de consommation détaillé côté utilisateur et cockpit administrateur avec export CSV.
- **RBAC (Contrôle d'accès par rôle)** : Réservez certains modèles coûteux aux administrateurs (`admin`) et gardez des modèles rapides pour tous (`user`).
- **SSO Microsoft Entra ID (Azure AD)** : Connexion OIDC en 1 clic en plus des comptes locaux (hachage Argon2id).
- **Mémoire IA & Base Documentaire RAG** :
  - Sauvegarde automatique et manuelle des souvenirs utilisateur (`<memory_save>`).
  - Base documentaire partagée d'entreprise (`.docx`, `.xlsx`, `.txt`, `.md`, `.csv` ou texte collé).
- **Pièces jointes multimodales** : Images (`jpeg`, `png`, `webp`), PDF, Word (`.docx`), Excel (`.xlsx`), CSV et fichiers de code en glisser-déposer.
- **Chiffrement des clés API** : Les clés configurées dans l'Admin sont chiffrées en base avec `AES-256-GCM` via `APP_SECRET`.

---

## 📋 Prérequis serveur

- **PHP** : `8.2` ou supérieur (`8.3` / `8.4` recommandé)
- **Extensions PHP** : `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `zip`, `mbstring`, `json`
- **Base de données** : MySQL `8.0+` ou MariaDB `10.6+`
- **Serveur Web** : Apache (`mod_rewrite` activé) ou Nginx

---

## 🚀 Guide d'installation

### Méthode 1 : Assistant Web (Recommandé)

1. **Téléchargez ou clonez** le dépôt sur votre serveur :
   ```bash
   git clone https://github.com/YOUR_ORG/vanill-ia-chat.git
   ```
2. **Faites pointer la racine Web (DocumentRoot)** uniquement vers le dossier `public/` (ne jamais exposer la racine du projet ni `storage/`).
   - *Infomaniak / Mutualisé Apache* : définissez le répertoire du site sur `/chemin/vers/vanill-ia-chat/public`.
3. **Vérifiez les droits d'écriture** sur la racine (le temps de créer `.env`) et sur `storage/` :
   ```bash
   chmod -R 750 storage/
   ```
4. **Créez une base de données MySQL/MariaDB vide** (`utf8mb4_unicode_ci`).
5. **Ouvrez l'assistant** dans votre navigateur :
   ```
   https://votre-domaine.com/install
   ```
6. Saisissez les identifiants MySQL et le premier compte Administrateur puis validez.
   - L'assistant génère `.env`, crée les tables, ajoute les modèles initiaux, crée l'administrateur et verrouille l'installeur via `storage/installed.lock`.
7. **Connectez-vous** sur `/login` puis rendez-vous dans `/admin` pour renseigner vos clés API IA.

---

### Méthode 2 : Installation manuelle CLI

1. Copiez `.env.example` vers `.env` et générez une clé `APP_SECRET` (64 caractères hex) :
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
2. Renseignez `APP_URL`, `DB_*` et vos clés API dans `.env`.
3. Importez le schéma SQL :
   ```bash
   mysql -u utilisateur -p nom_base < database/schema.sql
   ```
4. Verrouillez l'installeur :
   ```bash
   date -Iseconds > storage/installed.lock
   ```

---

## 🌐 Configuration Apache & Nginx

### Apache
Le fichier `public/.htaccess` est déjà fourni. Assurez-vous simplement que `mod_rewrite` et `AllowOverride All` soient actifs.

### Nginx
Le streaming IA utilise **Server-Sent Events (SSE)** : désactivez impérativement la mise en tampon (`fastcgi_buffering off;`) :

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

## 🔄 Mettre à jour une instance existante

1. Sauvegardez votre `.env`, `storage/` et votre base MySQL.
2. Remplacez les dossiers `public/` et `src/` (sans écraser `.env` ni `storage/`).
3. Les évolutions de structure SQL sont appliquées automatiquement via `VanillIA\Core\Migrator::ensureSchema()` (ou manuellement via `database/migrations/`).

---

## 🛡️ Sécurité & RGPD (Auto-hébergement)

- Ne placez jamais `.env` ou `storage/` dans le répertoire public web.
- Ne commitez jamais `.env`, `storage/installed.lock` ni `storage/keys/*.json`.
- Pour un usage **RGPD strict en Europe**, privilégiez **Google Cloud Vertex AI** (`europe-west1`) ou des comptes API professionnels avec politique *Zero Data Retention*.

---

## 📄 Licence

Distribué sous licence MIT. Voir [`LICENSE`](./LICENSE).
