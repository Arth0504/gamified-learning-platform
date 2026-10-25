<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

if ($student_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Student account not found.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if ($course_id === 0 || empty($title) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields (course, rating, title, message) are required.']);
        exit();
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars.']);
        exit();
    }
    
    try {
        // Enforce restriction: One feedback per course completion
        $check = $pdo->prepare("SELECT id FROM course_feedback WHERE student_id = ? AND course_id = ?");
        $check->execute([$student_id, $course_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already submitted feedback for this course.']);
            exit();
        }
        
        // Insert feedback
        $ins = $pdo->prepare("
            INSERT INTO course_feedback (student_id, course_id, rating, title, message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$student_id, $course_id, $rating, $title, $message]);
        
        log_activity($pdo, $user_id, 'Submit Course Feedback', "Submitted feedback for course ID: $course_id");
        
        echo json_encode(['success' => true]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>
