<?php
$page_title = "Student doubts";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Handle Status Changes (Resolved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_doubt') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $doubt_id = intval($_POST['doubt_id'] ?? 0);
        if ($doubt_id > 0) {
            // Verify teacher teaches the course for this doubt
            $chk = $pdo->prepare("
                SELECT d.id FROM doubts d
                JOIN courses c ON d.course_id = c.id
                WHERE d.id = ? AND c.teacher_id = ?
            ");
            $chk->execute([$doubt_id, $teacher_id]);
            if ($chk->fetch()) {
                $up = $pdo->prepare("UPDATE doubts SET status = 'Resolved' WHERE id = ?");
                $up->execute([$doubt_id]);
                
                log_activity($pdo, $user_id, 'Resolve Doubt', "Teacher marked doubt ID: $doubt_id as Resolved");
                set_flash_message('success', 'Doubt status updated to Resolved.');
                header("Location: doubts.php?doubt_id=" . $doubt_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
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
            // Verify teacher teaches the course for this doubt
            $chk = $pdo->prepare("
                SELECT d.id, d.student_id, d.title FROM doubts d
                JOIN courses c ON d.course_id = c.id
                WHERE d.id = ? AND c.teacher_id = ?
            ");
            $chk->execute([$doubt_id, $teacher_id]);
            $doubt_data = $chk->fetch();
            
            if ($doubt_data) {
                // Insert reply
                $ins_rep = $pdo->prepare("INSERT INTO doubt_replies (doubt_id, user_id, reply, is_read) VALUES (?, ?, ?, 0)");
                $ins_rep->execute([$doubt_id, $user_id, $reply]);
                
                // Update doubt status to Answered
                $up_status = $pdo->prepare("UPDATE doubts SET status = 'Answered' WHERE id = ?");
                $up_status->execute([$doubt_id]);
                
                // Send student notification
                $student_info_stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
                $student_info_stmt->execute([$doubt_data['student_id']]);
                $student_user_id = $student_info_stmt->fetchColumn();
                if ($student_user_id) {
                    $notify = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, type, message, is_read) 
                        VALUES (?, 'Doubt Answered', 'info', ?, 0)
                    ");
                    $notify->execute([$student_user_id, "Teacher replied to your doubt: '" . $doubt_data['title'] . "'"]);
                }
                
                log_activity($pdo, $user_id, 'Reply Doubt Thread', "Teacher replied to doubt ID: $doubt_id");
                set_flash_message('success', 'Reply posted successfully.');
                header("Location: doubts.php?doubt_id=" . $doubt_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        }
    }
}

// Fetch all doubts for teacher's courses
$doubts_stmt = $pdo->prepare("
    SELECT d.*, c.title AS course_title, l.title AS lesson_title, u.full_name AS student_name
    FROM doubts d
    JOIN courses c ON d.course_id = c.id
    LEFT JOIN lessons l ON d.lesson_id = l.id
    JOIN students s ON d.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY d.updated_at DESC
");
$doubts_stmt->execute([$teacher_id]);
$all_doubts = $doubts_stmt->fetchAll();

// Selected doubt for detail view
$selected_doubt_id = isset($_GET['doubt_id']) ? intval($_GET['doubt_id']) : null;
$selected_doubt = null;
$replies = [];

if ($selected_doubt_id) {
    $sel_stmt = $pdo->prepare("
        SELECT d.*, c.title AS course_title, l.title AS lesson_title, u.full_name AS student_name
        FROM doubts d
        JOIN courses c ON d.course_id = c.id
        LEFT JOIN lessons l ON d.lesson_id = l.id
        JOIN students s ON d.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE d.id = ? AND c.teacher_id = ?
    ");
    $sel_stmt->execute([$selected_doubt_id, $teacher_id]);
    $selected_doubt = $sel_stmt->fetch();
    
    if ($selected_doubt) {
        // Mark all student replies in this doubt as Read
        $up_read = $pdo->prepare("
            UPDATE doubt_replies dr
            SET dr.is_read = 1 
            WHERE dr.doubt_id = ? 
              AND dr.user_id IN (SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Student')
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
    <div>
        <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">Student Doubt Resolutions</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant">Resolve questions and clarify concepts submitted by students enrolled in your courses.</p>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        
        <!-- Left Side: List of Doubts -->
        <div class="lg:col-span-5 space-y-md">
            <div class="glass-card rounded-xl p-md bg-white">
                <div class="border-b border-outline-variant/30 pb-xs mb-sm flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Submissions</h3>
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
                        <p class="font-body-md text-body-md">No student doubts found for your courses.</p>
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
                                    <span class="text-xs font-semibold text-primary"><?php echo e($d['student_name']); ?></span>
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
                <div class="glass-card rounded-xl p-xl h-full flex flex-col justify-center items-center text-center text-on-surface-variant min-h-[350px] bg-white">
                    <span class="material-symbols-outlined text-secondary text-5xl mb-sm">chat</span>
                    <p class="font-body-md text-body-md font-semibold">No Doubt Selected</p>
                    <p class="text-xs text-on-surface-variant/70">Select a doubt thread from the left pane to view details and send answers.</p>
                </div>
            <?php else: ?>
                <div class="glass-card rounded-xl p-md md:p-lg space-y-md bg-white">
                    <!-- Title & Actions Row -->
                    <div class="border-b border-outline-variant/30 pb-sm mb-md flex justify-between items-start">
                        <div class="space-y-xs">
                            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($selected_doubt['title']); ?></h3>
                            <p class="text-xs text-on-surface-variant">
                                Student: <strong><?php echo e($selected_doubt['student_name']); ?></strong> |
                                Course: <strong><?php echo e($selected_doubt['course_title']); ?></strong>
                                <?php if ($selected_doubt['lesson_title']): ?>
                                    | Lesson: <strong><?php echo e($selected_doubt['lesson_title']); ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-sm">
                            <span class="px-sm py-1 rounded-full text-xs font-bold <?php 
                                echo ($selected_doubt['status'] === 'Resolved') ? 'bg-green-500/10 text-green-600' : (($selected_doubt['status'] === 'Answered') ? 'bg-blue-500/10 text-blue-600' : 'bg-amber-500/10 text-amber-600'); 
                            ?>"><?php echo $selected_doubt['status']; ?></span>
                            
                            <?php if ($selected_doubt['status'] !== 'Resolved'): ?>
                                <form action="doubts.php" method="POST" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="resolve_doubt">
                                    <input type="hidden" name="doubt_id" value="<?php echo $selected_doubt['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-xs bg-green-500 text-white hover:bg-green-600 px-sm py-1 rounded-lg text-xs font-bold transition shadow border-0">
                                        <span class="material-symbols-outlined text-[14px]">done</span>
                                        Mark Resolved
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
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
                            <p class="text-xs text-on-surface-variant italic py-xs text-center">No replies posted yet. Send a response below to answer.</p>
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
                            <textarea name="reply" rows="3" class="w-full p-2 border border-outline-variant/30 rounded-lg text-xs bg-white" style="color:black !important;" placeholder="Write your reply/resolution to the student..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md transition-colors shadow border-0">
                                Send Answer & Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter Tabs
    const filterTabs = document.querySelectorAll('.filter-tab');
    const doubtItems = document.querySelectorAll('.doubt-item');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            filterTabs.forEach(t => {
                t.classList.remove('bg-primary/10', 'text-primary', 'font-bold');
                t.classList.add('text-on-surface-variant');
            });
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
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
