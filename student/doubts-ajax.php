<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/middleware.php';

// Enforce student role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['Student', 'Admin', 'Teacher'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT l.id, l.title, s.title AS subject_title 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            WHERE s.course_id = ?
            ORDER BY s.sort_order ASC, l.sort_order ASC
        ");
        $stmt->execute([$course_id]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($lessons);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode([]);
    exit();
}
?>
