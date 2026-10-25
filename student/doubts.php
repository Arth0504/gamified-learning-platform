<?php
$page_title = "My doubts";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

// Handle New Doubt Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ask_doubt') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $course_id = intval($_POST['course_id'] ?? 0);
        $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : null;
        $title = trim($_POST['title'] ?? '');
        $question = trim($_POST['question'] ?? '');
        
        if (empty($title) || empty($question) || $course_id === 0) {
            set_flash_message('danger', 'Course, Title and Question fields are required.');
        } else {
            $screenshot_path = null;
            // Handle optional screenshot upload
            if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['screenshot']['tmp_name'];
                $file_name = time() . '_' . basename($_FILES['screenshot']['name']);
                $upload_dir = __DIR__ . '/../uploads/doubts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $screenshot_path = 'uploads/doubts/' . $file_name;
                }
            }
            
            $ins_stmt = $pdo->prepare("
                INSERT INTO doubts (student_id, course_id, lesson_id, title, question, screenshot_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $ins_stmt->execute([$student_id, $course_id, $lesson_id, $title, $question, $screenshot_path]);
            $doubt_id = $pdo->lastInsertId();
            
            // Log Activity
            log_activity($pdo, $user_id, 'Create Doubt', "Asked doubt: $title (ID: $doubt_id)");
            
            set_flash_message('success', 'Your doubt has been submitted successfully to the teacher!');
            header("Location: doubts.php?doubt_id=" . $doubt_id);
            exit();
        }
    }
}

// Handle Reply Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_doubt') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $doubt_id = intval($_POST['doubt_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        
        if ($doubt_id > 0 && !empty($reply)) {
            // Verify student owns doubt
            $chk = $pdo->prepare("SELECT status FROM doubts WHERE id = ? AND student_id = ?");
            $chk->execute([$doubt_id, $student_id]);
            $doubt = $chk->fetch();
            
            if ($doubt) {
                // Insert reply
                $ins_rep = $pdo->prepare("INSERT INTO doubt_replies (doubt_id, user_id, reply, is_read) VALUES (?, ?, ?, 0)");
                $ins_rep->execute([$doubt_id, $user_id, $reply]);
                
                // If doubt was Resolved, set it back to Pending (re-opened) or Answered?
                // Let's set it back to Pending or keep it as is. Usually re-opens as Pending.
                if ($doubt['status'] === 'Resolved') {
                    $up_status = $pdo->prepare("UPDATE doubts SET status = 'Pending' WHERE id = ?");
                    $up_status->execute([$doubt_id]);
                }
                
                log_activity($pdo, $user_id, 'Reply Doubt Thread', "Student replied to doubt ID: $doubt_id");
                set_flash_message('success', 'Reply posted successfully.');
                header("Location: doubts.php?doubt_id=" . $doubt_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        }
    }
}

// Fetch Student's enrolled courses for the "Ask Doubt" dropdown
$courses_stmt = $pdo->prepare("
    SELECT c.id, c.title 
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    WHERE ce.student_id = ? AND ce.status = 'active'
");
$courses_stmt->execute([$student_id]);
$enrolled_courses = $courses_stmt->fetchAll();

// Fetch Doubts with unread replies count
$doubts_stmt = $pdo->prepare("
    SELECT d.*, c.title AS course_title, l.title AS lesson_title,
           (SELECT COUNT(*) FROM doubt_replies dr JOIN users u ON dr.user_id = u.id JOIN roles r ON u.role_id = r.id WHERE dr.doubt_id = d.id AND r.name = 'Teacher' AND dr.is_read = 0) as unread_replies_count
    FROM doubts d
    LEFT JOIN courses c ON d.course_id = c.id
    LEFT JOIN lessons l ON d.lesson_id = l.id
    WHERE d.student_id = ?
    ORDER BY d.updated_at DESC
");
$doubts_stmt->execute([$student_id]);
$all_doubts = $doubts_stmt->fetchAll();

// Selected doubt for detail view
$selected_doubt_id = isset($_GET['doubt_id']) ? intval($_GET['doubt_id']) : null;
$selected_doubt = null;
$replies = [];

if ($selected_doubt_id) {
    $sel_stmt = $pdo->prepare("
        SELECT d.*, c.title AS course_title, l.title AS lesson_title 
        FROM doubts d
        LEFT JOIN courses c ON d.course_id = c.id
        LEFT JOIN lessons l ON d.lesson_id = l.id
        WHERE d.id = ? AND d.student_id = ?
    ");
    $sel_stmt->execute([$selected_doubt_id, $student_id]);
    $selected_doubt = $sel_stmt->fetch();
    
    if ($selected_doubt) {
        // Mark all teacher replies in this doubt as Read
        $up_read = $pdo->prepare("
            UPDATE doubt_replies dr
            SET dr.is_read = 1 
            WHERE dr.doubt_id = ? 
              AND dr.user_id IN (SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Teacher')
        ");
        $up_read->execute([$selected_doubt_id]);
        
        // Fetch replies thread
        $rep_stmt = $pdo->prepare("
            SELECT dr.*, u.full_name, u.role_id, r.name AS role_name, u.profile_pic 
            FROM doubt_replies dr
            JOIN users u ON dr.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE dr.doubt_id = ?
            ORDER BY dr.created_at ASC
        ");
        $rep_stmt->execute([$selected_doubt_id]);
        $replies = $rep_stmt->fetchAll();
    }
}
?>

<div class="space-y-lg">
    <!-- Top Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">Doubt Resolution Center</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Ask questions to your teachers and track your unresolved doubts.</p>
        </div>
        <button class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2.5 rounded-lg font-label-md text-label-md transition-colors shadow border-0" type="button" data-bs-toggle="modal" data-bs-target="#askDoubtModal">
            <span class="material-symbols-outlined text-[18px]">question_answer</span>
            Ask a Doubt
        </button>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        
        <!-- Left Side: List of Doubts -->
        <div class="lg:col-span-5 space-y-md">
            <div class="glass-card rounded-xl p-md">
                <div class="border-b border-outline-variant/30 pb-xs mb-sm flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">My Doubts</h3>
                    <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">Total: <?php echo count($all_doubts); ?></span>
                </div>
                
                <!-- Filter Tabs -->
                <div class="flex gap-2 mb-md text-xs">
                    <button class="filter-tab px-3 py-1.5 rounded-full border border-primary/20 bg-primary/10 text-primary font-bold" data-target="all">All</button>
                    <button class="filter-tab px-3 py-1.5 rounded-full border border-outline-variant/30 text-on-surface-variant" data-target="Pending">Pending</button>
                    <button class="filter-tab px-3 py-1.5 rounded-full border border-outline-variant/30 text-on-surface-variant" data-target="Answered">Answered</button>
                    <button class="filter-tab px-3 py-1.5 rounded-full border border-outline-variant/30 text-on-surface-variant" data-target="Resolved">Resolved</button>
                </div>
                
                <?php if (empty($all_doubts)): ?>
                    <div class="text-center py-xl space-y-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-secondary text-5xl">forum</span>
                        <p class="font-body-md text-body-md">You haven't asked any doubts yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-sm max-h-[60vh] overflow-y-auto pr-xs">
                        <?php foreach ($all_doubts as $d): 
                            $status_class = "bg-amber-500/10 text-amber-600";
                            if ($d['status'] === 'Answered') $status_class = "bg-blue-500/10 text-blue-600";
                            if ($d['status'] === 'Resolved') $status_class = "bg-green-500/10 text-green-600";
                            
                            $active_card_class = ($selected_doubt_id === $d['id']) ? "border-primary bg-primary/[0.02]" : "border-outline-variant/30 bg-surface-container-lowest";
                        ?>
                            <a href="doubts.php?doubt_id=<?php echo $d['id']; ?>" class="doubt-item block p-md border rounded-xl <?php echo $active_card_class; ?> hover:shadow-md transition-all text-decoration-none" data-status="<?php echo $d['status']; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="px-sm py-0.5 rounded-full text-xs font-bold <?php echo $status_class; ?>"><?php echo $d['status']; ?></span>
                                    <?php if ($d['unread_replies_count'] > 0): ?>
                                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                            <?php echo $d['unread_replies_count']; ?> New Reply
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="font-headline-sm text-sm font-bold text-on-surface mb-xs limit-text"><?php echo e($d['title']); ?></h4>
                                <div class="flex justify-between items-center text-xs text-on-surface-variant">
                                    <span><?php echo e($d['course_title']); ?></span>
                                    <span><?php echo date('M d, h:i A', strtotime($d['updated_at'])); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Doubt Detail & Thread View -->
        <div class="lg:col-span-7">
            <?php if (!$selected_doubt): ?>
                <div class="glass-card rounded-xl p-xl h-full flex flex-col justify-center items-center text-center text-on-surface-variant min-h-[350px]">
                    <span class="material-symbols-outlined text-secondary text-5xl mb-sm">chat</span>
                    <p class="font-body-md text-body-md font-semibold">No Doubt Selected</p>
                    <p class="text-xs text-on-surface-variant/70">Select a doubt from the left pane to view its reply thread or write a reply.</p>
                </div>
            <?php else: ?>
                <div class="glass-card rounded-xl p-md md:p-lg space-y-md">
                    <!-- Title & Lesson Info -->
                    <div class="border-b border-outline-variant/30 pb-sm mb-md flex justify-between items-start">
                        <div class="space-y-xs">
                            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($selected_doubt['title']); ?></h3>
                            <p class="text-xs text-on-surface-variant">
                                Course: <strong><?php echo e($selected_doubt['course_title']); ?></strong>
                                <?php if ($selected_doubt['lesson_title']): ?>
                                    | Lesson: <strong><?php echo e($selected_doubt['lesson_title']); ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="px-sm py-1 rounded-full text-xs font-bold <?php 
                            echo ($selected_doubt['status'] === 'Resolved') ? 'bg-green-500/10 text-green-600' : (($selected_doubt['status'] === 'Answered') ? 'bg-blue-500/10 text-blue-600' : 'bg-amber-500/10 text-amber-600'); 
                        ?>"><?php echo $selected_doubt['status']; ?></span>
                    </div>

                    <!-- Initial Question Card -->
                    <div class="p-md rounded-xl border border-outline-variant/20 bg-surface-container-low/40 space-y-sm text-left">
                        <div class="flex justify-between items-center text-xs text-on-surface-variant">
                            <span class="font-semibold text-primary">Student Question (Original)</span>
                            <span><?php echo date('M d, Y h:i A', strtotime($selected_doubt['created_at'])); ?></span>
                        </div>
                        <p class="font-body-md text-on-surface leading-relaxed text-sm whitespace-pre-line"><?php echo e($selected_doubt['question']); ?></p>
                        
                        <?php if ($selected_doubt['screenshot_path']): ?>
                            <div class="pt-sm">
                                <span class="text-xs font-semibold text-on-surface-variant block mb-1">Attached Screenshot:</span>
                                <a href="<?php echo BASE_URL . e($selected_doubt['screenshot_path']); ?>" target="_blank" class="inline-block border border-outline-variant/30 rounded overflow-hidden shadow-sm hover:opacity-90">
                                    <img src="<?php echo BASE_URL . e($selected_doubt['screenshot_path']); ?>" alt="Attachment" class="max-w-[240px] max-h-[160px] object-contain">
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thread Replies -->
                    <div class="space-y-sm max-h-[40vh] overflow-y-auto pr-xs">
                        <h4 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-sm">Discussion Thread</h4>
                        <?php if (empty($replies)): ?>
                            <p class="text-xs text-on-surface-variant italic py-xs text-center">No replies posted yet. Waiting for teacher's response...</p>
                        <?php else: ?>
                            <?php foreach ($replies as $rep): 
                                $is_teacher = ($rep['role_name'] === 'Teacher');
                                $card_border = $is_teacher ? "border-secondary/20 bg-secondary/[0.01]" : "border-outline-variant/20 bg-surface-container-lowest";
                                $author_color = $is_teacher ? "text-secondary font-bold" : "text-primary font-bold";
                            ?>
                                <div class="p-sm rounded-xl border <?php echo $card_border; ?> space-y-1 text-left">
                                    <div class="flex justify-between items-center text-[10px] text-on-surface-variant mb-1">
                                        <span class="<?php echo $author_color; ?>"><?php echo e($rep['full_name']); ?> (<?php echo e($rep['role_name']); ?>)</span>
                                        <span><?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?></span>
                                    </div>
                                    <p class="text-xs text-on-surface leading-relaxed whitespace-pre-line"><?php echo e($rep['reply']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Reply Form -->
                    <form action="doubts.php" method="POST" class="border-t border-outline-variant/30 pt-sm mt-md space-y-sm">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="reply_doubt">
                        <input type="hidden" name="doubt_id" value="<?php echo $selected_doubt_id; ?>">
                        
                        <div>
                            <textarea name="reply" rows="3" class="w-full p-2 border border-outline-variant/30 rounded-lg text-xs" style="color:black !important;" placeholder="Write your reply to the teacher..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md transition-colors shadow border-0">
                                Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Ask Doubt Modal -->
<div class="modal fade" id="askDoubtModal" tabindex="-1" aria-labelledby="askDoubtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-panel border-0 overflow-hidden shadow-2xl rounded-xl">
            <div class="modal-header bg-gradient-to-r from-primary/10 to-secondary/10 px-lg py-md flex justify-between items-center border-b border-outline-variant/20">
                <h5 class="modal-title font-heading font-bold text-headline-sm text-on-surface" id="askDoubtModalLabel">Ask a Question / Doubt</h5>
                <button type="button" class="btn-close focus:outline-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-lg bg-surface-container-low/40">
                <form action="doubts.php" method="POST" enctype="multipart/form-data" class="space-y-md">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="action" value="ask_doubt">
                    
                    <!-- Course dropdown -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="course_id">Select Enrolled Course</label>
                        <select id="course_id" name="course_id" class="w-full h-10 px-sm border border-outline-variant/30 rounded-lg text-sm bg-white" style="color:black !important;" required>
                            <option value="" disabled selected>-- Select Course --</option>
                            <?php foreach ($enrolled_courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?></option>
                            <?php endforeach; ?></select>
                    </div>

                    <!-- Lesson dropdown (loaded dynamically depending on course) -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="lesson_id">Select Lesson (Optional)</label>
                        <select id="lesson_id" name="lesson_id" class="w-full h-10 px-sm border border-outline-variant/30 rounded-lg text-sm bg-white" style="color:black !important;">
                            <option value="">-- General Course Question --</option>
                        </select>
                    </div>
                    
                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="title">Doubt Subject/Title</label>
                        <input type="text" id="title" name="title" class="w-full p-2 border border-outline-variant/30 rounded-lg text-sm bg-white" style="color:black !important;" placeholder="e.g. Unclear on Subject-Verb agreement rules" required>
                    </div>

                    <!-- Question text -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="question">Detailed Explanation of Doubt</label>
                        <textarea id="question" name="question" rows="4" class="w-full p-2 border border-outline-variant/30 rounded-lg text-sm bg-white" style="color:black !important;" placeholder="Write details about what is confusing you..." required></textarea>
                    </div>

                    <!-- Screenshot file -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="screenshot">Upload Image/Screenshot (Optional)</label>
                        <input type="file" id="screenshot" name="screenshot" accept="image/*" class="w-full text-xs text-on-surface-variant border border-outline-variant/30 rounded-lg p-2 bg-white">
                    </div>

                    <div class="pt-sm border-t border-outline-variant/20 mt-4 flex gap-md">
                        <button type="button" class="w-1/3 py-2.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-label-md border-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="w-2/3 py-2.5 rounded-lg bg-primary text-white hover:bg-primary-hover font-label-md text-label-md shadow transition-all border-0">Submit Doubt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // JavaScript Filter Tabs
    const filterTabs = document.querySelectorAll('.filter-tab');
    const doubtItems = document.querySelectorAll('.doubt-item');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Reset active style on all tabs
            filterTabs.forEach(t => {
                t.classList.remove('bg-primary/10', 'text-primary', 'font-bold');
                t.classList.add('text-on-surface-variant');
            });
            // Mark selected active
            this.classList.add('bg-primary/10', 'text-primary', 'font-bold');
            this.classList.remove('text-on-surface-variant');
            
            const target = this.getAttribute('data-target');
            doubtItems.forEach(item => {
                if (target === 'all' || item.getAttribute('data-status') === target) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Dynamic dropdown for lessons based on selected course
    const courseSelect = document.getElementById('course_id');
    const lessonSelect = document.getElementById('lesson_id');
    
    courseSelect.addEventListener('change', function() {
        const courseId = this.value;
        if (!courseId) return;
        
        // Fetch lessons of the course
        lessonSelect.innerHTML = '<option value="">Loading lessons...</option>';
        
        fetch('doubts-ajax.php?course_id=' + courseId)
        .then(res => res.json())
        .then(data => {
            lessonSelect.innerHTML = '<option value="">-- General Course Question --</option>';
            if (data && data.length > 0) {
                data.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l.id;
                    opt.textContent = `[${l.subject_title}] ${l.title}`;
                    lessonSelect.appendChild(opt);
                });
            }
        })
        .catch(err => {
            lessonSelect.innerHTML = '<option value="">-- General Course Question --</option>';
            console.error('Lessons load error:', err);
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
