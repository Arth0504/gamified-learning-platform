<?php
$page_title = "Course Feedback Analytics";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Fetch all feedback for courses taught by this teacher
$fb_query = "
    SELECT fb.*, c.title AS course_title, u.full_name AS student_name
    FROM course_feedback fb
    JOIN courses c ON fb.course_id = c.id
    JOIN students s ON fb.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY fb.created_at DESC
";
$fb_stmt = $pdo->prepare($fb_query);
$fb_stmt->execute([$teacher_id]);
$feedbacks = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);

// Perform analytics calculations
$total_feedbacks = count($feedbacks);
$average_rating = 0;
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$positive_count = 0;
$negative_count = 0;
$neutral_count = 0;

$course_ratings = []; // Group rating by course

foreach ($feedbacks as $fb) {
    $rating = intval($fb['rating']);
    $average_rating += $rating;
    
    // Star distribution
    if (isset($rating_distribution[$rating])) {
        $rating_distribution[$rating]++;
    }
    
    // Trends (Positive/Negative/Neutral)
    if ($rating >= 4) {
        $positive_count++;
    } elseif ($rating <= 2) {
        $negative_count++;
    } else {
        $neutral_count++;
    }
    
    // Course-wise grouping
    $c_title = $fb['course_title'];
    if (!isset($course_ratings[$c_title])) {
        $course_ratings[$c_title] = ['sum' => 0, 'count' => 0];
    }
    $course_ratings[$c_title]['sum'] += $rating;
    $course_ratings[$c_title]['count']++;
}

if ($total_feedbacks > 0) {
    $average_rating = round($average_rating / $total_feedbacks, 1);
} else {
    $average_rating = 0.0;
}

// Calculate course-wise averages and top rated course
$top_rated_course = 'N/A';
$top_rated_avg = 0;
$course_averages = [];

foreach ($course_ratings as $title => $data) {
    $avg = round($data['sum'] / $data['count'], 1);
    $course_averages[] = [
        'title' => $title,
        'avg' => $avg,
        'count' => $data['count']
    ];
    if ($avg > $top_rated_avg) {
        $top_rated_avg = $avg;
        $top_rated_course = $title;
    }
}

// Sort course averages by avg DESC
usort($course_averages, function($a, $b) {
    return $b['avg'] <=> $a['avg'];
});
?>

<div class="space-y-lg">
    <!-- Top Header -->
    <div>
        <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">Course Feedback & Ratings</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant">Analyze reviews and ratings left by students to measure course satisfaction and content trends.</p>
    </div>

    <?php if ($total_feedbacks === 0): ?>
        <div class="glass-card rounded-xl p-xl text-center space-y-md bg-white">
            <span class="material-symbols-outlined text-secondary text-5xl">rate_review</span>
            <p class="font-body-md text-body-md text-on-surface-variant">No feedback has been submitted for your courses yet.</p>
        </div>
    <?php else: ?>
        <!-- Analytics KPI row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
            
            <!-- Average Rating -->
            <div class="glass-card rounded-2xl p-md border border-white/20 shadow-sm flex items-center gap-md bg-white">
                <div class="w-12 h-12 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">star</span>
                </div>
                <div>
                    <span class="text-xs font-label-sm text-on-surface-variant block uppercase">Average Rating</span>
                    <strong class="text-headline-md text-on-surface font-bold"><?php echo $average_rating; ?> / 5.0</strong>
                </div>
            </div>

            <!-- Total Feedbacks -->
            <div class="glass-card rounded-2xl p-md border border-white/20 shadow-sm flex items-center gap-md bg-white">
                <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">reviews</span>
                </div>
                <div>
                    <span class="text-xs font-label-sm text-on-surface-variant block uppercase">Total Reviews</span>
                    <strong class="text-headline-md text-on-surface font-bold"><?php echo $total_feedbacks; ?></strong>
                </div>
            </div>

            <!-- Top Rated Course -->
            <div class="glass-card rounded-2xl p-md border border-white/20 shadow-sm flex items-center gap-md bg-white">
                <div class="w-12 h-12 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">emoji_events</span>
                </div>
                <div class="min-w-0">
                    <span class="text-xs font-label-sm text-on-surface-variant block uppercase">Top Rated Course</span>
                    <strong class="text-body-md text-on-surface font-bold truncate block" title="<?php echo e($top_rated_course); ?>">
                        <?php echo e($top_rated_course); ?>
                    </strong>
                </div>
            </div>

            <!-- Positive satisfaction -->
            <?php 
                $positive_percent = round(($positive_count / $total_feedbacks) * 100);
            ?>
            <div class="glass-card rounded-2xl p-md border border-white/20 shadow-sm flex items-center gap-md bg-white">
                <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">thumb_up</span>
                </div>
                <div>
                    <span class="text-xs font-label-sm text-on-surface-variant block uppercase">Satisfaction Rate</span>
                    <strong class="text-headline-md text-on-surface font-bold"><?php echo $positive_percent; ?>%</strong>
                </div>
            </div>

        </div>

        <!-- Charts and Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
            
            <!-- Left Panel: Rating Distribution -->
            <div class="lg:col-span-4 space-y-md">
                <div class="glass-card rounded-xl p-md bg-white">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm border-b border-outline-variant/30 pb-xs">Rating Distribution</h3>
                    
                    <div class="space-y-sm pt-xs">
                        <?php for ($i = 5; $i >= 1; $i--): 
                            $count = $rating_distribution[$i];
                            $percent = $total_feedbacks > 0 ? round(($count / $total_feedbacks) * 100) : 0;
                        ?>
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-xs font-semibold text-on-surface-variant">
                                    <span class="flex items-center gap-xs text-amber-500">
                                        <?php echo $i; ?> <span class="text-[14px]">★</span>
                                    </span>
                                    <span><?php echo $count; ?> review(s) (<?php echo $percent; ?>%)</span>
                                </div>
                                <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                                    <div class="bg-amber-500 h-full transition-all duration-300" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-md bg-white">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm border-b border-outline-variant/30 pb-xs">Satisfaction Trends</h3>
                    
                    <div class="grid grid-cols-3 gap-xs pt-xs text-center text-xs font-bold">
                        <div class="p-sm bg-green-500/5 rounded-xl border border-green-500/10">
                            <span class="text-green-600 block mb-xs">Positive</span>
                            <strong class="text-lg text-green-500"><?php echo $positive_count; ?></strong>
                        </div>
                        <div class="p-sm bg-surface-container/30 rounded-xl border border-outline-variant/30">
                            <span class="text-on-surface-variant block mb-xs">Neutral</span>
                            <strong class="text-lg text-on-surface"><?php echo $neutral_count; ?></strong>
                        </div>
                        <div class="p-sm bg-red-500/5 rounded-xl border border-red-500/10">
                            <span class="text-red-600 block mb-xs">Negative</span>
                            <strong class="text-lg text-red-500"><?php echo $negative_count; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Course-wise Average Ratings -->
            <div class="lg:col-span-8 space-y-md">
                <div class="glass-card rounded-xl p-md bg-white">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm border-b border-outline-variant/30 pb-xs">Average Rating by Course</h3>
                    
                    <div class="divide-y divide-outline-variant/20">
                        <?php foreach ($course_averages as $c_avg): ?>
                            <div class="flex justify-between items-center py-2.5 first:pt-0">
                                <div class="space-y-xs min-w-0">
                                    <h4 class="font-headline-sm text-sm font-bold text-on-surface truncate pr-md" title="<?php echo e($c_avg['title']); ?>">
                                        <?php echo e($c_avg['title']); ?>
                                    </h4>
                                    <span class="text-xs text-on-surface-variant font-medium"><?php echo $c_avg['count']; ?> feedback review(s)</span>
                                </div>
                                <div class="flex items-center gap-xs shrink-0">
                                    <span class="text-amber-500 text-lg">★</span>
                                    <strong class="text-headline-sm text-on-surface font-black"><?php echo $c_avg['avg']; ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent Feedback Reviews -->
        <div class="glass-card rounded-xl p-md bg-white">
            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm border-b border-outline-variant/30 pb-xs">Recent Reviews List</h3>
            
            <div class="space-y-md pt-xs text-left max-h-[50vh] overflow-y-auto pr-xs">
                <?php foreach ($feedbacks as $fb): ?>
                    <div class="p-md border border-outline-variant/30 rounded-xl bg-surface-container-lowest hover:shadow-md transition-all">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-sm mb-sm border-b border-outline-variant/10 pb-xs">
                            <div>
                                <span class="text-xs font-semibold text-primary block uppercase">Course: <?php echo e($fb['course_title']); ?></span>
                                <span class="text-xs text-on-surface-variant">Student: <strong><?php echo e($fb['student_name']); ?></strong> | <?php echo date('M d, Y h:i A', strtotime($fb['created_at'])); ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <span class="text-lg <?php echo $s <= $fb['rating'] ? 'text-amber-500' : 'text-surface-container-highest'; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <h4 class="font-headline-sm text-sm font-bold text-on-surface mb-1"><?php echo e($fb['title']); ?></h4>
                        <p class="font-body-sm text-xs text-on-surface-variant leading-relaxed whitespace-pre-line"><?php echo e($fb['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
