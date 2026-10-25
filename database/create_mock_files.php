<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/fpdf.php';

// Ensure uploads/resources directory exists
$upload_dir = __DIR__ . '/../uploads/resources/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 1. Create worksheet.pdf
$pdf1 = new FPDF();
$pdf1->AddPage();
$pdf1->SetFont('Arial', 'B', 16);
$pdf1->Cell(40, 10, 'Printable Math Worksheet');
$pdf1->Ln(10);
$pdf1->SetFont('Arial', '', 12);
$pdf1->Cell(0, 10, 'Addition Practice Questions: 2 + 2 = ?');
$pdf1->Output('F', $upload_dir . 'worksheet.pdf');
echo "Created worksheet.pdf\n";

// 2. Create notes.pdf
$pdf2 = new FPDF();
$pdf2->AddPage();
$pdf2->SetFont('Arial', 'B', 16);
$pdf2->Cell(40, 10, 'Study Notes');
$pdf2->Ln(10);
$pdf2->SetFont('Arial', '', 12);
$pdf2->Cell(0, 10, 'Mathematics & English Study Guides and Key Concepts.');
$pdf2->Output('F', $upload_dir . 'notes.pdf');
echo "Created notes.pdf\n";

// 3. Update database records
try {
    // Math Worksheet
    $size1 = filesize($upload_dir . 'worksheet.pdf');
    $stmt1 = $pdo->prepare("UPDATE rewards SET file_path = 'uploads/resources/worksheet.pdf', file_size = ?, resource_type = 'worksheet' WHERE id = 1");
    $stmt1->execute([$size1]);
    echo "Updated reward 1 in database.\n";
    
    // E-Certificate
    $stmt2 = $pdo->prepare("UPDATE rewards SET resource_type = 'certificate' WHERE id = 2");
    $stmt2->execute();
    echo "Updated reward 2 in database.\n";
    
    // Study Notes (Insert if not exists)
    $stmt_check = $pdo->prepare("SELECT id FROM rewards WHERE title = 'Study Notes'");
    $stmt_check->execute();
    if (!$stmt_check->fetch()) {
        $size3 = filesize($upload_dir . 'notes.pdf');
        $stmt3 = $pdo->prepare("INSERT INTO rewards (title, description, cost_points, image_path, file_path, file_size, resource_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt3->execute([
            'Study Notes',
            'Get key learning summaries for mathematics and english.',
            50,
            'notes.png',
            'uploads/resources/notes.pdf',
            $size3,
            'notes'
        ]);
        echo "Inserted new reward 'Study Notes' in database.\n";
    } else {
        $size3 = filesize($upload_dir . 'notes.pdf');
        $stmt3 = $pdo->prepare("UPDATE rewards SET file_path = 'uploads/resources/notes.pdf', file_size = ?, resource_type = 'notes' WHERE title = 'Study Notes'");
        $stmt3->execute([$size3]);
        echo "Updated Study Notes in database.\n";
    }
} catch (Exception $e) {
    echo "Error updating records: " . $e->getMessage() . "\n";
}
?>
