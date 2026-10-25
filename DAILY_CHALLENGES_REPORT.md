# EduQuest - Daily Challenges Module Report

This report outlines the implementation details, database structure, and testing results for the **Daily Challenges Module** of the EduQuest platform.

---

## 1. Database Table Structure

The daily challenge system is backed by two primary relational tables: `daily_challenges` and `student_challenges`.

### Table `daily_challenges`
Holds daily challenges metadata:
- `id` (INT, AUTO_INCREMENT, PRIMARY KEY): Unique identifier.
- `description` (VARCHAR(255), NOT NULL): Clear text description of the challenge (e.g. *"Read at least 1 lesson today"*).
- `target_type` (VARCHAR(50), NOT NULL): Action identifier representing one of the 10 challenge types.
- `target_value` (INT, NOT NULL): The target metric required to complete the challenge (e.g., *5* for answering 5 correct quiz questions).
- `points_reward` (INT, NOT NULL): Bonus coins/points awarded upon completion.
- `xp_reward` (INT, NOT NULL): XP awarded upon completion.
- `challenge_date` (DATE, NOT NULL, UNIQUE): The calendar date this challenge is active.

### Table `student_challenges`
Tracks student-specific daily challenge progression:
- `id` (INT, AUTO_INCREMENT, PRIMARY KEY): Unique identifier.
- `student_id` (INT, NOT NULL, FOREIGN KEY -> `students.id`): Student doing the challenge.
- `challenge_id` (INT, NOT NULL, FOREIGN KEY -> `daily_challenges.id`): Active challenge.
- `status` (ENUM('in_progress', 'completed'), DEFAULT 'in_progress'): Progression state.
- `progress_value` (INT, DEFAULT 0): The current progression count.
- `completed_at` (TIMESTAMP, NULL): When the student achieved the challenge.

---

## 2. Challenge Types & Templates

EduQuest supports **10 unique challenge templates** selected randomly by the generator:

1. **Complete 1 Lesson**: `target_type = 'lesson_read'`, value = `1`.
2. **Complete 2 Lessons**: `target_type = 'lesson_read'`, value = `2`.
3. **Attempt 1 Quiz**: `target_type = 'quiz_attempt'`, value = `1`.
4. **Score 80%+ in a Quiz**: `target_type = 'quiz_score_80'`, value = `1`.
5. **Earn 50 XP**: `target_type = 'xp_earn'`, value = `50`.
6. **Earn 100 XP**: `target_type = 'xp_earn'`, value = `100`.
7. **Maintain Learning Streak**: `target_type = 'streak_maintain'`, value = `1`.
8. **Complete a Course Activity**: `target_type = 'course_activity'`, value = `1`.
9. **Answer 5 Quiz Questions Correctly**: `target_type = 'correct_answers_5'`, value = `5`.
10. **Login Daily Challenge**: `target_type = 'daily_login'`, value = `1`.

---

## 3. Challenge Generation & Reward Logic

The logic is unified in [challenges-helper.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/includes/challenges-helper.php):
- **Auto-Generation Check**: On every student page load, the system checks if a challenge exists in the database for `CURDATE()`. If missing and auto-generation is enabled in settings, it randomly picks a challenge from the templates and saves it.
- **Login auto-completion**: If the active challenge is a `daily_login` challenge, visiting the dashboard automatically flags progress as completed and claims rewards.
- **Rewards System**: Completion triggers an automatic increment to the student's `points` and `xp` columns, and pushes an Inbox notification card.
- **Auto-Reset (Midnight)**: The cron script [daily_challenges_cron.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/database/daily_challenges_cron.php) runs at midnight to pre-generate the next day's challenge and mark incomplete challenges as expired.

---

## 4. Admin Management Panel

Admins access the challenges suite from the sidebar link or directly at [admin/challenges.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/admin/challenges.php):
- **Manual Creation**: Creates custom daily challenges for any selected date.
- **Auto-Generation Toggle**: Instantly toggles setting state in the `settings` table.
- **Statistics Board**: Displays participation count, completion counts, and a dynamic percentage completion rate.

---

## 5. Testing & Verification Results

A simulated user progression test was run using `test_challenge_progression.php` to verify integration:
- **Initial student stats**: Coins: 0, XP: 0
- **Daily Challenge**: *"Answer 5 quiz questions correctly today"* (target: 5)
- **Simulated activity**: User completed quiz answering 5 correct questions.
- **Verification outcomes**:
  - `student_challenges.progress_value` updated to `5/5`
  - `student_challenges.status` flagged as `completed`
  - Student points/coins set to `30` (+30 coins reward)
  - Student XP set to `75` (+75 XP reward)
  - **Status: PASS**
