<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Force student role access
require_role('Student');

$user_id = $_SESSION['user_id'];
$reward_id = intval($_GET['reward_id'] ?? 0);

if (!$reward_id) {
    die("Error: Missing Reward ID.");
}

// Get Student ID
$st_stmt = $pdo->prepare("SELECT id, user_id FROM students WHERE user_id = ?");
$st_stmt->execute([$user_id]);
$student = $st_stmt->fetch();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    die("Error: Student profile not found.");
}

// 1. SECURITY: Verify user has redeemed/unlocked the item
$verify_stmt = $pdo->prepare("
    SELECT sr.*, r.title, r.file_path, r.resource_type, r.file_size 
    FROM student_rewards sr 
    JOIN rewards r ON sr.reward_id = r.id 
    WHERE sr.student_id = ? AND sr.reward_id = ?
");
$verify_stmt->execute([$student_id, $reward_id]);
$unlock_info = $verify_stmt->fetch();

if (!$unlock_info) {
    // If not unlocked, output custom message
    set_flash_message('danger', 'You must redeem this item first.');
    header("Location: achievements.php");
    exit();
}

$resource_type = $unlock_info['resource_type'];

// 2. CERTIFICATE GENERATION: Generate E-Certificate dynamically
if ($resource_type === 'certificate') {
    require_once __DIR__ . '/../includes/fpdf.php';
    
    // Fetch latest completed course details for this student
    $course_stmt = $pdo->prepare("
        SELECT c.title AS course_name, ce.completed_at, u.full_name AS instructor_name, c.id AS course_id
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ce.student_id = ? AND ce.status = 'completed'
        ORDER BY ce.completed_at DESC LIMIT 1
    ");
    $course_stmt->execute([$student_id]);
    $course_info = $course_stmt->fetch();
    
    if ($course_info) {
        $course_name = $course_info['course_name'];
        $completion_date = date('M d, Y', strtotime($course_info['completed_at']));
        $instructor_name = $course_info['instructor_name'];
        $course_id = $course_info['course_id'];
    } else {
        // Fallback in case course completion validation was bypassed
        $course_name = 'EduQuest Learning Pathway';
        $completion_date = date('M d, Y');
        $instructor_name = 'EduQuest Faculty';
        $course_id = 0;
    }
    
    // Fetch student's full name
    $user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $user_stmt->execute([$unlock_info['student_id'] ? $user_id : $user_id]); // Keep simple user mapping
    $user_info = $user_stmt->fetch();
    $student_name = $user_info['full_name'] ?? 'Valued Student';
    
    $certificate_id = 'CERT-' . $student_id . '-' . ($course_id ? $course_id : '0') . '-' . date('Ymd');
    
    // Generate Landscape A4 PDF
    class CertificatePDF extends FPDF {
        function Header() {
            // Elegant green outer border
            $this->SetDrawColor(25, 135, 84); // theme green
            $this->SetLineWidth(2);
            $this->Rect(10, 10, 277, 190);
            
            // Elegant amber inner border
            $this->SetDrawColor(245, 158, 11); // theme amber
            $this->SetLineWidth(1);
            $this->Rect(12, 12, 273, 186);
        }
    }
    
    $pdf = new CertificatePDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    $pdf->Ln(15);
    
    // Certificate Title
    $pdf->SetFont('Arial', 'B', 32);
    $pdf->SetTextColor(25, 135, 84);
    $pdf->Cell(0, 15, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Decorative star line
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(245, 158, 11);
    $pdf->Cell(0, 10, '* * * * * * * * * * * * * * * * * * * * * * *', 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Presented to label
    $pdf->SetFont('Arial', 'I', 16);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 10, 'This is proudly presented to', 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Student Name
    $pdf->SetFont('Arial', 'B', 28);
    $pdf->SetTextColor(17, 24, 39);
    $pdf->Cell(0, 15, strtoupper($student_name), 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Course completion phrase
    $pdf->SetFont('Arial', 'I', 15);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 10, 'for successfully completing the learning quest course', 0, 1, 'C');
    
    $pdf->Ln(3);
    
    // Course Title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(25, 135, 84);
    $pdf->Cell(0, 12, '"' . $course_name . '"', 0, 1, 'C');
    
    $pdf->Ln(15);
    
    // Signatures
    $pdf->SetY(145);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(107, 114, 128);
    
    // Date Left
    $pdf->SetX(30);
    $pdf->Cell(100, 8, 'Completion Date: ' . $completion_date, 0, 0, 'L');
    
    // Instructor Right
    $pdf->SetX(167);
    $pdf->Cell(100, 8, 'Instructor Name: ' . $instructor_name, 0, 1, 'R');
    
    $pdf->SetY(153);
    $pdf->SetX(30);
    $pdf->Cell(100, 1, '___________________________', 0, 0, 'L');
    
    $pdf->SetX(167);
    $pdf->Cell(100, 1, '___________________________', 0, 1, 'R');
    
    // Certificate ID at bottom center
    $pdf->SetY(175);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(156, 163, 175);
    $pdf->Cell(0, 10, 'Official Certificate ID: ' . $certificate_id, 0, 1, 'C');
    
    // Force PDF download
    $filename = "Certificate_" . preg_replace('/[^a-zA-Z0-9]/', '_', $student_name) . ".pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output('I', $filename);
    exit();
}

// 3. STATIC RESOURCES: Worksheets, Notes, PDFs
$raw_file_path = $unlock_info['file_path'] ?? '';
if (empty($raw_file_path)) {
    die("Error: This resource does not have an associated file path.");
}

$basename = basename($raw_file_path);
$full_physical_path = __DIR__ . '/../uploads/resources/' . $basename;

if (!file_exists($full_physical_path)) {
    die("Error: The requested file could not be found on the server. Physical path checked: uploads/resources/" . $basename);
}

// Clean buffer to avoid corrup files
if (ob_get_level()) {
    ob_end_clean();
}

// Force File Download
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $basename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($full_physical_path));
readfile($full_physical_path);
exit();
?>
