<?php

namespace VanillIA\Core;

class I18n
{
    private const SUPPORTED = ['fr', 'en'];
    private static string $currentLang = 'fr';
    private static array $translations = [];

    public static function init(?string $preferredLang = null): string
    {
        $lang = null;

        if (!empty($_GET['lang']) && in_array($_GET['lang'], self::SUPPORTED, true)) {
            $lang = $_GET['lang'];
            $_SESSION['lang'] = $lang;
            self::setLangCookie($lang);
        } elseif (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], self::SUPPORTED, true)) {
            $lang = $_SESSION['lang'];
        } elseif (!empty($_COOKIE['vanillia_lang']) && in_array($_COOKIE['vanillia_lang'], self::SUPPORTED, true)) {
            $lang = $_COOKIE['vanillia_lang'];
            $_SESSION['lang'] = $lang;
        } elseif (!empty($preferredLang) && in_array($preferredLang, self::SUPPORTED, true)) {
            $lang = $preferredLang;
            $_SESSION['lang'] = $lang;
            self::setLangCookie($lang);
        } else {
            $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
            if (str_starts_with($accept, 'en')) {
                $lang = 'en';
            } else {
                $defaultEnv = strtolower((string)Env::get('APP_DEFAULT_LANG', 'fr'));
                $lang = in_array($defaultEnv, self::SUPPORTED, true) ? $defaultEnv : 'fr';
            }
        }

        self::$currentLang = $lang;
        self::loadDictionary($lang);
        return $lang;
    }

    public static function setLang(string $lang): string
    {
        if (!in_array($lang, self::SUPPORTED, true)) {
            $lang = 'fr';
        }
        self::$currentLang = $lang;
        $_SESSION['lang'] = $lang;
        self::setLangCookie($lang);
        self::loadDictionary($lang);
        return $lang;
    }

    public static function getLang(): string
    {
        return self::$currentLang;
    }

    public static function getSupported(): array
    {
        return self::SUPPORTED;
    }

    public static function t(string $key, array $params = []): string
    {
        if (empty(self::$translations)) {
            self::loadDictionary(self::$currentLang);
        }

        $text = self::$translations[$key] ?? $key;
        foreach ($params as $pKey => $pVal) {
            $text = str_replace('{' . $pKey . '}', (string)$pVal, $text);
        }
        return $text;
    }

    public static function all(): array
    {
        if (empty(self::$translations)) {
            self::loadDictionary(self::$currentLang);
        }
        return self::$translations;
    }

    public static function getTheme(?string $preferredTheme = null): string
    {
        if (!empty($_COOKIE['vanillia_theme']) && in_array($_COOKIE['vanillia_theme'], ['dark', 'light'], true)) {
            return $_COOKIE['vanillia_theme'];
        }
        if (!empty($_SESSION['theme']) && in_array($_SESSION['theme'], ['dark', 'light'], true)) {
            return $_SESSION['theme'];
        }
        if (!empty($preferredTheme) && in_array($preferredTheme, ['dark', 'light'], true)) {
            return $preferredTheme;
        }
        return 'dark';
    }

    public static function setThemeCookie(string $theme): void
    {
        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = 'dark';
        }
        $_SESSION['theme'] = $theme;
        if (!headers_sent()) {
            setcookie('vanillia_theme', $theme, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function setLangCookie(string $lang): void
    {
        if (!headers_sent()) {
            setcookie('vanillia_lang', $lang, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
    }

    private static function loadDictionary(string $lang): void
    {
        $file = __DIR__ . '/../Lang/' . $lang . '.php';
        $fallback = __DIR__ . '/../Lang/fr.php';
        $base = file_exists($fallback) ? require $fallback : [];
        $target = ($lang !== 'fr' && file_exists($file)) ? require $file : [];
        self::$translations = array_merge($base, $target);
    }
}
