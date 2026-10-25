# HY093 Database Parameter Count Mismatch Bug Report

A critical database exception `SQLSTATE[HY093]: Invalid parameter number` occurred when completing lessons, gaining XP, taking quizzes, or logging in. Below is the diagnostic audit and solution documentation.

---

## 1. Bug Audit & Occurrences

The bug occurred when inserting records into the `notifications` table. The prepared statements hardcoded the notification category string literal inside the `VALUES` clause (yielding only 3 positional `?` placeholders), but the parameter arrays passed to `execute()` still contained 4 elements (including the category string). 

This mismatch occurred in the following locations:

| File Name | Line Number | SQL Query Affected | Missing Parameter / Count |
| :--- | :--- | :--- | :--- |
| [`student/lesson-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/lesson-view.php) | `109` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'info')` | 4 bound vs. 3 placeholders |
| [`student/lesson-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/lesson-view.php) | `175` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/lesson-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/lesson-view.php) | `199` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/lesson-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/lesson-view.php) | `217` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/courses.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/courses.php) | `44` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'course')` | 4 bound vs. 3 placeholders |
| [`student/quiz-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-view.php) | `197` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'quiz')` | 4 bound vs. 3 placeholders |
| [`student/quiz-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-view.php) | `218` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/quiz-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-view.php) | `236` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/quiz-view.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/quiz-view.php) | `278` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`student/achievements.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/achievements.php) | `57` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |
| [`login.php`](file:///c:/xampp/htdocs/gamified%20learning%20platform/login.php) | `80` | `INSERT INTO notifications (..., type) VALUES (?, ?, ?, 'badge')` | 4 bound vs. 3 placeholders |

---

## 2. Root Cause

In PHP PDO, if the number of parameters passed in the `$stmt->execute([...])` array does not match the number of parameter placeholders (`?` or `:name`) in the prepared SQL statement, PDO throws a `PDOException` with code `HY093`. 

In all the audited occurrences:
- The SQL statement defined: `VALUES (?, ?, ?, 'category_literal')` (which contains only **3** parameters to bind)
- The execution array defined: `[$user_id, $title, $message, 'category_literal']` (which contains **4** parameters)
- Since the execute array had 4 items and the SQL statement only had 3 placeholders, the query execution threw the `SQLSTATE[HY093]` error.

---

## 3. Fix Applied

We modified all occurrence SQL queries to dynamically accept the 4th parameter via a placeholder:

```sql
/* BEFORE */
INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')

/* AFTER */
INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)
```

This properly maps all 4 elements in the execution arrays:
1. `user_id` -> `$user_id`
2. `title` -> `$title`
3. `message` -> `$message`
4. `type` -> `$type` (e.g. `'info'`, `'badge'`, `'quiz'`, `'course'`)

All files have been verified to pass syntax check. Manual verification in the browser confirms that marking a lesson complete now runs correctly without any database errors.
