<?php
$page_title = "Manage Quizzes";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? $_POST['course_id'] ?? null;
$lesson_id_filter = $_GET['lesson_id'] ?? $_POST['lesson_id'] ?? null;

if (!$course_id) {
    set_flash_message('danger', 'Course ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Verify Course Ownership
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$course_stmt->execute([$course_id, $teacher_id]);
$course = $course_stmt->fetch();

if (!$course) {
    set_flash_message('danger', 'Course not found or unauthorized access.');
    header("Location: courses.php");
    exit();
}

// Get all lessons in this course for the dropdown list
$lessons_stmt = $pdo->prepare("
    SELECT l.id, l.title, l.subject_id, s.title AS subject_title 
    FROM lessons l 
    JOIN subjects s ON l.subject_id = s.id 
    WHERE s.course_id = ? 
    ORDER BY s.sort_order ASC, l.sort_order ASC
");
$lessons_stmt->execute([$course_id]);
$course_lessons = $lessons_stmt->fetchAll();

// Get all subjects in this course for AI generator dropdown
$subjects_stmt = $pdo->prepare("SELECT id, title FROM subjects WHERE course_id = ? ORDER BY sort_order ASC");
$subjects_stmt->execute([$course_id]);
$course_subjects = $subjects_stmt->fetchAll();

// Handle Form Submissions
$action = $_GET['action'] ?? 'list';
$edit_quiz = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : null;
        $time_limit_mins = intval($_POST['time_limit_mins'] ?? 10);
        $passing_score = intval($_POST['passing_score'] ?? 50);
        $max_attempts = intval($_POST['max_attempts'] ?? 3);
        $points_reward = intval($_POST['points_reward'] ?? 50);
        $xp_reward = intval($_POST['xp_reward'] ?? 100);

        if (empty($title)) {
            set_flash_message('danger', 'Quiz Title is required.');
        } elseif (isset($_POST['quiz_id'])) {
            // Edit Quiz
            $quiz_id = $_POST['quiz_id'];
            
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND course_id = ?");
            $check->execute([$quiz_id, $course_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE quizzes 
                    SET title = ?, description = ?, lesson_id = ?, time_limit_mins = ?, passing_score = ?, max_attempts = ?, points_reward = ?, xp_reward = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $lesson_id, $time_limit_mins, $passing_score, $max_attempts, $points_reward, $xp_reward, $quiz_id]);
                
                log_activity($pdo, $user_id, 'Edit Quiz', "Updated quiz: $title (ID: $quiz_id)");
                set_flash_message('success', 'Quiz updated successfully!');
                header("Location: quizzes.php?course_id=" . $course_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        } else {
            // Add Quiz
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (lesson_id, course_id, title, description, time_limit_mins, passing_score, max_attempts, points_reward, xp_reward) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$lesson_id, $course_id, $title, $description, $time_limit_mins, $passing_score, $max_attempts, $points_reward, $xp_reward]);
            
            log_activity($pdo, $user_id, 'Create Quiz', "Created quiz: $title under course: " . $course['title']);
            set_flash_message('success', 'Quiz created successfully!');
            header("Location: quizzes.php?course_id=" . $course_id);
            exit();
        }
    }
}

// Handle Delete Quiz
if ($action === 'delete' && isset($_GET['id'])) {
    $quiz_id = $_GET['id'];
    
    // Verify ownership
    $check = $pdo->prepare("SELECT id, title FROM quizzes WHERE id = ? AND course_id = ?");
    $check->execute([$quiz_id, $course_id]);
    $qz = $check->fetch();
    
    if ($qz) {
        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        
        log_activity($pdo, $user_id, 'Delete Quiz', "Deleted quiz: " . $qz['title'] . " (ID: $quiz_id)");
        set_flash_message('success', 'Quiz deleted successfully!');
    } else {
        set_flash_message('danger', 'Unauthorized or quiz not found.');
    }
    header("Location: quizzes.php?course_id=" . $course_id);
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $quiz_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND course_id = ?");
    $stmt->execute([$quiz_id, $course_id]);
    $edit_quiz = $stmt->fetch();
    if (!$edit_quiz) {
        set_flash_message('danger', 'Quiz not found.');
        header("Location: quizzes.php?course_id=" . $course_id);
        exit();
    }
}

// Get Course Quizzes
$stmt = $pdo->prepare("
    SELECT q.*, l.title AS lesson_title, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as questions_count 
    FROM quizzes q 
    LEFT JOIN lessons l ON q.lesson_id = l.id 
    WHERE q.course_id = ? 
    ORDER BY q.created_at DESC
");
$stmt->execute([$course_id]);
$quizzes = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="courses.php" class="text-success text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item text-secondary active" aria-current="page"><?php echo e($course['title']); ?></li>
            </ol>
        </nav>
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Quiz' : ($action === 'add' ? 'Add Quiz' : 'Course Quizzes'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end d-flex flex-wrap align-items-end justify-content-md-end gap-2">
        <?php if ($action === 'list'): ?>
            <button class="btn btn-gradient text-white flex items-center gap-xs font-semibold px-3 py-2 border-0" type="button" data-bs-toggle="modal" data-bs-target="#aiQuizModal">
                <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                Generate Quiz with AI
            </button>
            <a href="quizzes.php?course_id=<?php echo $course_id; ?>&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e(__('teacher.add_quiz')); ?>
            </a>
            <a href="courses.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Courses
            </a>
        <?php else: ?>
            <a href="quizzes.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST QUIZZES VIEW -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($quizzes)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-question-square text-secondary display-4 mb-3"></i>
                        <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                        <a href="quizzes.php?course_id=<?php echo $course_id; ?>&action=add" class="btn btn-primary">Add Your First Quiz</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Linked Lesson</th>
                                    <th>Time Limit</th>
                                    <th>Passing Score</th>
                                    <th>Rewards</th>
                                    <th>Questions</th>
                                    <th style="width: 300px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizzes as $q): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark-theme-compatible"><?php echo e($q['title']); ?></strong>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php echo $q['lesson_title'] ? e($q['lesson_title']) : '<span class="text-muted italic">General Course Quiz</span>'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $q['time_limit_mins']; ?> mins</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark"><?php echo $q['passing_score']; ?>%</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">+<?php echo $q['points_reward']; ?> Pts</span>
                                            <span class="badge bg-success">+<?php echo $q['xp_reward']; ?> XP</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?php echo $q['questions_count']; ?> Questions</span>
                                        </td>
                                        <td>
                                            <a href="questions.php?quiz_id=<?php echo $q['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-list-task me-1"></i> Questions
                                            </a>
                                            <a href="quizzes.php?course_id=<?php echo $course_id; ?>&action=edit&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-secondary mx-1" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="quizzes.php?course_id=<?php echo $course_id; ?>&action=delete&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this quiz and all its questions?');" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD/EDIT FORM VIEW -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form action="quizzes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <?php if ($edit_quiz): ?>
                        <input type="hidden" name="quiz_id" value="<?php echo $edit_quiz['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label font-heading">Quiz Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_quiz ? e($edit_quiz['title']) : ''; ?>" placeholder="e.g. Assessment 1: Counting Math" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Briefly introduce the quiz rules..."><?php echo $edit_quiz ? e($edit_quiz['description']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lesson_id" class="form-label font-heading">Link to Specific Lesson <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" id="lesson_id" name="lesson_id">
                                <option value="">-- General Course Quiz --</option>
                                <?php foreach ($course_lessons as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>" <?php 
                                        echo (($edit_quiz && $edit_quiz['lesson_id'] == $cl['id']) || ($lesson_id_filter == $cl['id'])) ? 'selected' : ''; 
                                    ?>>
                                        [<?php echo e($cl['subject_title']); ?>] <?php echo e($cl['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="time_limit_mins" class="form-label font-heading">Time Limit (Minutes)</label>
                            <input type="number" class="form-control" id="time_limit_mins" name="time_limit_mins" value="<?php echo $edit_quiz ? intval($edit_quiz['time_limit_mins']) : '10'; ?>" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="passing_score" class="form-label font-heading">Passing Percentage Score (%)</label>
                            <input type="number" class="form-control" id="passing_score" name="passing_score" value="<?php echo $edit_quiz ? intval($edit_quiz['passing_score']) : '50'; ?>" min="1" max="100" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="max_attempts" class="form-label font-heading">Maximum Allowed Attempts</label>
                            <input type="number" class="form-control" id="max_attempts" name="max_attempts" value="<?php echo $edit_quiz ? intval($edit_quiz['max_attempts']) : '3'; ?>" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="points_reward" class="form-label font-heading">Points Reward (On Passing)</label>
                            <input type="number" class="form-control" id="points_reward" name="points_reward" value="<?php echo $edit_quiz ? intval($edit_quiz['points_reward']) : '50'; ?>" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="xp_reward" class="form-label font-heading">XP Reward (On Passing)</label>
                            <input type="number" class="form-control" id="xp_reward" name="xp_reward" value="<?php echo $edit_quiz ? intval($edit_quiz['xp_reward']) : '100'; ?>" min="0" required>
                        </div>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Quiz</button>
                        <a href="quizzes.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- AI Quiz Generator Modal -->
<div class="modal fade" id="aiQuizModal" tabindex="-1" aria-labelledby="aiQuizModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-panel rounded-xl border-0 overflow-hidden shadow-2xl">
            <!-- Modal Header -->
            <div class="modal-header border-b border-outline-variant/20 px-lg py-md flex justify-between items-center bg-gradient-to-r from-primary/10 to-secondary/10">
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined text-primary text-[28px] animate-pulse">temp_preferences_custom</span>
                    <h5 class="modal-title font-heading font-bold text-headline-sm text-on-surface" id="aiQuizModalLabel">Generate Quiz with AI</h5>
                </div>
                <button type="button" class="btn-close text-on-surface focus:outline-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-lg space-y-lg max-h-[70vh] overflow-y-auto bg-surface-container-low/40">
                <!-- Step 1: Input Setup Form -->
                <div id="aiSetupStep">
                    <form id="aiGenerationForm" class="space-y-md">
                        <input type="hidden" id="ai_course_id" value="<?php echo $course_id; ?>">
                        <input type="hidden" id="ai_csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                        
                        <!-- Subject dropdown -->
                        <div class="mb-3">
                            <label class="font-label-md text-label-md text-on-surface block mb-2" for="ai_subject_id">Select Subject Unit / Chapter</label>
                            <select id="ai_subject_id" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md" required>
                                <option value="" disabled selected>-- Select a Chapter / Subject Unit --</option>
                                <?php foreach ($course_subjects as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>"><?php echo e($sub['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Lesson dropdown -->
                        <div class="mb-3">
                            <label class="font-label-md text-label-md text-on-surface block mb-2" for="ai_lesson_id">Select Lesson (Optional)</label>
                            <select id="ai_lesson_id" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md">
                                <option value="">-- All Lessons in Chapter --</option>
                            </select>
                        </div>
                        
                        <!-- Topic string input -->
                        <div class="mb-3">
                            <label class="font-label-md text-label-md text-on-surface block mb-2" for="ai_topic">Quiz Topic</label>
                            <input type="text" id="ai_topic" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md" placeholder="e.g. Photosynthesis, Basic Fractions, Past Tense" required>
                        </div>
                        
                        <div class="row">
                            <!-- Difficulty -->
                            <div class="col-md-6 mb-3">
                                <label class="font-label-md text-label-md text-on-surface block mb-2" for="ai_difficulty">Question Difficulty</label>
                                <select id="ai_difficulty" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md" required>
                                    <option value="easy">Easy (10 Pts per question)</option>
                                    <option value="medium">Medium (15 Pts per question)</option>
                                    <option value="hard">Hard (20 Pts per question)</option>
                                </select>
                            </div>
                            
                            <!-- Questions Count -->
                            <div class="col-md-6 mb-3">
                                <label class="font-label-md text-label-md text-on-surface block mb-2" for="ai_questions_count">Number of Questions</label>
                                <select id="ai_questions_count" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md" required>
                                    <option value="5">5 Questions</option>
                                    <option value="10" selected>10 Questions</option>
                                    <option value="20">20 Questions</option>
                                    <option value="50">50 Questions</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="pt-sm mt-3">
                            <button type="submit" class="w-full h-12 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-label-md text-label-md shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all flex items-center justify-center gap-xs border-0">
                                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                                Generate Questions
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Loading State -->
                <div id="aiLoadingStep" class="hidden text-center py-5 space-y-md">
                    <div class="inline-block w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="font-body-md text-on-surface font-semibold">Gemini AI is crafting your custom questions...</p>
                    <p class="text-xs text-on-surface-variant">This will take a few moments. Hang tight!</p>
                </div>
                
                <!-- Step 2: Edit & Preview Step -->
                <div id="aiPreviewStep" class="hidden space-y-md">
                    <div class="flex justify-between items-center pb-sm border-b border-outline-variant/20 mb-3">
                        <h6 class="font-bold text-on-surface text-sm flex items-center gap-xs">
                            <span class="material-symbols-outlined text-green-500">done_all</span>
                            Verify and Edit Questions
                        </h6>
                        <span id="ai_meta_info" class="text-xs text-secondary font-semibold bg-secondary/10 px-sm py-1 rounded-full"></span>
                    </div>
                    
                    <!-- Questions editing list container -->
                    <div id="ai_questions_edit_list" class="space-y-lg max-h-[45vh] overflow-y-auto pr-xs" style="max-height: 45vh; overflow-y: auto;">
                        <!-- Questions will be injected here dynamically -->
                    </div>
                    
                    <!-- Save actions -->
                    <div class="flex gap-md pt-sm border-t border-outline-variant/20 mt-4">
                        <button type="button" id="aiBackButton" class="w-1/3 h-12 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high border-0">
                            Back
                        </button>
                        <button type="button" id="aiSaveButton" class="w-2/3 h-12 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-label-md text-label-md shadow-lg hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-xs border-0">
                            <span class="material-symbols-outlined text-[20px]">save</span>
                            Save & Publish Quiz
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseLessons = <?php echo json_encode($course_lessons); ?>;
    
    const aiGenerationForm = document.getElementById('aiGenerationForm');
    const aiSetupStep = document.getElementById('aiSetupStep');
    const aiLoadingStep = document.getElementById('aiLoadingStep');
    const aiPreviewStep = document.getElementById('aiPreviewStep');
    const aiQuestionsEditList = document.getElementById('ai_questions_edit_list');
    const aiMetaInfo = document.getElementById('ai_meta_info');
    const aiBackButton = document.getElementById('aiBackButton');
    const aiSaveButton = document.getElementById('aiSaveButton');
    const aiSubjectSelect = document.getElementById('ai_subject_id');
    const aiLessonSelect = document.getElementById('ai_lesson_id');
    
    // Dynamic filtering of lessons based on subject selection
    if (aiSubjectSelect && aiLessonSelect) {
        aiSubjectSelect.addEventListener('change', function() {
            const selectedSubjectId = this.value;
            aiLessonSelect.innerHTML = '<option value="">-- All Lessons in Chapter --</option>';
            
            const filtered = courseLessons.filter(l => l.subject_id == selectedSubjectId);
            filtered.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = l.title;
                aiLessonSelect.appendChild(opt);
            });
        });
    }
    
    let generatedQuestions = [];

    aiGenerationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const subjectId = document.getElementById('ai_subject_id').value;
        const lessonId = document.getElementById('ai_lesson_id').value;
        const topic = document.getElementById('ai_topic').value;
        const difficulty = document.getElementById('ai_difficulty').value;
        const count = document.getElementById('ai_questions_count').value;
        const csrfToken = document.getElementById('ai_csrf_token').value;
        
        // Show loading state
        aiSetupStep.classList.add('hidden');
        aiLoadingStep.classList.remove('hidden');
        
        // Make AJAX request
        const formData = new FormData();
        formData.append('subject_id', subjectId);
        formData.append('lesson_id', lessonId);
        formData.append('topic', topic);
        formData.append('difficulty', difficulty);
        formData.append('questions_count', count);
        formData.append('csrf_token', csrfToken);
        
        fetch('generate-ai-quiz-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            aiLoadingStep.classList.add('hidden');
            if (data.success) {
                generatedQuestions = data.questions;
                aiMetaInfo.textContent = `Generated: ${data.questions_count} questions via ${data.generated_by === 'gemini_api' ? 'Gemini AI' : 'Template engine'}`;
                renderEditList(generatedQuestions);
                aiPreviewStep.classList.remove('hidden');
            } else {
                alert('Generation failed: ' + data.message);
                aiSetupStep.classList.remove('hidden');
            }
        })
        .catch(err => {
            aiLoadingStep.classList.add('hidden');
            alert('Request error: ' + err.message);
            aiSetupStep.classList.remove('hidden');
        });
    });

    aiBackButton.addEventListener('click', function() {
        aiPreviewStep.classList.add('hidden');
        aiSetupStep.classList.remove('hidden');
    });

    function renderEditList(questions) {
        aiQuestionsEditList.innerHTML = '';
        questions.forEach((q, qIndex) => {
            const card = document.createElement('div');
            card.className = 'p-md border border-outline-variant/30 rounded-xl bg-white space-y-sm mb-3';
            
            // Question header & text
            let html = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-xs font-bold text-primary uppercase">Question ${qIndex + 1} (${q.question_type.toUpperCase()})</span>
                    <span class="text-xs font-semibold text-on-surface-variant bg-surface-container px-2 py-0.5 rounded">${q.points} Pts</span>
                </div>
                <div class="mb-2">
                    <label class="text-xs text-on-surface-variant font-semibold mb-1 block">Question Text</label>
                    <textarea class="w-full p-2 border border-outline-variant/30 rounded-lg text-sm question-text-input" data-index="${qIndex}" rows="2" style="color: black !important; width: 100%;">${q.question_text}</textarea>
                </div>
            `;
            
            // Options editor depending on type
            if (q.question_type === 'mcq') {
                html += `<div class="space-y-xs">
                    <label class="text-xs text-on-surface-variant font-semibold mb-1 block">Multiple Choice Options (Check correct answer)</label>
                `;
                q.options.forEach((opt, optIndex) => {
                    html += `
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="radio" name="correct_opt_${qIndex}" class="correct-radio" data-qindex="${qIndex}" data-optindex="${optIndex}" ${opt.is_correct ? 'checked' : ''}>
                            <input type="text" class="form-control form-control-sm option-text-input" data-qindex="${qIndex}" data-optindex="${optIndex}" value="${opt.option_text}" style="color: black !important;">
                        </div>
                    `;
                });
                html += `</div>`;
            } else if (q.question_type === 'tf') {
                html += `<div class="space-y-xs mb-2">
                    <label class="text-xs text-on-surface-variant font-semibold mb-1 block">True / False Correct Answer</label>
                    <div class="d-flex gap-3">
                `;
                q.options.forEach((opt, optIndex) => {
                    html += `
                        <label class="d-flex align-items-center gap-1 cursor-pointer bg-surface-container/30 px-3 py-1.5 rounded border border-outline-variant/20 text-xs">
                            <input type="radio" name="correct_opt_${qIndex}" class="correct-radio" data-qindex="${qIndex}" data-optindex="${optIndex}" ${opt.is_correct ? 'checked' : ''}>
                            <span>${opt.option_text}</span>
                        </label>
                    `;
                });
                html += `
                    </div>
                </div>`;
            } else if (q.question_type === 'short_answer') {
                const ansText = q.options && q.options[0] ? q.options[0].option_text : '';
                html += `
                    <div class="mb-2">
                        <label class="text-xs text-on-surface-variant font-semibold mb-1 block">Expected Correct Answer Text</label>
                        <input type="text" class="w-full p-2 border border-outline-variant/30 rounded-lg text-xs short-answer-input" data-qindex="${qIndex}" value="${ansText}" style="color: black !important; width: 100%;" placeholder="Enter the exact answer expected">
                    </div>
                `;
            }
            
            card.innerHTML = html;
            aiQuestionsEditList.appendChild(card);
        });
    }

    aiSaveButton.addEventListener('click', function() {
        // Collect edits from fields
        const textAreas = document.querySelectorAll('.question-text-input');
        textAreas.forEach(ta => {
            const index = parseInt(ta.getAttribute('data-index'));
            generatedQuestions[index].question_text = ta.value;
        });

        // Collect radio inputs for correct selections
        const radios = document.querySelectorAll('.correct-radio');
        radios.forEach(rad => {
            if (rad.checked) {
                const qIndex = parseInt(rad.getAttribute('data-qindex'));
                const optIndex = parseInt(rad.getAttribute('data-optindex'));
                // Set correct flag and clear others
                generatedQuestions[qIndex].options.forEach((opt, idx) => {
                    opt.is_correct = (idx === optIndex) ? 1 : 0;
                });
            }
        });

        // Collect text inputs for options text
        const optionInputs = document.querySelectorAll('.option-text-input');
        optionInputs.forEach(input => {
            const qIndex = parseInt(input.getAttribute('data-qindex'));
            const optIndex = parseInt(input.getAttribute('data-optindex'));
            generatedQuestions[qIndex].options[optIndex].option_text = input.value;
        });

        // Collect short answer input values
        const shortInputs = document.querySelectorAll('.short-answer-input');
        shortInputs.forEach(input => {
            const qIndex = parseInt(input.getAttribute('data-qindex'));
            generatedQuestions[qIndex].options = [{
                option_text: input.value,
                is_correct: 1
            }];
        });

        // Save via AJAX request
        aiPreviewStep.classList.add('hidden');
        aiLoadingStep.classList.remove('hidden');

        const courseId = document.getElementById('ai_course_id').value;
        const subjectId = document.getElementById('ai_subject_id').value;
        const lessonId = document.getElementById('ai_lesson_id').value;
        const topic = document.getElementById('ai_topic').value;
        const difficulty = document.getElementById('ai_difficulty').value;
        const count = document.getElementById('ai_questions_count').value;
        const csrfToken = document.getElementById('ai_csrf_token').value;

        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('subject_id', subjectId);
        formData.append('lesson_id', lessonId);
        formData.append('topic', topic);
        formData.append('difficulty', difficulty);
        formData.append('questions', JSON.stringify(generatedQuestions));
        formData.append('questions_count', count);
        formData.append('csrf_token', csrfToken);

        fetch('save-ai-quiz-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            aiLoadingStep.classList.add('hidden');
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Save failed: ' + data.message);
                aiPreviewStep.classList.remove('hidden');
            }
        })
        .catch(err => {
            aiLoadingStep.classList.add('hidden');
            alert('Save request error: ' + err.message);
            aiPreviewStep.classList.remove('hidden');
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
