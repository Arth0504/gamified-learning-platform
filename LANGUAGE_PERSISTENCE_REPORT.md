# Language Persistence Report
**Project:** Rural Learn — Gamified Learning Platform
**Date:** 2026-07-06
**Scope:** Global multi-language persistence (English / Hindi / Gujarati)

---

## Executive Summary

Language selection is now fully persistent across all pages, browser refreshes, session expiry events, and cross-page navigation. The implementation uses a **3-layer persistence chain** (localStorage → PHP Session → PHP Cookie) so the selected language is never lost under any normal operating condition.

---

## Files Changed

### New Files Created

| File | Purpose |
|---|---|
| `includes/language.php` | Central language loader — single source of truth for language detection |
| `includes/set_lang.php` | Lightweight AJAX endpoint — sets PHP session + cookie on language change |

### Modified Files

| File | Change |
|---|---|
| `config/translate.php` | Removed inline GET-param handling; now delegates to `language.php` |
| `assets/js/app.js` | Replaced URL-redirect switcher with localStorage + AJAX + active highlight |
| `includes/public_navbar.php` | Added `lang_opt_class()` helper, flag emojis, active-state highlight |
| `includes/navbar.php` | Same active-highlight treatment for the dashboard navbar |
| `config/config.php` | Preserves `$_SESSION['lang']` across session timeout wipe |

---

## Persistence Architecture

```
User clicks language option
        │
        ▼
   app.js (browser)
   ├─ localStorage.setItem('rural_learn_lang', 'gu')
   └─ fetch POST → includes/set_lang.php
              │
              ▼
         set_lang.php
         ├─ $_SESSION['lang'] = 'gu'
         └─ setcookie('rural_learn_lang', 'gu', +1 year)
              │
              ▼
   app.js: window.location.reload()  (clean URL, no ?lang= clutter)
              │
              ▼
   Next request (any page)
   └─ includes/header.php
      └─ config/translate.php
         └─ includes/language.php
            Priority chain:
            1. ?lang= GET param               → saves to session + cookie
            2. $_SESSION['lang']              → already set from AJAX
            3. $_COOKIE['rural_learn_lang']   → fallback after session expiry
            4. Default: 'en'
```

---

## Pages Covered

| Page | Navbar | Lang Persistence |
|---|---|---|
| `index.php` (Home) | `public_navbar.php` | YES |
| `methodology.php` | `public_navbar.php` | YES |
| `impact.php` | `public_navbar.php` | YES |
| `partnerships.php` | `public_navbar.php` | YES |
| `login.php` | `public_navbar.php` | YES |
| `register.php` | `public_navbar.php` | YES |
| `forgot-password.php` | `public_navbar.php` | YES |
| `student/dashboard.php` | `navbar.php` | YES |
| `teacher/dashboard.php` | `navbar.php` | YES |
| `admin/dashboard.php` | `navbar.php` | YES |
| All other dashboard pages | `navbar.php` | YES |

---

## Language Selector UI Updates

- Active language highlighted with blue background (`bg-primary/10 text-primary font-semibold`)
- Flag emoji prefix: 🇬🇧 English | 🇮🇳 Hindi | 🇮🇳 Gujarati
- Dropdown button shows active language name
- `data-active-lang` attribute on dropdown wrapper for JS targeting
- JavaScript adds checkmark (✓) prefix to active item

---

## Session Timeout Handling

`config/config.php` now captures `$_SESSION['lang']` BEFORE wiping the session, then restores it:

```php
$_saved_lang = $_SESSION['lang'] ?? ($_COOKIE['rural_learn_lang'] ?? 'en');
// ... wipe session ...
$_SESSION['lang'] = $_saved_lang;  // restore after restart
```

---

## Verification Checklist

### Test Scenario 1 — Gujarati persists across navigation
- [x] Select Gujarati on Home page
- [x] Navigate to methodology.php → Gujarati
- [x] Navigate to impact.php → Gujarati
- [x] Navigate to partnerships.php → Gujarati
- [x] Navigate to login.php → Gujarati
- [x] Navigate to register.php → Gujarati

### Test Scenario 2 — Refresh persistence
- [x] Select Gujarati → press F5 → still Gujarati
- [x] Close tab, reopen URL → still Gujarati (cookie)

### Test Scenario 3 — Hindi persistence
- [x] Select Hindi → navigate all pages → Hindi everywhere

### Test Scenario 4 — Switch back to English
- [x] From Gujarati, select English → all pages in English

### Test Scenario 5 — Dashboard pages
- [x] Login → dashboard in selected language
- [x] Navigate courses, quizzes, leaderboard → same language

### Test Scenario 6 — Session expiry
- [x] Select Gujarati → clear session cookie → revisit
- [x] PHP reads `rural_learn_lang` cookie → renders Gujarati

---

## Cookie Reference

| Cookie Name | Value | Expiry | Path | HttpOnly |
|---|---|---|---|---|
| `rural_learn_lang` | `en` / `hi` / `gu` | +1 year | `/` | No (JS-readable) |

---

*Generated automatically — Language Persistence Implementation 2026-07-06*
