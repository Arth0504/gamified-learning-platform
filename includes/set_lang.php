<?php
/**
 * set_lang.php — AJAX Language Persistence Endpoint
 *
 * Called via POST from app.js when the user selects a language.
 * Sets both PHP session and a long-lived cookie so language
 * persists across navigation, session expiry, and browser restarts.
 *
 * Expected POST body: { lang: 'en' | 'hi' | 'gu' }
 * Returns JSON: { success: true, lang: 'xx' } or { success: false, error: '...' }
 */

// Load config to get session started (config.php starts the session)
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=UTF-8');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$allowed_langs = ['en', 'hi', 'gu'];
$lang = trim($_POST['lang'] ?? '');

if (!in_array($lang, $allowed_langs)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid language code.']);
    exit;
}

// Set in PHP session
$_SESSION['lang'] = $lang;

// Set long-lived cookie (1 year) — readable by PHP and JS
setcookie(
    'rural_learn_lang',
    $lang,
    [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'httponly' => false,   // must be JS-readable for localStorage sync
        'samesite' => 'Lax',
    ]
);

echo json_encode(['success' => true, 'lang' => $lang]);
exit;
