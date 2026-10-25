<?php
/**
 * language.php — Global Language Persistence Loader
 * 
 * Handles all language detection and persistence across:
 *   1. URL query param (?lang=xx)  — highest priority (user just clicked)
 *   2. PHP Session ($_SESSION['lang'])
 *   3. PHP Cookie (rural_learn_lang, 1-year TTL)
 *   4. Default: 'en'
 *
 * Include this file EARLY (before any output) via config/translate.php.
 * All pages that include header.php automatically benefit.
 */

$_LANG_ALLOWED = ['en', 'hi', 'gu'];
$_LANG_COOKIE  = 'rural_learn_lang';

// ── 1. URL param — user just selected a language ────────────────────────────
if (isset($_GET['lang']) && in_array($_GET['lang'], $_LANG_ALLOWED)) {
    $chosen = $_GET['lang'];

    // Persist in session
    $_SESSION['lang'] = $chosen;

    // Persist in long-lived cookie (1 year)
    setcookie(
        $_LANG_COOKIE,
        $chosen,
        [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => '/',
            'httponly' => false,   // JS needs to read it as fallback
            'samesite' => 'Lax',
        ]
    );
}

// ── 2. AJAX / POST param — set_lang.php will have already set session+cookie.
//    Nothing to do here; we just fall through to read session below.

// ── 3. Session already has a language ───────────────────────────────────────
elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $_LANG_ALLOWED)) {
    // Already resolved. No cookie write needed (already done on first set).
}

// ── 4. Cookie fallback — restores language after session expiry ─────────────
elseif (isset($_COOKIE[$_LANG_COOKIE]) && in_array($_COOKIE[$_LANG_COOKIE], $_LANG_ALLOWED)) {
    $_SESSION['lang'] = $_COOKIE[$_LANG_COOKIE];
}

// ── 5. Default: English ──────────────────────────────────────────────────────
else {
    $_SESSION['lang'] = 'en';
}

// Expose $current_lang globally for all templates
$current_lang = $_SESSION['lang'];
