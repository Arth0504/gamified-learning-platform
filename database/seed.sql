-- Gamified Learning Platform Seed Data
-- Safe, non-destructive, and idempotent seed script for local development.
USE gamified_learning_platform;

-- Preserve referential integrity
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Insert Roles (Admin = 1, Teacher = 2, Student = 3)
INSERT IGNORE INTO roles (id, name) VALUES 
(1, 'Admin'),
(2, 'Teacher'),
(3, 'Student');

-- 2. Insert Users (Password is 'password123')
INSERT IGNORE INTO users (id, role_id, username, email, password_hash, full_name, status) VALUES
(1, 1, 'admin', 'admin@rurallearn.org', '$2y$10$zXTcs6pbVv1MJZ0Hgh7xDeTylvt5e8uUG9bveQfjJKi6DC6n/0Wz.', 'System Administrator', 'active'),
(2, 2, 'teacher', 'teacher@rurallearn.org', '$2y$10$zXTcs6pbVv1MJZ0Hgh7xDeTylvt5e8uUG9bveQfjJKi6DC6n/0Wz.', 'Suresh Kumar', 'active'),
(3, 3, 'student', 'student@rurallearn.org', '$2y$10$zXTcs6pbVv1MJZ0Hgh7xDeTylvt5e8uUG9bveQfjJKi6DC6n/0Wz.', 'Ramesh Patel', 'active');

-- 3. Insert Teacher profile (Linked to user_id = 2)
INSERT IGNORE INTO teachers (id, user_id, bio, specialization) VALUES
(1, 2, 'Passionate educator focusing on primary and middle school education in rural areas.', 'Mathematics & Science');

-- 4. Insert Student profile (Linked to user_id = 3)
INSERT IGNORE INTO students (id, user_id, points, xp, streak_count, last_activity_date) VALUES
(1, 3, 120, 350, 3, CURDATE());

-- 5. Insert Sample Course (Created by Suresh Kumar, teacher_id = 1)
INSERT IGNORE INTO courses (id, teacher_id, title, description, category, difficulty_level, image_path, status) VALUES
(1, 1, 'Basic Mathematics', 'A fun course to learn numbers, counting, basic addition, and subtraction with real-life rural examples like counting cattle and sharing fruits.', 'Mathematics', 'easy', 'assets/uploads/math_course.jpg', 'published');

-- 6. Insert Subjects (Linked to course_id = 1)
INSERT IGNORE INTO subjects (id, course_id, title, description, sort_order) VALUES
(1, 1, 'Unit 1: Introduction to Numbers', 'Learn counting, place values, and ordering numbers.', 1),
(2, 1, 'Unit 2: Simple Addition', 'Adding numbers together using farm examples.', 2);

-- 7. Insert Lessons (Linked to subjects 1 and 2)
INSERT IGNORE INTO lessons (id, subject_id, title, content, content_type, file_path, external_url, duration_mins, is_downloadable, sort_order) VALUES
(1, 1, 'Counting 1 to 100', 'Welcome to your first math lesson! Let us learn to count. \n\n1 (One) - One Sun in the sky.\n2 (Two) - Two bullocks pulling a cart.\n3 (Three) - Three wheels on an auto-rickshaw.\n4 (Four) - Four legs of a cow.\n\nPractice counting items in your house and write them down in your notebook!', 'text', NULL, NULL, 10, 1, 1),
(2, 1, 'Place Value: Tens and Ones', 'Watch this video to understand how tens and ones work. Ten single items make one bundle of ten!', 'video', NULL, 'https://www.youtube.com/embed/5W47KDGPtgQ', 12, 0, 2),
(3, 2, 'Addition Guide Book', 'This guide contains fun practice worksheets for addition using diagrams. Download and complete the exercises.', 'pdf', 'assets/uploads/addition_guide.pdf', NULL, 15, 1, 1);

-- 8. Insert Course Enrollment (student_id = 1, course_id = 1)
INSERT IGNORE INTO course_enrollments (id, student_id, course_id, enrolled_at, progress_percent, status) VALUES
(1, 1, 1, CURRENT_TIMESTAMP, 33, 'active');

-- 9. Insert Lesson progress for Student (first lesson completed: student_id = 1, lesson_id = 1)
INSERT IGNORE INTO progress (student_id, lesson_id, status, started_at, completed_at) VALUES
(1, 1, 'completed', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR), CURRENT_TIMESTAMP);

-- 10. Insert Quiz (lesson_id = 1, course_id = 1)
INSERT IGNORE INTO quizzes (id, lesson_id, course_id, title, description, time_limit_mins, passing_score, max_attempts, points_reward, xp_reward) VALUES
(1, 1, 1, 'Numbers Quiz', 'A quick quiz to test your counting skills.', 5, 50, 3, 50, 100);

-- 11. Insert Quiz Questions (quiz_id = 1)
INSERT IGNORE INTO questions (id, quiz_id, question_text, question_type, points, difficulty) VALUES
(1, 1, 'What number comes after 29?', 'mcq', 10, 'easy'),
(2, 1, 'How many legs does a cow have?', 'mcq', 10, 'easy');

-- 12. Insert Question Options (questions 1 and 2)
INSERT IGNORE INTO question_options (id, question_id, option_text, is_correct) VALUES
(1, 1, '28', 0),
(2, 1, '30', 1),
(3, 1, '31', 0),
(4, 1, '20', 0),
(5, 2, '2', 0),
(6, 2, '4', 1),
(7, 2, '6', 0),
(8, 2, '8', 0);

-- 13. Insert Badges
INSERT IGNORE INTO badges (id, name, description, image_path, criteria_type, criteria_value) VALUES
(1, 'Quick Learner', 'Pass 1 quiz successfully.', 'quick_learner.png', 'quizzes', 1),
(2, 'Consistent Scholar', 'Maintain a 3-day learning streak.', 'consistent_scholar.png', 'streak', 3),
(3, 'Point Explorer', 'Amass 100 learning points.', 'point_explorer.png', 'points', 100),
(4, 'First Course Graduate', 'Complete 1 full course.', 'graduate.png', 'course_completion', 1);

-- 14. Award Badge 3 to the student (since they have 120 points: student_id = 1, badge_id = 3)
INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_at) VALUES
(1, 3, CURRENT_TIMESTAMP);

-- 15. Insert Rewards
INSERT IGNORE INTO rewards (id, title, description, cost_points, image_path) VALUES
(1, 'Printable Math Worksheet', 'Get a high-quality PDF addition worksheet for self-study.', 30, 'worksheet.png'),
(2, 'Personalized E-Certificate', 'Unlock a customized PDF certificate signed by your teacher.', 100, 'certificate.png');

-- 16. Insert Daily Challenges (one for today, one for tomorrow)
INSERT IGNORE INTO daily_challenges (id, description, target_type, target_value, points_reward, xp_reward, challenge_date) VALUES
(1, 'Read at least 1 lesson today', 'lesson_read', 1, 20, 50, CURDATE()),
(2, 'Pass a quiz tomorrow', 'quiz_completion', 1, 30, 80, DATE_ADD(CURDATE(), INTERVAL 1 DAY));

-- 17. Link Daily Challenge 1 to Student as In-Progress (student_id = 1, challenge_id = 1)
INSERT IGNORE INTO student_challenges (student_id, challenge_id, status, progress_value) VALUES
(1, 1, 'in_progress', 0);

-- 18. Seed settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'Rural Learn - Gamified Platform'),
('allow_registration', '1'),
('xp_per_lesson', '20'),
('points_per_lesson', '10');

-- 19. Seed leaderboards (student_id = 1)
INSERT IGNORE INTO leaderboards (student_id, total_points, rank_position) VALUES
(1, 120, 1);
