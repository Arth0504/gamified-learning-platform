# Viva Examination Preparation Guide

This guide compiles critical technical, architectural, and security questions that examiners frequently ask during final project presentations.

---

## 🏗️ 1. Architecture & Design

### Q: Why did you choose raw/vanilla PHP instead of a framework like Laravel?
* **Answer:** Vanilla PHP has no extra package loading overhead, making it highly lightweight and suitable for local village deployment on budget hardware. Writing it from scratch demonstrates a strong grasp of core HTTP requests handling, prepared statement connections, SQL normalization schemas, and session controls.

### Q: Explain the directory structure.
* **Answer:** It uses an MVC-inspired layout:
  * `/config/` handles database settings, translation setups, and security properties.
  * `/includes/` contains common layouts (header, footer, navbar, sidebar) and role checking middlewares.
  * `/languages/` separates localization resource strings.
  * Root directories (`/admin/`, `/teacher/`, `/student/`) isolate the dashboards, menus, and pages for each role.

---

## 🔒 2. Security and Data Protection

### Q: What is a CSRF attack, and how does your application prevent it?
* **Answer:** Cross-Site Request Forgery occurs when an attacker tricks an authenticated user into performing unwanted actions. To prevent this, our [config.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/config/config.php) generates a cryptographically secure random token (`csrf_token`) and stores it in the session. Forms append this token as a hidden field. Upon POST requests, the validation middleware confirms the POST token matches the session value.

### Q: What is XSS, and how is it prevented?
* **Answer:** Cross-Site Scripting occurs when malicious script tags are injected into input fields and rendered in the browser. We wrap all page outputs inside a custom htmlspecialchars escaping helper `e()` which encodes characters like `<` and `>` into HTML entities (`&lt;` and `&gt;`), rendering them safely as text.

---

## 🎮 3. Gamification & Adaptive Learning

### Q: How is the Leaderboard recalculation optimized?
* **Answer:** Instead of using heavy background batch calculations, the rankings table executes a direct SQL request sorting students dynamically by points and XP:
  `ORDER BY points DESC, xp DESC`
  This updates ranks in real-time as points change, keeping standings completely synchronized.

### Q: Describe the Adaptive Learning Recommendations query logic.
* **Answer:** The recommendations engine tracks quiz attempts. If a student scores below 60% on a quiz, a query retrieves the weak subject ID and fetches related lessons from that unit for revision. If a student passes a quiz with a score of 90% or above, the system checks the course difficulty level and queries courses in the same category but with a higher difficulty tier (e.g. Medium or Hard) that the student is not enrolled in yet, suggesting advanced content.
