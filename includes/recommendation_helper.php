<?php
/**
 * Recommendation Engine helper functions for EduQuest.
 */

if (!function_exists('get_profile_recommendations')) {
    function get_profile_recommendations($student_id, $pdo) {
        // Fetch student profile details
        $stmt = $pdo->prepare("
            SELECT stream, class, semester, learning_goal, interests, skill_level, education_level 
            FROM students 
            WHERE id = ?
        ");
        $stmt->execute([$student_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            return [
                'courses' => [],
                'curated_pathways' => []
            ];
        }

        $stream = strtolower(trim($profile['stream'] ?? ''));
        $class = strtolower(trim($profile['class'] ?? ''));
        $education_level = trim($profile['education_level'] ?? '');
        $learning_goal = trim($profile['learning_goal'] ?? '');
        $skill_level = trim($profile['skill_level'] ?? '');
        $interests_str = trim($profile['interests'] ?? '');
        
        $interests = !empty($interests_str) ? array_map('trim', explode(',', $interests_str)) : [];

        // 1. QUERY EXISTING DATABASE COURSES THAT MATCH PROFILE
        // Get list of courses student is already enrolled in to exclude them
        $enroll_stmt = $pdo->prepare("SELECT course_id FROM course_enrollments WHERE student_id = ?");
        $enroll_stmt->execute([$student_id]);
        $enrolled_course_ids = $enroll_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Fetch all published courses
        $courses_stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS teacher_name 
            FROM courses c
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE c.status = 'published'
        ");
        $courses_stmt->execute();
        $all_courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

        $matched_courses = [];

        foreach ($all_courses as $course) {
            // Exclude already enrolled courses
            if (in_array($course['id'], $enrolled_course_ids)) {
                continue;
            }

            $score = 0;
            $course_title = strtolower($course['title']);
            $course_cat = strtolower($course['category']);
            $course_diff = strtolower($course['difficulty_level']);

            // Match learning goal with category or title
            if (!empty($learning_goal)) {
                if (stripos($course_cat, $learning_goal) !== false || stripos($course_title, $learning_goal) !== false) {
                    $score += 3;
                }
            }

            // Match interests
            foreach ($interests as $interest) {
                if (stripos($course_cat, $interest) !== false || stripos($course_title, $interest) !== false) {
                    $score += 2;
                }
            }

            // Match skill level with difficulty level
            // Beginner -> easy, Intermediate -> medium, Advanced -> hard
            if ($skill_level === 'Beginner' && $course_diff === 'easy') {
                $score += 1;
            } elseif ($skill_level === 'Intermediate' && $course_diff === 'medium') {
                $score += 1;
            } elseif ($skill_level === 'Advanced' && $course_diff === 'hard') {
                $score += 1;
            }

            if ($score > 0) {
                $course['match_score'] = $score;
                $matched_courses[] = $course;
            }
        }

        // Sort matched courses by match score descending
        usort($matched_courses, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // Limit to top 3 database course recommendations
        $matched_courses = array_slice($matched_courses, 0, 3);

        // 2. COMPILE CURATED PATHWAY RECOMMENDATIONS (MAPPED PATHWAYS)
        $curated_pathways = [];

        // A. MCA Student / Postgraduate
        if ($stream === 'mca' || strpos($stream, 'postgraduate') !== false || $education_level === 'Postgraduate') {
            $curated_pathways = [
                [
                    'title' => 'Full Stack Development',
                    'icon' => 'code',
                    'description' => 'Learn to construct frontend interfaces, database backends, and full web systems.',
                    'level' => 'Advanced'
                ],
                [
                    'title' => 'Database Management Systems (DBMS)',
                    'icon' => 'database',
                    'description' => 'Master SQL schema definitions, entity relationships, query joins, and indexing structures.',
                    'level' => 'Intermediate'
                ],
                [
                    'title' => 'Modern Web Development',
                    'icon' => 'html',
                    'description' => 'Create responsive site designs using grids, CSS, JavaScript, and dynamic server routing.',
                    'level' => 'Intermediate'
                ],
                [
                    'title' => 'Programming Fundamentals',
                    'icon' => 'terminal',
                    'description' => 'Understand OOP design patterns, classes, methods, arrays, and general programming syntax.',
                    'level' => 'Beginner'
                ]
            ];
        } 
        // B. BCA Student / Undergraduate
        elseif ($stream === 'bca' || strpos($stream, 'undergraduate') !== false || $education_level === 'Undergraduate') {
            $curated_pathways = [
                [
                    'title' => 'C Programming Logic',
                    'icon' => 'integration_instructions',
                    'description' => 'Learn structured procedural coding, logic loops, variables, pointers, and memory storage.',
                    'level' => 'Beginner'
                ],
                [
                    'title' => 'Java Object Oriented Basics',
                    'icon' => 'coffee',
                    'description' => 'Implement inheritances, interfaces, polymorphism, encapsulation, and exception flows in Java.',
                    'level' => 'Intermediate'
                ],
                [
                    'title' => 'Database Fundamentals',
                    'icon' => 'storage',
                    'description' => 'Build simple tables, define primary keys, execute SELECT statements, and organize structured schemas.',
                    'level' => 'Beginner'
                ]
            ];
        } 
        // C. 10th Standard / School
        elseif ($class === '10' || $class === '10th' || strpos($class, '10th') !== false || $education_level === 'School') {
            $curated_pathways = [
                [
                    'title' => 'English Grammar Mastery',
                    'icon' => 'translate',
                    'description' => 'Improve parts of speech, active/passive voices, correct tenses, and spelling layouts.',
                    'level' => 'Intermediate'
                ],
                [
                    'title' => 'Core School Mathematics',
                    'icon' => 'calculate',
                    'description' => 'Study trigonometry equations, quadratic functions, basic geometry theorems, and fractions.',
                    'level' => 'Intermediate'
                ],
                [
                    'title' => 'Science Foundation',
                    'icon' => 'science',
                    'description' => 'Examine forces, motion equations, acids/bases reactions, biological cells, and simple organ functions.',
                    'level' => 'Beginner'
                ]
            ];
        }
        
        // D. Fallback if no specific pathway matched (general learning or goal matches)
        if (empty($curated_pathways)) {
            // General pathway based on interests/learning goals
            if ($learning_goal === 'English Improvement' || in_array('English', $interests)) {
                $curated_pathways[] = [
                    'title' => 'English Reading & Writing',
                    'icon' => 'menu_book',
                    'description' => 'Learn simple reading comprehensions and construct clean written compositions.',
                    'level' => 'Beginner'
                ];
            }
            if ($learning_goal === 'Mathematics' || in_array('Mathematics', $interests)) {
                $curated_pathways[] = [
                    'title' => 'Basic Arithmetic Calculations',
                    'icon' => 'calculate',
                    'description' => 'Work out multi-digit addition, subtraction, simple division ratios, and decimal units.',
                    'level' => 'Beginner'
                ];
            }
            if ($learning_goal === 'Computer Skills' || in_array('Computer', $interests)) {
                $curated_pathways[] = [
                    'title' => 'Basic Computer Operations',
                    'icon' => 'desktop_windows',
                    'description' => 'Learn mouse movements, keyboard entries, directory trees, files saving, and safe web searching.',
                    'level' => 'Beginner'
                ];
            }
            
            // Add a general curiosity card if still empty
            if (empty($curated_pathways)) {
                $curated_pathways[] = [
                    'title' => 'General Knowledge Quests',
                    'icon' => 'public',
                    'description' => 'Broaden your horizons by studying history, geography, space, and general trivia facts.',
                    'level' => 'Beginner'
                ];
            }
        }

        return [
            'courses' => $matched_courses,
            'curated_pathways' => $curated_pathways
        ];
    }
}
?>
