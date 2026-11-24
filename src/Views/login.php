<?php
use VanillIA\Core\I18n;
$lang = $lang ?? I18n::getLang();
$theme = $theme ?? I18n::getTheme();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(I18n::t('login_title')) ?></title>
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
        body { background-color: var(--app-bg); height: 100dvh; transition: background-color 0.2s ease; }
        .login-card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; max-width: 410px; width: 100%; }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center p-3 position-relative">
    <div class="position-absolute top-0 end-0 p-3 d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Language">
            <button type="button" class="btn <?= $lang === 'fr' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('fr')">FR</button>
            <button type="button" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('en')">EN</button>
        </div>
        <button type="button" id="themeToggleBtn" class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()" title="<?= htmlspecialchars(I18n::t('theme_toggle_title')) ?>">
            <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?>"></i>
        </button>
    </div>

    <div class="login-card p-4 shadow-lg">
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1">Vanill-<span class="text-primary">IA</span> <span class="fw-normal text-body-secondary">Chat</span></h3>
            <p class="text-body-secondary small mb-0"><?= htmlspecialchars(I18n::t('login_subtitle')) ?></p>
        </div>

        <?php if (!empty($entraEnabled)): ?>
            <a href="/auth/entra" class="btn btn-outline-primary w-100 fw-semibold py-2 mb-3 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-microsoft"></i> <?= htmlspecialchars(I18n::t('login_entra_btn')) ?>
            </a>
            <div class="d-flex align-items-center my-3">
                <hr class="flex-grow-1 border-secondary border-opacity-25">
                <span class="px-2 text-body-secondary small"><?= htmlspecialchars(I18n::t('login_or_local')) ?></span>
                <hr class="flex-grow-1 border-secondary border-opacity-25">
            </div>
        <?php endif; ?>

        <div id="loginError" class="alert alert-danger py-2 small d-none"></div>

        <form id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="mb-3">
                <label class="form-label small text-body-secondary"><?= htmlspecialchars(I18n::t('login_email')) ?></label>
                <input type="email" name="email" class="form-control bg-body-tertiary border-secondary border-opacity-25" required>
            </div>
            <div class="mb-4">
                <label class="form-label small text-body-secondary"><?= htmlspecialchars(I18n::t('login_password')) ?></label>
                <input type="password" name="password" class="form-control bg-body-tertiary border-secondary border-opacity-25" required>
            </div>
            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 fw-semibold py-2"><?= htmlspecialchars(I18n::t('login_submit')) ?></button>
        </form>
    </div>

    <script>
        function syncThemeIcon(theme) {
            const icon = document.querySelector('#themeToggleBtn i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
        }
        syncThemeIcon(document.documentElement.getAttribute('data-bs-theme') || 'dark');

        async function toggleTheme() {
            const current = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('vanillia_theme', next);
            document.cookie = `vanillia_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
            syncThemeIcon(next);
            try {
                await fetch('/api/preferences', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: next })
                });
            } catch (e) {}
        }

        async function switchLanguage(lang) {
            document.cookie = `vanillia_lang=${lang}; path=/; max-age=31536000; SameSite=Lax`;
            try {
                await fetch('/api/preferences', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lang })
                });
            } catch (e) {}
            window.location.reload();
        }

        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const errBox = document.getElementById('loginError');
            const btn = document.getElementById('btnSubmit');
            errBox.classList.add('d-none');
            btn.disabled = true;

            const formData = Object.fromEntries(new FormData(e.target));
            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const rawText = await res.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    throw new Error(rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300) || <?= json_encode(I18n::t('login_error_default')) ?>);
                }
                if (!res.ok) throw new Error(data.error || <?= json_encode(I18n::t('login_error_default')) ?>);
                window.location.href = data.redirect;
            } catch (err) {
                errBox.textContent = err.message;
                errBox.classList.remove('d-none');
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
