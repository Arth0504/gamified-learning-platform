# Login & Register Pages - Translation Refactor Error Report
**Project:** Rural Learn — Gamified Learning Platform  
**Date:** 2026-07-06  

---

## 1. Exact Error Message Captured

```
Fatal error: Uncaught Error: Call to undefined function t() in C:\xampp\htdocs\gamified learning platform\login.php:2
Stack trace:
#0 {main}
  thrown in C:\xampp\htdocs\gamified learning platform\login.php on line 2
```

*(Note: The exact same Fatal Error was also triggered on `register.php:2` and `forgot-password.php:2` when accessed).*

---

## 2. Diagnostics Details

- **File Names:** `login.php`, `register.php`, `forgot-password.php`
- **Line Number:** `2` (the assignment of `$page_title`)
- **Root Cause:** 
  In the translation refactoring step, the dynamic page title assignment was changed to call `t()` directly at the top of the file:
  `$page_title = t('common.login');`
  
  However, the translation configuration (`config/translate.php`)—which defines the `t()` helper function—was not loaded until line 5/6 when `includes/header.php` was included:
  `include_once __DIR__ . '/includes/header.php';`
  
  Because PHP executed the function call to `t()` *before* its definition block was loaded/evaluated, it threw a Fatal Error on bootstrapping.

---

## 3. Fix Applied & Verified

1. **Bootstrap Translation Helper Early:**
   Modified `login.php`, `register.php`, and `forgot-password.php` to explicitly load the translation engine config `config/translate.php` using `require_once` at the very beginning of the files, before calling `t()`:
   ```php
   <?php
   require_once __DIR__ . '/config/translate.php';
   $page_title = t('common.login');
   ...
   ```

2. **Ensured Clean Inclusions:**
   Because `includes/header.php` requires `config/translate.php` using `require_once`, the subsequent inclusion inside the header is skipped by PHP, avoiding any double inclusion or redeclaration warnings.

3. **Status Check:**
   - Both pages now load and render successfully on local Apache server.
   - Syntax lint checks passed cleanly with exit code `0`.
