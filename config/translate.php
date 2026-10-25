<?php
/**
 * translate.php — Translation Engine
 *
 * Loads the correct language file and exposes the __() helper.
 * Language detection is handled by includes/language.php which
 * implements a 4-layer persistence chain:
 *   URL param → Session → Cookie (1 yr) → default 'en'
 */

// ── Language detection (sets $current_lang) ──────────────────────────────────
require_once dirname(__DIR__) . '/includes/language.php';

// ── Load translation strings from the matching language file ─────────────────
$lang_file = dirname(__DIR__) . "/languages/{$current_lang}.php";
if (file_exists($lang_file)) {
    $translations = include $lang_file;
} else {
    $translations = [];
}

// ── Translation helper ────────────────────────────────────────────────────────
if (!function_exists('__')) {
    /**
     * Translate a key using dot notation.
     * Supports placeholder replacement: __('student.welcome', ['name' => 'Arth'])
     *
     * @param string $key          Dot-separated key, e.g. 'nav.courses'
     * @param array  $placeholders Key→value pairs for {placeholder} substitution
     * @return string
     */
    function __($key, $placeholders = []) {
        global $translations;

        $keys  = explode('.', $key);
        $value = $translations;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Fallback: return raw key if translation missing
            }
        }

        if (!is_string($value)) {
            return $key;
        }

        // Replace {placeholder} tokens
        foreach ($placeholders as $placeholder => $replacement) {
            $value = str_replace("{{$placeholder}}", $replacement, $value);
        }

        return $value;
    }
}

if (!function_exists('t')) {
    /**
     * Alias for translation helper __()
     */
    function t($key, $placeholders = []) {
        return __($key, $placeholders);
    }
}
?>
