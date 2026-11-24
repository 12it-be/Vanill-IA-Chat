<?php

namespace VanillIA\Controllers;

use Throwable;
use VanillIA\Core\Database;
use VanillIA\Core\Env;
use VanillIA\Core\I18n;
use VanillIA\Core\Migrator;
use VanillIA\Core\Security;
use VanillIA\Models\User;

class AuthController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        $lang = I18n::init();
        $theme = I18n::getTheme();
        $csrfToken = Security::generateCsrf();
        $entraEnabled = Env::get('ENTRA_ENABLED', 'false') === 'true';
        require __DIR__ . '/../Views/login.php';
    }

    public function login(): void
    {
        header('Content-Type: application/json');
        I18n::init();

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            if (!Security::validateCsrf($input['csrf_token'] ?? null)) {
                http_response_code(403);
                echo json_encode(['error' => I18n::t('login_error_csrf')]);
                return;
            }

            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            $user = User::findByEmail($email);

            if (!$user || empty($user['password_hash']) || !Security::verifyPassword($password, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['error' => I18n::t('login_error_invalid')]);
                return;
            }

            if ((int)$user['is_active'] !== 1) {
                http_response_code(403);
                echo json_encode(['error' => I18n::t('login_error_suspended')]);
                return;
            }

            if (Security::needsRehash($user['password_hash'])) {
                $newHash = Security::hashPassword($password);
                $db = Database::getInstance();
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];

            if (!empty($user['preferred_lang']) && empty($_COOKIE['vanillia_lang'])) {
                I18n::setLang($user['preferred_lang']);
            }
            if (!empty($user['preferred_theme']) && empty($_COOKIE['vanillia_theme'])) {
                I18n::setThemeCookie($user['preferred_theme']);
            }

            echo json_encode(['success' => true, 'redirect' => '/']);
        } catch (Throwable $e) {
            error_log('[Login Error] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function updatePreferences(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $lang = isset($input['lang']) && in_array($input['lang'], I18n::getSupported(), true)
            ? $input['lang']
            : null;
        $theme = isset($input['theme']) && in_array($input['theme'], ['dark', 'light'], true)
            ? $input['theme']
            : null;

        if ($lang !== null) {
            I18n::setLang($lang);
        }
        if ($theme !== null) {
            I18n::setThemeCookie($theme);
        }

        if (!empty($_SESSION['user_id'])) {
            try {
                $db = Database::getInstance();
                Migrator::ensureSchema($db);
                if ($lang !== null) {
                    $db->prepare("UPDATE users SET preferred_lang = ? WHERE id = ?")->execute([$lang, (int)$_SESSION['user_id']]);
                }
                if ($theme !== null) {
                    $db->prepare("UPDATE users SET preferred_theme = ? WHERE id = ?")->execute([$theme, (int)$_SESSION['user_id']]);
                }
            } catch (Throwable $e) {
                // Non bloquant
            }
        }

        echo json_encode([
            'success' => true,
            'lang'    => $lang ?? I18n::getLang(),
            'theme'   => $theme ?? I18n::getTheme(),
        ]);
    }

    public function switchLang(array $params): void
    {
        $lang = strtolower((string)($params['lang'] ?? 'fr'));
        $applied = I18n::setLang($lang);

        if (!empty($_SESSION['user_id'])) {
            try {
                $db = Database::getInstance();
                Migrator::ensureSchema($db);
                $db->prepare("UPDATE users SET preferred_lang = ? WHERE id = ?")->execute([$applied, (int)$_SESSION['user_id']]);
            } catch (Throwable $e) {
                // Non bloquant
            }
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $path = parse_url($referer, PHP_URL_PATH) ?: '/';
        header('Location: ' . $path);
        exit;
    }

    public function redirectToEntra(): void
    {
        if (Env::get('ENTRA_ENABLED') !== 'true') {
            header('Location: /login');
            exit;
        }

        $tenantId = Env::get('ENTRA_TENANT_ID');
        $clientId = Env::get('ENTRA_CLIENT_ID');
        $redirectUri = Env::get('ENTRA_REDIRECT_URI');
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'response_mode' => 'query',
            'scope'         => 'openid profile email User.Read',
            'state'         => $state,
        ]);

        header("Location: https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?{$params}");
        exit;
    }

    public function handleEntraCallback(): void
    {
        $state = $_GET['state'] ?? '';
        $code  = $_GET['code'] ?? '';

        if (empty($code) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
            die('Erreur de validation OAuth2 State.');
        }
        unset($_SESSION['oauth_state']);

        $tenantId     = Env::get('ENTRA_TENANT_ID');
        $clientId     = Env::get('ENTRA_CLIENT_ID');
        $clientSecret = Env::get('ENTRA_CLIENT_SECRET');
        $redirectUri  = Env::get('ENTRA_REDIRECT_URI');

        $ch = curl_init("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $clientId,
                'scope'         => 'openid profile email User.Read',
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
                'client_secret' => $clientSecret,
            ]),
        ]);

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($response['id_token'])) {
            die('Échec de l\'authentification Microsoft Entra ID.');
        }

        $jwtParts = explode('.', $response['id_token']);
        $claims = json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true);

        $oid   = $claims['oid'] ?? $claims['sub'] ?? '';
        $email = $claims['preferred_username'] ?? $claims['email'] ?? '';
        $name  = $claims['name'] ?? explode('@', $email)[0];

        if (empty($oid) || empty($email)) {
            die('Impossible de récupérer le profil utilisateur depuis Entra ID.');
        }

        $user = User::findOrCreateByEntra($oid, $email, $name);

        if ((int)$user['is_active'] !== 1) {
            die('Accès refusé : votre compte est désactivé.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}
