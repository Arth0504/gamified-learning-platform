# Secure Quiz Mode Implementation Report

This report documents the implementation of the Secure Quiz Mode, designed to prevent cheating during quiz sessions.

---

## 1. Feature Specifications & Architecture

### A. Quiz Launch & Navigation
- **Dedicated Window**: When a student launches a quiz from the introductory page, it opens in a dedicated pop-up portal using `window.open()` with restricted window configurations (`menubar=no,status=no,toolbar=no,resizable=yes`). This prevents standard browser navigation interference.
- **Hidden Site Layout**: If the active quiz page loads (`start=1`), the page dynamically sets `$no_layout = true` and `$simple_footer = true` at the very beginning of processing, hiding all default wrappers, sidebars, footers, and dashboard menus.
- **Interactive Fullscreen Overlay**: On page load, a clean glassmorphism overlay enforces the security rules. Clicking "Enter Secure Fullscreen & Begin" executes `requestFullscreen()`, transition-hiding the overlay, initializing the database security log, and starting the timer.

### B. Quiz Restrictions & Clipboard Blocking
- **Event Interceptions**: Event listeners are registered on the active document to completely intercept:
  - Right-click (`contextmenu` -> `e.preventDefault()`)
  - Copy (`copy` -> `e.preventDefault()`)
  - Cut (`cut` -> `e.preventDefault()`)
  - Paste (`paste` -> `e.preventDefault()`)
- **CSS Text Selection Defeat**: CSS rules apply `user-select: none !important` to the body element, preventing students from selecting question or choice text.

### C. Visibility & Focus Loss Tracking
- **Listeners Registered**: The window tracks focus changes using `window.blur` (losing window focus), `document.visibilitychange` (switching tabs, minimizing), and `document.fullscreenchange` (exiting secure fullscreen).
- **Intelligent Violation Throttling**: A 2-second timestamp threshold prevents double-counting focus losses when transitioning states.
- **Three-Violation Warning System**:
  - **1st focus loss**: Triggers Warning #1 custom alert.
  - **2nd focus loss**: Triggers Warning #2 custom alert.
  - **3rd focus loss**: Alerts the student, locks out inputs, sets `#auto_submitted` to `1`, and automatically executes JS `form.submit()`.

### D. Timer Security & Database Sessions
- **Session-bound Base**: When the quiz begins, the server registers the start timestamp (`$_SESSION['quiz'][$quiz_id]['start_time'] = time()`).
- **Resilience**: The remaining seconds are dynamically recalculated server-side upon page reloads. Reloads force the student to re-authenticate fullscreen mode, but preserve current progress and block clock tampering.

### E. Auto-Save Action
- **Answer Persistency**: Active choice selections are automatically saved in `$_SESSION['quiz'][$quiz_id]['answers']` in two ways:
  - **Click Action**: Every time an option or radio input is changed, a fetch POST payload is sent.
  - **Periodic Interval**: A background `setInterval()` executes auto-saves every 15 seconds.
- On refresh/reload, choices are pre-populated dynamically from the session data.

---

## 2. Database Schema Details

We created the `quiz_security_logs` database table via a PHP migration script:

```sql
CREATE TABLE IF NOT EXISTS quiz_security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    warning_count INT DEFAULT 0,
    focus_loss_count INT DEFAULT 0,
    fullscreen_exit_count INT DEFAULT 0,
    auto_submitted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Teacher/Admin Reports Integration

We added a **Security Violations** tab inside [reports.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/teacher/reports.php) to allow teachers/admins to audit secure quiz quest sessions. 

- **Audited Metrics**: Student Name, Quiz Title, Course Title, Warnings Issued, Focus Losses, Fullscreen Exits, Auto-Submitted status, and completion timestamp.
- **Spreadsheet Export**: Integrated secure quiz violations report into the CSV export handler.

---

## 4. Verification Testing Results

- **AJAX Endpoints Checked**:
  - [quiz-autosave-ajax.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-autosave-ajax.php): **Passed**
  - [quiz-security-ajax.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-security-ajax.php): **Passed**
- **Syntax Check**: All modified files pass compilation checks cleanly.
- **Browser Interaction Test**: Launching the quiz opens a clean fullscreen page with no navigation sidebars. Tab switching triggers alerts, updates the database logs in real time, and auto-submits answers on the 3rd violation.
