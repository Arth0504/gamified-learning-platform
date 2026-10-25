// App JavaScript — Language Switcher, Sidebar, & Persistence
// Language strategy:
//   1. On click  → save to localStorage + POST to set_lang.php (session+cookie) → reload
//   2. On load   → read localStorage, sync with PHP via set_lang.php if mismatch → reload

document.addEventListener('DOMContentLoaded', () => {

    // ── Constants ──────────────────────────────────────────────────────────────
    const LANG_KEY      = 'rural_learn_lang';
    const ALLOWED_LANGS = ['en', 'hi', 'gu'];
    const SET_LANG_URL  = '/gamified learning platform/includes/set_lang.php';

    // Resolve base path dynamically so it works from any sub-directory
    function getSetLangUrl() {
        // Walk up from current path to find the app root
        const base = document.querySelector('script[src*="assets/js/app.js"]');
        if (base) {
            return base.src.replace('assets/js/app.js', 'includes/set_lang.php');
        }
        return SET_LANG_URL;
    }

    /**
     * Send the selected language to PHP (sets session + cookie), save in
     * localStorage, then reload the page so PHP renders in the new language.
     */
    function applyLanguage(lang) {
        if (!ALLOWED_LANGS.includes(lang)) return;

        localStorage.setItem(LANG_KEY, lang);

        fetch(getSetLangUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lang=${encodeURIComponent(lang)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Strip any legacy ?lang= param from URL before reloading
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('lang');
                window.location.href = cleanUrl.toString();
            }
        })
        .catch(() => {
            // Fallback: reload anyway — PHP cookie was at least saved if fetch
            // partially succeeded; worst case language.php reads the cookie.
            window.location.reload();
        });
    }

    // ── Language Switcher Click Handler ────────────────────────────────────────
    document.querySelectorAll('.lang-opt').forEach(opt => {
        opt.addEventListener('click', e => {
            e.preventDefault();
            const selectedLang = opt.getAttribute('data-lang');
            applyLanguage(selectedLang);
        });
    });

    // ── On-Load Sync: localStorage → PHP ──────────────────────────────────────
    // If user has a localStorage preference that differs from what PHP rendered,
    // sync it now. This handles: new tab, Incognito, cleared cookies, etc.
    const storedLang  = localStorage.getItem(LANG_KEY);
    const activeLang  = document.documentElement.getAttribute('lang') || 'en';

    if (storedLang && ALLOWED_LANGS.includes(storedLang) && storedLang !== activeLang) {
        // Silently sync to PHP then reload — user will never see a flash
        fetch(getSetLangUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lang=${encodeURIComponent(storedLang)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('lang');
                window.location.href = cleanUrl.toString();
            }
        })
        .catch(() => {
            // Non-blocking — if AJAX fails just leave the page as-is
        });
    }

    // ── Highlight Active Language in Dropdown ──────────────────────────────────
    document.querySelectorAll('.lang-opt').forEach(opt => {
        const lang = opt.getAttribute('data-lang');
        if (lang === activeLang) {
            opt.classList.add('lang-opt-active');
            // Add a visible checkmark prefix
            if (!opt.querySelector('.lang-check')) {
                const check = document.createElement('span');
                check.className = 'lang-check';
                check.textContent = '✓ ';
                check.style.cssText = 'color: #004ac6; font-weight: 700; margin-right: 4px;';
                opt.prepend(check);
            }
        }
    });

    // ── Mobile Sidebar Toggle ──────────────────────────────────────────────────
    const toggleSidebarBtn = document.getElementById('toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');

    if (toggleSidebarBtn && sidebar) {
        toggleSidebarBtn.addEventListener('click', e => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', e => {
            if (
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) &&
                e.target !== toggleSidebarBtn
            ) {
                sidebar.classList.remove('show');
            }
        });
    }
});
