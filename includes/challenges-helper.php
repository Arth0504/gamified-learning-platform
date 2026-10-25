<?php
require_once __DIR__ . '/../config/db.php';

if (!function_exists('get_or_generate_daily_challenge')) {
    function get_or_generate_daily_challenge($pdo) {
        $today = date('Y-m-d');
        
        // 1. Check if challenge already exists for today
        $stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
        $stmt->execute([$today]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($challenge) {
            return $challenge;
        }
        
        // 2. Check setting for auto generation
        $set_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_generate_challenges'");
        $set_stmt->execute();
        $auto_gen = $set_stmt->fetchColumn();
        
        // Default to '1' if not set
        if ($auto_gen === false) {
            $auto_gen = '1';
        }
        
        if ($auto_gen !== '1') {
            return null; // Auto generation disabled
        }
        
        // 3. Generate random challenge from templates
        $templates = [
            [
                'description' => 'Read at least 1 lesson today',
                'target_type' => 'lesson_read',
                'target_value' => 1,
                'points_reward' => 20,
                'xp_reward' => 50
            ],
            [
                'description' => 'Read at least 2 lessons today',
                'target_type' => 'lesson_read',
                'target_value' => 2,
                'points_reward' => 35,
                'xp_reward' => 80
            ],
            [
                'description' => 'Attempt at least 1 quiz today',
                'target_type' => 'quiz_attempt',
                'target_value' => 1,
                'points_reward' => 20,
                'xp_reward' => 50
            ],
            [
                'description' => 'Score 80% or more in any quiz today',
                'target_type' => 'quiz_score_80',
                'target_value' => 1,
                'points_reward' => 40,
                'xp_reward' => 100
            ],
            [
                'description' => 'Earn 50 XP today',
                'target_type' => 'xp_earn',
                'target_value' => 50,
                'points_reward' => 25,
                'xp_reward' => 50
            ],
            [
                'description' => 'Earn 100 XP today',
                'target_type' => 'xp_earn',
                'target_value' => 100,
                'points_reward' => 50,
                'xp_reward' => 100
            ],
            [
                'description' => 'Maintain your learning streak by doing any activity today',
                'target_type' => 'streak_maintain',
                'target_value' => 1,
                'points_reward' => 15,
                'xp_reward' => 40
            ],
            [
                'description' => 'Complete a Course Activity (read a lesson or take a quiz)',
                'target_type' => 'course_activity',
                'target_value' => 1,
                'points_reward' => 20,
                'xp_reward' => 50
            ],
            [
                'description' => 'Answer 5 quiz questions correctly today',
                'target_type' => 'correct_answers_5',
                'target_value' => 5,
                'points_reward' => 30,
                'xp_reward' => 75
            ],
            [
                'description' => 'Log in to your dashboard today',
                'target_type' => 'daily_login',
                'target_value' => 1,
                'points_reward' => 10,
                'xp_reward' => 30
            ]
        ];
        
        $selected = $templates[array_rand($templates)];
        
        try {
            $ins_stmt = $pdo->prepare("
                INSERT INTO daily_challenges (description, target_type, target_value, points_reward, xp_reward, challenge_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins_stmt->execute([
                $selected['description'],
                $selected['target_type'],
                $selected['target_value'],
                $selected['points_reward'],
                $selected['xp_reward'],
                $today
            ]);
            
            $selected['id'] = $pdo->lastInsertId();
            $selected['challenge_date'] = $today;
            return $selected;
        } catch (Exception $e) {
            // Fetch if inserted in parallel
            $stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
            $stmt->execute([$today]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

if (!function_exists('track_student_challenge_progress')) {
    function track_student_challenge_progress($pdo, $student_id, $target_type, $increment = 1, $absolute = null) {
        $challenge = get_or_generate_daily_challenge($pdo);
        if (!$challenge) {
            return;
        }
        
        if ($challenge['target_type'] !== $target_type) {
            return;
        }
        
        $stud_stmt = $pdo->prepare("SELECT user_id, points, xp FROM students WHERE id = ?");
        $stud_stmt->execute([$student_id]);
        $student = $stud_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return;
        }
        
        $user_id = $student['user_id'];
        $current_points = intval($student['points']);
        $current_xp = intval($student['xp']);
        
        $sc_stmt = $pdo->prepare("SELECT * FROM student_challenges WHERE student_id = ? AND challenge_id = ?");
        $sc_stmt->execute([$student_id, $challenge['id']]);
        $sc = $sc_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sc) {
            $progress = ($absolute !== null) ? $absolute : $increment;
            $status = ($progress >= $challenge['target_value']) ? 'completed' : 'in_progress';
            $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            
            $ins = $pdo->prepare("
                INSERT INTO student_challenges (student_id, challenge_id, status, progress_value, completed_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$student_id, $challenge['id'], $status, $progress, $completed_at]);
            
            if ($status === 'completed') {
                award_challenge_rewards($pdo, $student_id, $user_id, $current_points, $current_xp, $challenge);
            }
        } else {
            if ($sc['status'] === 'completed') {
                return;
            }
            
            $progress = ($absolute !== null) ? $absolute : ($sc['progress_value'] + $increment);
            $status = ($progress >= $challenge['target_value']) ? 'completed' : 'in_progress';
            $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            
            $up = $pdo->prepare("
                UPDATE student_challenges 
                SET progress_value = ?, status = ?, completed_at = ? 
                WHERE student_id = ? AND challenge_id = ?
            ");
            $up->execute([$progress, $status, $completed_at, $student_id, $challenge['id']]);
            
            if ($status === 'completed') {
                award_challenge_rewards($pdo, $student_id, $user_id, $current_points, $current_xp, $challenge);
            }
        }
    }
}

if (!function_exists('award_challenge_rewards')) {
    function award_challenge_rewards($pdo, $student_id, $user_id, $current_points, $current_xp, $challenge) {
        $new_points = $current_points + $challenge['points_reward'];
        $new_xp = $current_xp + $challenge['xp_reward'];
        
        $up = $pdo->prepare("UPDATE students SET points = ?, xp = ? WHERE id = ?");
        $up->execute([$new_points, $new_xp, $student_id]);
        
        $ins_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $ins_notif->execute([
            $user_id,
            'Daily Challenge Completed!',
            "Congratulations! You completed today's challenge: '{$challenge['description']}' and earned +{$challenge['points_reward']} Points and +{$challenge['xp_reward']} XP!",
            'badge'
        ]);
    }
}

// 4. Initialization Logic for Logged-In Student
if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['user_id']) && isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Student') {
    $user_id = $_SESSION['user_id'];
    
    // Get Student ID
    $s_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $s_stmt->execute([$user_id]);
    $student_id = $s_stmt->fetchColumn();
    
    if ($student_id) {
        $challenge = get_or_generate_daily_challenge($pdo);
        if ($challenge) {
            // Check if record exists in student_challenges
            $sc_stmt = $pdo->prepare("SELECT * FROM student_challenges WHERE student_id = ? AND challenge_id = ?");
            $sc_stmt->execute([$student_id, $challenge['id']]);
            $sc = $sc_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sc) {
                // Determine initial progress and status
                $initial_progress = 0;
                $status = 'in_progress';
                
                // If it is daily_login, it's completed on login!
                if ($challenge['target_type'] === 'daily_login') {
                    $initial_progress = 1;
                    $status = 'completed';
                }
                
                $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
                
                $ins = $pdo->prepare("
                    INSERT INTO student_challenges (student_id, challenge_id, status, progress_value, completed_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ins->execute([$student_id, $challenge['id'], $status, $initial_progress, $completed_at]);
                
                if ($status === 'completed') {
                    // Fetch current stats to award rewards
                    $stud_stmt = $pdo->prepare("SELECT points, xp FROM students WHERE id = ?");
                    $stud_stmt->execute([$student_id]);
                    $student = $stud_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($student) {
                        award_challenge_rewards($pdo, $student_id, $user_id, intval($student['points']), intval($student['xp']), $challenge);
                    }
                }
            } else {
                // If the challenge is daily_login and it's not yet completed, complete it
                if ($challenge['target_type'] === 'daily_login' && $sc['status'] !== 'completed') {
                    $up = $pdo->prepare("
                        UPDATE student_challenges 
                        SET progress_value = 1, status = 'completed', completed_at = ? 
                        WHERE student_id = ? AND challenge_id = ?
                    ");
                    $up->execute([date('Y-m-d H:i:s'), $student_id, $challenge['id']]);
                    
                    $stud_stmt = $pdo->prepare("SELECT points, xp FROM students WHERE id = ?");
                    $stud_stmt->execute([$student_id]);
                    $student = $stud_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($student) {
                        award_challenge_rewards($pdo, $student_id, $user_id, intval($student['points']), intval($student['xp']), $challenge);
                    }
                }
            }
        }
    }
}
?>
