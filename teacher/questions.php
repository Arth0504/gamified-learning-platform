<?php
$page_title = "Manage Quiz Questions";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? $_POST['quiz_id'] ?? null;

if (!$quiz_id) {
    set_flash_message('danger', 'Quiz ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Verify Quiz ownership (Quiz -> Course -> Teacher)
$quiz_stmt = $pdo->prepare("
    SELECT q.*, c.title AS course_title, c.id AS course_id 
    FROM quizzes q 
    JOIN courses c ON q.course_id = c.id 
    WHERE q.id = ? AND c.teacher_id = ?
");
$quiz_stmt->execute([$quiz_id, $teacher_id]);
$quiz = $quiz_stmt->fetch();

if (!$quiz) {
    set_flash_message('danger', 'Quiz not found or unauthorized access.');
    header("Location: courses.php");
    exit();
}

// Handle Form Submissions
$action = $_GET['action'] ?? 'list';
$edit_question = null;
$edit_options = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $question_text = trim($_POST['question_text'] ?? '');
        $points = intval($_POST['points'] ?? 10);
        $difficulty = $_POST['difficulty'] ?? 'easy';
        
        $options = [
            trim($_POST['option_1'] ?? ''),
            trim($_POST['option_2'] ?? ''),
            trim($_POST['option_3'] ?? ''),
            trim($_POST['option_4'] ?? '')
        ];
        
        $correct_index = intval($_POST['correct_option'] ?? 0); // 0-3

        if (empty($question_text)) {
            set_flash_message('danger', 'Question text is required.');
        } elseif (empty($options[0]) || empty($options[1]) || empty($options[2]) || empty($options[3])) {
            set_flash_message('danger', 'All four MCQ options are required.');
        } else {
            try {
                $pdo->beginTransaction();
                
                if (isset($_POST['question_id'])) {
                    // Update Question
                    $question_id = $_POST['question_id'];
                    $up_stmt = $pdo->prepare("UPDATE questions SET question_text = ?, points = ?, difficulty = ? WHERE id = ?");
                    $up_stmt->execute([$question_text, $points, $difficulty, $question_id]);
                    
                    // Replace options (Delete existing, insert new)
                    $del_stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                    $del_stmt->execute([$question_id]);
                    
                    $ins_opt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    foreach ($options as $idx => $opt) {
                        $is_correct = ($idx === $correct_index) ? 1 : 0;
                        $ins_opt->execute([$question_id, $opt, $is_correct]);
                    }
                    
                    log_activity($pdo, $user_id, 'Edit Quiz Question', "Updated question ID: $question_id");
                    set_flash_message('success', 'Question updated successfully!');
                } else {
                    // Add Question
                    $ins_stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, points, difficulty) VALUES (?, ?, ?, ?)");
                    $ins_stmt->execute([$quiz_id, $question_text, $points, $difficulty]);
                    $question_id = $pdo->lastInsertId();
                    
                    // Insert options
                    $ins_opt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    foreach ($options as $idx => $opt) {
                        $is_correct = ($idx === $correct_index) ? 1 : 0;
                        $ins_opt->execute([$question_id, $opt, $is_correct]);
                    }
                    
                    log_activity($pdo, $user_id, 'Create Quiz Question', "Created question under quiz: " . $quiz['title']);
                    set_flash_message('success', 'Question added successfully!');
                }
                
                $pdo->commit();
                header("Location: questions.php?quiz_id=" . $quiz_id);
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash_message('danger', 'Operation failed. Error: ' . $e->getMessage());
            }
        }
    }
}

// Handle Delete Question
if ($action === 'delete' && isset($_GET['id'])) {
    $question_id = $_GET['id'];
    
    // Verify ownership via quiz ID
    $check = $pdo->prepare("SELECT id FROM questions WHERE id = ? AND quiz_id = ?");
    $check->execute([$question_id, $quiz_id]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        
        log_activity($pdo, $user_id, 'Delete Quiz Question', "Deleted question ID: $question_id");
        set_flash_message('success', 'Question deleted successfully!');
    } else {
        set_flash_message('danger', 'Access denied.');
    }
    header("Location: questions.php?quiz_id=" . $quiz_id);
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $question_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ? AND quiz_id = ?");
    $stmt->execute([$question_id, $quiz_id]);
    $edit_question = $stmt->fetch();
    
    if ($edit_question) {
        $opt_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $opt_stmt->execute([$question_id]);
        $edit_options = $opt_stmt->fetchAll();
    } else {
        set_flash_message('danger', 'Question not found.');
        header("Location: questions.php?quiz_id=" . $quiz_id);
        exit();
    }
}

// Get Quiz Questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="courses.php" class="text-success text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item"><a href="quizzes.php?course_id=<?php echo $quiz['course_id']; ?>" class="text-success text-decoration-none"><?php echo e($quiz['course_title']); ?></a></li>
                <li class="breadcrumb-item text-secondary active" aria-current="page"><?php echo e($quiz['title']); ?></li>
            </ol>
        </nav>
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Question' : ($action === 'add' ? 'Add MCQ Question' : 'Quiz Questions'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end d-flex align-items-end justify-content-md-end gap-2">
        <?php if ($action === 'list'): ?>
            <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e(__('teacher.add_question')); ?>
            </a>
            <a href="quizzes.php?course_id=<?php echo $quiz['course_id']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
            </a>
        <?php else: ?>
            <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST QUESTIONS VIEW -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($questions)): ?>
                <div class="glass-card text-center py-5">
                    <i class="bi bi-list-ol text-secondary display-4 mb-3"></i>
                    <p class="text-secondary">No questions added yet.</p>
                    <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>&action=add" class="btn btn-primary">Add Your First Question</a>
                </div>
            <?php else: ?>
                <?php foreach ($questions as $idx => $q): ?>
                    <div class="glass-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-color">
                            <h5 class="font-heading text-dark-theme-compatible mb-0">Question <?php echo $idx + 1; ?></h5>
                            <div>
                                <span class="badge bg-secondary"><?php echo ucfirst($q['difficulty']); ?></span>
                                <span class="badge bg-success">+<?php echo $q['points']; ?> Points</span>
                                <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>&action=edit&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-secondary ms-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>&action=delete&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this question?');" title="Delete"><i class="bi bi-trash"></i></a>
                            </div>
                        </div>
                        <p class="fs-5 text-dark-theme-compatible mb-3"><?php echo e($q['question_text']); ?></p>
                        
                        <?php
                        // Fetch options
                        $opts_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
                        $opts_stmt->execute([$q['id']]);
                        $opts = $opts_stmt->fetchAll();
                        ?>
                        <div class="row g-2">
                            <?php foreach ($opts as $o_idx => $opt): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded border-color d-flex align-items-center justify-content-between <?php echo $opt['is_correct'] ? 'bg-success-subtle border-success' : ''; ?>">
                                        <span class="text-dark-theme-compatible">
                                            <strong><?php echo chr(65 + $o_idx); ?>.</strong> <?php echo e($opt['option_text']); ?>
                                        </span>
                                        <?php if ($opt['is_correct']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Correct</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD/EDIT FORM VIEW -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form action="questions.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    
                    <?php if ($edit_question): ?>
                        <input type="hidden" name="question_id" value="<?php echo $edit_question['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label font-heading">Question Text</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" placeholder="Type the multiple choice question here..." required><?php echo $edit_question ? e($edit_question['question_text']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="points" class="form-label font-heading">Points Value</label>
                            <input type="number" class="form-control" id="points" name="points" value="<?php echo $edit_question ? intval($edit_question['points']) : '10'; ?>" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="difficulty" class="form-label font-heading">Difficulty Level</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="easy" <?php echo ($edit_question && $edit_question['difficulty'] === 'easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo ($edit_question && $edit_question['difficulty'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo ($edit_question && $edit_question['difficulty'] === 'hard') ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="font-heading text-success mt-4 mb-3 border-bottom pb-2 border-color"><i class="bi bi-ui-checks-grid me-2"></i>MCQ Options</h5>
                    <div class="form-text small mb-3 text-secondary">Fill out the four answer options and select the correct one using the buttons on the left.</div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-transparent border-end-0 border-color">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="0" aria-label="Option A Correct" <?php 
                                    echo (!$edit_options || (isset($edit_options[0]) && $edit_options[0]['is_correct'] == 1)) ? 'checked' : ''; 
                                ?>>
                                <span class="ms-2 font-weight-bold">A.</span>
                            </div>
                            <input type="text" class="form-control border-start-0" name="option_1" value="<?php echo isset($edit_options[0]) ? e($edit_options[0]['option_text']) : ''; ?>" placeholder="First option text" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-transparent border-end-0 border-color">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="1" aria-label="Option B Correct" <?php 
                                    echo (isset($edit_options[1]) && $edit_options[1]['is_correct'] == 1) ? 'selected' : ''; 
                                    if (isset($edit_options[1]) && $edit_options[1]['is_correct'] == 1) echo 'checked';
                                ?>>
                                <span class="ms-2 font-weight-bold">B.</span>
                            </div>
                            <input type="text" class="form-control border-start-0" name="option_2" value="<?php echo isset($edit_options[1]) ? e($edit_options[1]['option_text']) : ''; ?>" placeholder="Second option text" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-transparent border-end-0 border-color">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="2" aria-label="Option C Correct" <?php 
                                    echo (isset($edit_options[2]) && $edit_options[2]['is_correct'] == 1) ? 'selected' : ''; 
                                    if (isset($edit_options[2]) && $edit_options[2]['is_correct'] == 1) echo 'checked';
                                ?>>
                                <span class="ms-2 font-weight-bold">C.</span>
                            </div>
                            <input type="text" class="form-control border-start-0" name="option_3" value="<?php echo isset($edit_options[2]) ? e($edit_options[2]['option_text']) : ''; ?>" placeholder="Third option text" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-transparent border-end-0 border-color">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="3" aria-label="Option D Correct" <?php 
                                    echo (isset($edit_options[3]) && $edit_options[3]['is_correct'] == 1) ? 'selected' : ''; 
                                    if (isset($edit_options[3]) && $edit_options[3]['is_correct'] == 1) echo 'checked';
                                ?>>
                                <span class="ms-2 font-weight-bold">D.</span>
                            </div>
                            <input type="text" class="form-control border-start-0" name="option_4" value="<?php echo isset($edit_options[3]) ? e($edit_options[3]['option_text']) : ''; ?>" placeholder="Fourth option text" required>
                        </div>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Question</button>
                        <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
/* CSS class background for highlighted correct answer container cards */
.bg-success-subtle {
    background-color: rgba(46, 125, 50, 0.15) !important;
}
</style>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
