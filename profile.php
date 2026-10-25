<?php
$page_title = "My Profile";
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

require_login();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role_name'];

// Fetch basic user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch role-specific details
$student_info = null;
$teacher_info = null;

if ($role === 'Student') {
    $stmt_student = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt_student->execute([$user_id]);
    $student_info = $stmt_student->fetch();
} elseif ($role === 'Teacher') {
    $stmt_teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
    $stmt_teacher->execute([$user_id]);
    $teacher_info = $stmt_teacher->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Teacher specific details
    $bio = trim($_POST['bio'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');

    // Student specific details
    $dob = trim($_POST['dob'] ?? null);
    $gender = trim($_POST['gender'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $roll_no = trim($_POST['roll_no'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $university_board = trim($_POST['university_board'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $education_level = trim($_POST['education_level'] ?? '');
    $preferred_language = trim($_POST['preferred_language'] ?? '');
    $learning_goal = trim($_POST['learning_goal'] ?? '');
    $skill_level = trim($_POST['skill_level'] ?? '');
    
    $interests_arr = $_POST['interests'] ?? [];
    $interests_str = !empty($interests_arr) ? implode(',', array_map('trim', $interests_arr)) : '';

    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } elseif (empty($full_name) || empty($email)) {
        set_flash_message('danger', 'Full Name and Email are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('danger', 'Invalid email format.');
    } elseif ($role === 'Student' && !empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        set_flash_message('danger', 'Mobile number must be a valid 10-digit number.');
    } elseif ($role === 'Student' && !empty($dob) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        set_flash_message('danger', 'Date of Birth must be in YYYY-MM-DD format.');
    } else {
        try {
            // Check if email taken by another user
            $email_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $email_stmt->execute([$email, $user_id]);
            if ($email_stmt->fetch()) {
                set_flash_message('danger', 'Email is already taken by another user.');
            } else {
                $pdo->beginTransaction();
                
                // Handle profile photo upload
                $profile_pic = $user['profile_pic'];
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['profile_pic']['tmp_name'];
                    $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_ext, $allowed_exts)) {
                        $upload_dir = __DIR__ . '/assets/uploads';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        if (move_uploaded_file($file_tmp, $upload_dir . '/' . $file_name)) {
                            $profile_pic = 'assets/uploads/' . $file_name;
                        }
                    } else {
                        throw new Exception('Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.');
                    }
                }

                // Update basic info
                $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, profile_pic = ? WHERE id = ?");
                $update_stmt->execute([$full_name, $email, $profile_pic, $user_id]);
                
                // Update password if provided
                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        throw new Exception(__('auth.passwords_dont_match'));
                    }
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $pass_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $pass_stmt->execute([$hashed, $user_id]);
                }
                
                // Update Teacher info
                if ($role === 'Teacher' && $teacher_info) {
                    $teacher_update = $pdo->prepare("UPDATE teachers SET bio = ?, specialization = ? WHERE user_id = ?");
                    $teacher_update->execute([$bio, $specialization, $user_id]);
                }

                // Update Student info
                if ($role === 'Student' && $student_info) {
                    $student_update = $pdo->prepare("
                        UPDATE students SET 
                            dob = ?, 
                            gender = ?, 
                            mobile_no = ?, 
                            address = ?, 
                            stream = ?, 
                            class = ?, 
                            semester = ?, 
                            enrollment_no = ?, 
                            roll_no = ?,
                            school_name = ?,
                            university_board = ?,
                            academic_year = ?,
                            education_level = ?,
                            preferred_language = ?,
                            learning_goal = ?,
                            skill_level = ?,
                            interests = ?
                        WHERE user_id = ?
                    ");
                    $student_update->execute([
                        $dob ?: null,
                        $gender ?: null,
                        $mobile_no ?: null,
                        $address ?: null,
                        $stream ?: null,
                        $class ?: null,
                        $semester ?: null,
                        $enrollment_no ?: null,
                        $roll_no ?: null,
                        $school_name ?: null,
                        $university_board ?: null,
                        $academic_year ?: null,
                        $education_level ?: null,
                        $preferred_language ?: null,
                        $learning_goal ?: null,
                        $skill_level ?: null,
                        $interests_str ?: null,
                        $user_id
                    ]);
                }
                
                $pdo->commit();
                
                // Update current session name & profile picture
                $_SESSION['full_name'] = $full_name;
                
                log_activity($pdo, $user_id, 'Profile Update', 'User updated profile details');
                set_flash_message('success', 'Profile updated successfully!');
                
                header("Location: profile.php");
                exit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Update failed: ' . $e->getMessage());
        }
    }
}

// Fetch dynamic ranks and counts if Student
$rank = 0;
$achievements_count = 0;
if ($role === 'Student' && $student_info) {
    $rank_stmt = $pdo->query("SELECT id FROM students ORDER BY points DESC, xp DESC, id ASC");
    $rank_list = $rank_stmt->fetchAll(PDO::FETCH_COLUMN);
    $rank = array_search($student_info['id'], $rank_list) + 1;

    $badges_query = $pdo->prepare("SELECT COUNT(*) FROM student_badges WHERE student_id = ?");
    $badges_query->execute([$student_info['id']]);
    $achievements_count = $badges_query->fetchColumn();
}
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="mb-lg">
        <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs font-bold">Profile Management</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Update your personal, academic details and account profile photo.</p>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Sidebar Column (Profile Card Summary) -->
        <div class="lg:col-span-4 space-y-md">
            <div class="glass-card rounded-xl p-md flex flex-col items-center text-center gap-sm">
                <!-- Avatar photo -->
                <div class="relative group w-28 h-28">
                    <?php if ($user['profile_pic'] && file_exists(__DIR__ . '/' . $user['profile_pic'])): ?>
                        <img id="avatar-preview" src="<?php echo e($user['profile_pic']); ?>" alt="Profile picture" class="w-full h-full object-cover rounded-full border-4 border-primary/20 shadow-md">
                    <?php else: ?>
                        <div id="avatar-fallback" class="w-full h-full rounded-full bg-primary/10 text-primary border-4 border-primary/20 flex items-center justify-center font-bold text-headline-lg shadow-md uppercase">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($user['full_name']); ?></h3>
                <span class="text-xs text-on-surface-variant font-mono">@<?php echo e($user['username']); ?></span>
                
                <?php if ($role === 'Student' && $student_info): ?>
                    <span class="inline-flex items-center gap-xs bg-error/10 text-error px-3 py-1 rounded-full text-xs font-bold border border-error/20 mt-xs">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                        <?php echo $student_info['streak_count']; ?> Day Streak
                    </span>

                    <div class="w-full grid grid-cols-2 gap-sm mt-sm border-t border-outline-variant/30 pt-sm">
                        <div class="p-xs text-center space-y-0.5">
                            <span class="material-symbols-outlined text-amber-500" style="font-variation-settings: 'FILL' 1;">stars</span>
                            <span class="text-[10px] text-on-surface-variant block uppercase font-semibold">Points</span>
                            <strong class="text-sm font-bold text-on-surface"><?php echo $student_info['points']; ?> Pts</strong>
                        </div>
                        <div class="p-xs text-center space-y-0.5">
                            <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">school</span>
                            <span class="text-[10px] text-on-surface-variant block uppercase font-semibold">Rank</span>
                            <strong class="text-sm font-bold text-on-surface">#<?php echo $rank; ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Column (Edit Information) -->
        <div class="lg:col-span-8">
            <div class="glass-card rounded-xl p-md md:p-lg space-y-md">
                <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">person_edit</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Edit Profile Information</h3>
                </div>

                <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-md m-0">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        <!-- Profile Pic -->
                        <div class="md:col-span-2">
                            <label for="profile_pic" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-xs">Profile Picture</label>
                            <input type="file" id="profile_pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp" class="block w-full text-sm text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 border border-outline-variant/30 rounded-lg p-xs">
                        </div>

                        <!-- Username (Disabled) -->
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-xs">Username</label>
                            <input type="text" class="w-full bg-surface-container/50 border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface-variant cursor-not-allowed" value="<?php echo e($user['username']); ?>" readonly disabled>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Email Address</label>
                            <input type="email" id="email" name="email" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($user['email']); ?>" required>
                        </div>

                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label for="full_name" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($user['full_name']); ?>" required>
                        </div>
                    </div>

                    <!-- Student Specific Details -->
                    <?php if ($role === 'Student' && $student_info): ?>
                        <div class="pt-md border-t border-outline-variant/20 space-y-md">
                            <h4 class="font-headline-sm text-body-lg text-primary font-bold">Personal Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                                <!-- DOB -->
                                <div>
                                    <label for="dob" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Date of Birth</label>
                                    <input type="date" id="dob" name="dob" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['dob']); ?>">
                                </div>

                                <!-- Gender -->
                                <div>
                                    <label for="gender" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Gender</label>
                                    <select id="gender" name="gender" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo $student_info['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $student_info['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $student_info['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <!-- Mobile No -->
                                <div>
                                    <label for="mobile_no" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Mobile Number</label>
                                    <input type="tel" id="mobile_no" name="mobile_no" pattern="[0-9]{10}" placeholder="e.g. 9876543210" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['mobile_no']); ?>">
                                </div>

                                <!-- Address -->
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Address</label>
                                    <textarea id="address" name="address" rows="3" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface"><?php echo e($student_info['address']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pt-md border-t border-outline-variant/20 space-y-md">
                            <h4 class="font-headline-sm text-body-lg text-primary font-bold">Academic Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                                <!-- School / College Name -->
                                <div class="md:col-span-2">
                                    <label for="school_name" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">School / College Name</label>
                                    <input type="text" id="school_name" name="school_name" placeholder="e.g. Government High School" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['school_name'] ?? ''); ?>">
                                </div>

                                <!-- University / Board -->
                                <div>
                                    <label for="university_board" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">University / Board</label>
                                    <input type="text" id="university_board" name="university_board" placeholder="e.g. GSEB, Gujarat University" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['university_board'] ?? ''); ?>">
                                </div>

                                <!-- Stream -->
                                <div>
                                    <label for="stream" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Stream / Department</label>
                                    <input type="text" id="stream" name="stream" placeholder="e.g. Science" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['stream']); ?>">
                                </div>

                                <!-- Class -->
                                <div>
                                    <label for="class" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Class / Standard</label>
                                    <input type="text" id="class" name="class" placeholder="e.g. 10th Standard" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['class']); ?>">
                                </div>

                                <!-- Semester -->
                                <div>
                                    <label for="semester" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Semester</label>
                                    <input type="text" id="semester" name="semester" placeholder="e.g. Semester 1" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['semester']); ?>">
                                </div>

                                <!-- Enrollment No -->
                                <div class="md:col-span-2">
                                    <label for="enrollment_no" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Enrollment Number</label>
                                    <input type="text" id="enrollment_no" name="enrollment_no" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['enrollment_no']); ?>">
                                </div>

                                <!-- Roll No -->
                                <div>
                                    <label for="roll_no" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Roll Number</label>
                                    <input type="text" id="roll_no" name="roll_no" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['roll_no']); ?>">
                                </div>

                                <!-- Academic Year -->
                                <div>
                                    <label for="academic_year" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Academic Year</label>
                                    <input type="text" id="academic_year" name="academic_year" placeholder="e.g. 2024-25" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface" value="<?php echo e($student_info['academic_year'] ?? ''); ?>">
                                </div>

                                <!-- Education Level -->
                                <div>
                                    <label for="education_level" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Education Level</label>
                                    <select id="education_level" name="education_level" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                        <option value="">Select Level</option>
                                        <option value="School" <?php echo ($student_info['education_level'] ?? '') === 'School' ? 'selected' : ''; ?>>School</option>
                                        <option value="Higher Secondary" <?php echo ($student_info['education_level'] ?? '') === 'Higher Secondary' ? 'selected' : ''; ?>>Higher Secondary</option>
                                        <option value="Diploma" <?php echo ($student_info['education_level'] ?? '') === 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                                        <option value="Undergraduate" <?php echo ($student_info['education_level'] ?? '') === 'Undergraduate' ? 'selected' : ''; ?>>Undergraduate</option>
                                        <option value="Postgraduate" <?php echo ($student_info['education_level'] ?? '') === 'Postgraduate' ? 'selected' : ''; ?>>Postgraduate</option>
                                    </select>
                                </div>

                                <!-- Preferred Language -->
                                <div>
                                    <label for="preferred_language" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Preferred Language</label>
                                    <select id="preferred_language" name="preferred_language" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                        <option value="">Select Language</option>
                                        <option value="English" <?php echo ($student_info['preferred_language'] ?? '') === 'English' ? 'selected' : ''; ?>>English</option>
                                        <option value="Hindi" <?php echo ($student_info['preferred_language'] ?? '') === 'Hindi' ? 'selected' : ''; ?>>Hindi</option>
                                        <option value="Gujarati" <?php echo ($student_info['preferred_language'] ?? '') === 'Gujarati' ? 'selected' : ''; ?>>Gujarati</option>
                                    </select>
                                </div>

                                <!-- Learning Goal -->
                                <div class="md:col-span-2">
                                    <label for="learning_goal" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Learning Goal</label>
                                    <select id="learning_goal" name="learning_goal" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                        <option value="">Select Learning Goal</option>
                                        <option value="English Improvement" <?php echo ($student_info['learning_goal'] ?? '') === 'English Improvement' ? 'selected' : ''; ?>>English Improvement</option>
                                        <option value="Mathematics" <?php echo ($student_info['learning_goal'] ?? '') === 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                        <option value="Science" <?php echo ($student_info['learning_goal'] ?? '') === 'Science' ? 'selected' : ''; ?>>Science</option>
                                        <option value="Computer Skills" <?php echo ($student_info['learning_goal'] ?? '') === 'Computer Skills' ? 'selected' : ''; ?>>Computer Skills</option>
                                        <option value="Exam Preparation" <?php echo ($student_info['learning_goal'] ?? '') === 'Exam Preparation' ? 'selected' : ''; ?>>Exam Preparation</option>
                                        <option value="General Learning" <?php echo ($student_info['learning_goal'] ?? '') === 'General Learning' ? 'selected' : ''; ?>>General Learning</option>
                                    </select>
                                </div>

                                <!-- Skill Level -->
                                <div>
                                    <label for="skill_level" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Skill Level</label>
                                    <select id="skill_level" name="skill_level" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                        <option value="">Select Skill Level</option>
                                        <option value="Beginner" <?php echo ($student_info['skill_level'] ?? '') === 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                                        <option value="Intermediate" <?php echo ($student_info['skill_level'] ?? '') === 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="Advanced" <?php echo ($student_info['skill_level'] ?? '') === 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                                    </select>
                                </div>

                                <!-- Interests (Multi-select Checkboxes) -->
                                <div class="md:col-span-3 mt-sm">
                                    <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-sm">Interests (Select multiple)</label>
                                    <?php 
                                    $interests_saved = explode(',', $student_info['interests'] ?? '');
                                    $interests_saved = array_map('trim', $interests_saved);
                                    $interest_options = ['English', 'Mathematics', 'Science', 'Computer', 'General Knowledge'];
                                    ?>
                                    <div class="flex flex-wrap gap-md">
                                        <?php foreach ($interest_options as $interest): ?>
                                            <label class="flex items-center gap-xs cursor-pointer group bg-surface-container/20 px-md py-2 rounded-xl border border-outline-variant/20 hover:border-primary/50 transition-colors m-0">
                                                <input type="checkbox" name="interests[]" value="<?php echo $interest; ?>" <?php echo in_array($interest, $interests_saved) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-outline-variant/50 focus:ring-primary/20">
                                                <span class="text-body-sm text-on-surface select-none font-medium"><?php echo $interest; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Teacher Specific Fields -->
                    <?php if ($role === 'Teacher' && $teacher_info): ?>
                        <div class="pt-md border-t border-outline-variant/20 space-y-md">
                            <h4 class="font-headline-sm text-body-lg text-primary font-bold">Teacher Details</h4>
                            <div class="space-y-md">
                                <div>
                                    <label for="specialization" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Specialization</label>
                                    <input type="text" id="specialization" name="specialization" value="<?php echo e($teacher_info['specialization']); ?>" placeholder="e.g. Mathematics, Science" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                                </div>
                                
                                <div>
                                    <label for="bio" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Biography</label>
                                    <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..." class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface"><?php echo e($teacher_info['bio']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Password Change -->
                    <div class="pt-md border-t border-outline-variant/20 space-y-md">
                        <h4 class="font-headline-sm text-body-lg text-primary font-bold">Security Settings <span class="text-xs text-on-surface-variant font-normal normal-case">(Leave blank if unchanged)</span></h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                            <div>
                                <label for="new_password" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-xs font-semibold text-on-surface uppercase tracking-wider mb-xs">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="w-full bg-surface border border-outline-variant/30 rounded-lg px-md py-2.5 font-body-md text-on-surface">
                            </div>
                        </div>
                    </div>

                    <div class="pt-md border-t border-outline-variant/20">
                        <button type="submit" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2.5 rounded-lg font-label-md text-label-md transition-colors shadow-md">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Save Profile Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
