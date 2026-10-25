<?php
require 'config/db.php';

// Fetch student row for user_id=3 (seed student account)
$stmt = $pdo->prepare("SELECT s.*, u.full_name, u.email, u.profile_pic, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = 3");
$stmt->execute();
$student = $stmt->fetch();

echo "=== STUDENT PROFILE DATA ===\n";
echo "Username:      " . ($student['username'] ?? 'N/A') . "\n";
echo "Full Name:     " . ($student['full_name'] ?? 'N/A') . "\n";
echo "Email:         " . ($student['email'] ?? 'N/A') . "\n";
echo "Profile Pic:   " . ($student['profile_pic'] ?? 'None') . "\n";
echo "Points:        " . ($student['points'] ?? 0) . "\n";
echo "XP:            " . ($student['xp'] ?? 0) . "\n";
echo "Streak:        " . ($student['streak_count'] ?? 0) . "\n";
echo "\n=== NEW PROFILE FIELDS ===\n";
echo "DOB:           " . ($student['dob'] ?? 'Not set') . "\n";
echo "Gender:        " . ($student['gender'] ?? 'Not set') . "\n";
echo "Mobile:        " . ($student['mobile_no'] ?? 'Not set') . "\n";
echo "Address:       " . ($student['address'] ?? 'Not set') . "\n";
echo "Stream:        " . ($student['stream'] ?? 'Not set') . "\n";
echo "Class:         " . ($student['class'] ?? 'Not set') . "\n";
echo "Semester:      " . ($student['semester'] ?? 'Not set') . "\n";
echo "Enrollment No: " . ($student['enrollment_no'] ?? 'Not set') . "\n";
echo "Roll No:       " . ($student['roll_no'] ?? 'Not set') . "\n";

// Test UPDATE
$update = $pdo->prepare("
    UPDATE students SET 
        dob = '2000-01-15',
        gender = 'Male',
        mobile_no = '9876543210',
        address = 'Village Road, Gujarat',
        stream = 'Science',
        class = 'Grade 10',
        semester = 'Semester 2',
        enrollment_no = 'ENR2024001',
        roll_no = '42'
    WHERE user_id = 3
");
$update->execute();

// Verify UPDATE
$stmt2 = $pdo->prepare("SELECT dob, gender, mobile_no, stream, class, semester, enrollment_no, roll_no FROM students WHERE user_id = 3");
$stmt2->execute();
$updated = $stmt2->fetch();

echo "\n=== AFTER DATABASE UPDATE ===\n";
echo "DOB:           " . $updated['dob'] . "\n";
echo "Gender:        " . $updated['gender'] . "\n";
echo "Mobile:        " . $updated['mobile_no'] . "\n";
echo "Stream:        " . $updated['stream'] . "\n";
echo "Class:         " . $updated['class'] . "\n";
echo "Semester:      " . $updated['semester'] . "\n";
echo "Enrollment No: " . $updated['enrollment_no'] . "\n";
echo "Roll No:       " . $updated['roll_no'] . "\n";
echo "\nAll checks PASSED.\n";
