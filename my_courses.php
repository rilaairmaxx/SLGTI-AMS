<?php
require_once "includes/auth.php";
require_once "config/db.php";

// Lecturer-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: dashboard.php");
    exit();
}

$lecturerId = $_SESSION['user_id'];

// Fetch courses assigned to this lecturer
$stmt = $conn->prepare("
    SELECT c.id, c.course_code, c.course_name, c.description, c.department, c.status,
           COUNT(e.id) AS student_count
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
    WHERE c.lecturer_id = ?
    GROUP BY c.id
    ORDER BY c.department ASC, c.course_name ASC
");
$stmt->bind_param("i", $lecturerId);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
while ($r = $courses_result->fetch_assoc()) $courses[] = $r;

$total_courses  = count($courses);
$total_students = array_sum(array_column($courses, 'student_count'));
$active_courses = count(array_filter($courses, fn($c) => $c['status'] === 'active'));

include "includes/header.php";
?>
<style>
:root {
    --royal:#0a2d6e; --mid:#1456c8; --accent:#1e90ff;
    --light:#f0f4fa; --border:#e4eaf3; --dark:#0d1b2e;
    --muted:#5a6e87; --green:#059669; --red:#dc2626; --amber:#d97706;
}
.mc-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:14px; }
.mc-top-left { display:flex; align-items:center; gap:14px; }
.mc-top-icon { width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,var(--royal),var(--mid)); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; box-shadow:0 4px 14px rgba(10,45,110,.25); flex-shrink:0; }
.mc-top-title { font-size:1.25rem; font-weight:800; color:var(--dark); margin:0 0 3px; }
.mc-top-sub { font-size:.8rem; color:var(--muted); margin:0; }

.mc-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:18px; margin-bottom:28px; }
.mc-stat { background:#fff; border-radius:16px; box-shadow:0 2px 16px rgba(10,45,110,.08); border:1px solid var(--border); padding:20px 18px; display:flex; align-items:center; gap:14px; position:relative; overflow:hidden; }
.mc-stat::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
.mc-stat.blue::before { background:linear-gradient(to bottom,var(--mid),var(--royal)); }
.mc-stat.green::before { background:linear-gradient(to bottom,#10b981,var(--green)); }
.mc-stat.amber::before { background:linear-gradient(to bottom,#f59e0b,var(--amber)); }
.mc-stat-icon { width:46px; height:46px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.mc-stat.blue .mc-stat-icon { background:#dbeafe; color:var(--mid); }
.mc-stat.green .mc-stat-icon { background:#d1fae5; color:var(--green); }
.mc-stat.amber .mc-stat-icon { background:#fef3c7; color:var(--amber); }
.mc-stat-num { font-size:1.8rem; font-weight:800; color:var(--dark); line-height:1; margin-bottom:3px; }
.mc-stat-lbl { font-size:.74rem; color:var(--muted); font-weight:500; text-transform:uppercase; letter-spacing:.04em; }

.mc-card { background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(10,45,110,.08); border:1px solid var(--border); overflow:hidden; }
.mc-card-head { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px; }
.mc-card-head h5 { margin:0; font-size:.93rem; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
.mc-card-head h5 i { color:var(--mid); }

.mc-search-wrap { position:relative; }
.mc-search-ico { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.85rem; pointer-events:none; }
.mc-search-input { border:1.5px solid var(--border); border-radius:9px; padding:9px 14px 9px 34px; font-size:.86rem; font-family:inherit; background:#f8fafd; color:var(--dark); width:220px; transition:border-color .2s; }
.mc-search-input:focus { outline:none; border-color:var(--mid); box-shadow:0 0 0 3px rgba(20,86,200,.1); background:#fff; }

.mc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px; padding:22px; }

.mc-course-card { background:#fff; border:1.5px solid var(--border); border-radius:16px; padding:20px; transition:transform .2s,box-shadow .2s; position:relative; overflow:hidden; }
.mc-course-card:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(10,45,110,.12); border-color:#bfdbfe; }
.mc-course-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,var(--mid),var(--accent)); border-radius:16px 16px 0 0; }

.mc-dept-badge { display:inline-flex; align-items:center; gap:5px; background:var(--light); color:var(--muted); border:1px solid var(--border); border-radius:20px; padding:3px 10px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-bottom:12px; }
.mc-course-code { font-size:.78rem; font-weight:800; color:var(--mid); font-family:monospace; letter-spacing:.06em; background:#eff6ff; border:1px solid #bfdbfe; border-radius:7px; padding:3px 9px; display:inline-block; margin-bottom:8px; }
.mc-course-name { font-size:1rem; font-weight:800; color:var(--dark); margin:0 0 6px; line-height:1.3; }
.mc-course-desc { font-size:.78rem; color:var(--muted); line-height:1.5; margin-bottom:14px; min-height:36px; }

.mc-course-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding-top:12px; border-top:1px solid var(--border); }
.mc-meta-item { display:flex; align-items:center; gap:5px; font-size:.75rem; color:var(--muted); font-weight:500; }
.mc-meta-item i { font-size:.7rem; }
.mc-meta-item.students { color:var(--mid); font-weight:700; }

.mc-status-badge { margin-left:auto; display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:.68rem; font-weight:700; }
.mc-status-badge.active { background:#ecfdf5; color:#065f46; border:1px solid #6ee7b7; }
.mc-status-badge.inactive { background:#fff1f1; color:#991b1b; border:1px solid #fca5a5; }

.mc-empty { text-align:center; padding:52px 24px; color:#94a3b8; }
.mc-empty i { font-size:2.4rem; display:block; margin-bottom:12px; opacity:.3; }
.mc-empty p { font-size:.88rem; margin:0; }

.mc-no-results { display:none; text-align:center; padding:32px; color:#94a3b8; font-size:.86rem; }
.mc-no-results i { font-size:1.6rem; display:block; margin-bottom:8px; opacity:.3; }

@media(max-width:576px) {
    .mc-grid { grid-template-columns:1fr; padding:14px; }
    .mc-search-input { width:160px; }
}
</style>

<div class="mc-top">
    <div class="mc-top-left">
        <div class="mc-top-icon"><i class="fas fa-book-open"></i></div>
        <div>
            <h1 class="mc-top-title">My Courses</h1>
            <p class="mc-top-sub">Courses assigned to you</p>
        </div>
    </div>
    <a href="timetable.php" style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--mid),var(--royal));color:#fff;border-radius:11px;padding:10px 18px;font-size:.85rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(10,45,110,.22);">
        <i class="fas fa-calendar-week"></i> View Timetable
    </a>
</div>

<!-- Stats -->
<div class="mc-stats">
    <div class="mc-stat blue">
        <div class="mc-stat-icon"><i class="fas fa-book-open"></i></div>
        <div>
            <div class="mc-stat-num"><?php echo $total_courses; ?></div>
            <div class="mc-stat-lbl">Assigned Courses</div>
        </div>
    </div>
    <div class="mc-stat green">
        <div class="mc-stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="mc-stat-num"><?php echo $total_students; ?></div>
            <div class="mc-stat-lbl">Total Students</div>
        </div>
    </div>
    <div class="mc-stat amber">
        <div class="mc-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="mc-stat-num"><?php echo $active_courses; ?></div>
            <div class="mc-stat-lbl">Active Courses</div>
        </div>
    </div>
</div>

<!-- Course cards -->
<div class="mc-card">
    <div class="mc-card-head">
        <h5><i class="fas fa-list-ul"></i> Course List</h5>
        <div class="mc-search-wrap">
            <i class="fas fa-search mc-search-ico"></i>
            <input type="text" id="mcSearch" class="mc-search-input" placeholder="Search courses…">
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <div class="mc-empty">
            <i class="fas fa-book"></i>
            <p>No courses have been assigned to you yet. Contact your administrator.</p>
        </div>
    <?php else: ?>
        <div class="mc-grid" id="mcGrid">
            <?php foreach ($courses as $c):
                $deptColors = ['ICT'=>'#1456c8','Mechanical'=>'#d97706','Automotive'=>'#059669','Electrical'=>'#7c3aed','Food Technology'=>'#db2777','Construction'=>'#0891b2'];
                $dc = $deptColors[$c['department']] ?? '#5a6e87';
            ?>
            <div class="mc-course-card" data-search="<?php echo strtolower(htmlspecialchars($c['course_code'].' '.$c['course_name'].' '.$c['department'])); ?>">
                <?php if ($c['department']): ?>
                <span class="mc-dept-badge" style="color:<?php echo $dc; ?>;border-color:<?php echo $dc; ?>22;background:<?php echo $dc; ?>11;">
                    <i class="fas fa-building" style="font-size:.6rem;"></i>
                    <?php echo htmlspecialchars($c['department']); ?>
                </span>
                <?php endif; ?>
                <div>
                    <span class="mc-course-code"><?php echo htmlspecialchars($c['course_code']); ?></span>
                </div>
                <h3 class="mc-course-name"><?php echo htmlspecialchars($c['course_name']); ?></h3>
                <p class="mc-course-desc">
                    <?php echo $c['description'] ? htmlspecialchars(mb_strimwidth($c['description'], 0, 100, '…')) : '<em style="color:#c8d0db;">No description provided.</em>'; ?>
                </p>
                <div class="mc-course-meta">
                    <span class="mc-meta-item students">
                        <i class="fas fa-users"></i>
                        <?php echo $c['student_count']; ?> student<?php echo $c['student_count'] != 1 ? 's' : ''; ?>
                    </span>
                    <span class="mc-status-badge <?php echo $c['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle" style="font-size:.45rem;"></i>
                        <?php echo ucfirst($c['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mc-no-results" id="mcNoResults">
            <i class="fas fa-search"></i>
            No courses match your search.
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('mcSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    let visible = 0;
    document.querySelectorAll('.mc-course-card').forEach(card => {
        const match = card.dataset.search.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const noRes = document.getElementById('mcNoResults');
    if (noRes) noRes.style.display = (visible === 0 && q !== '') ? 'block' : 'none';
});
</script>

<?php include "includes/footer.php"; ?>
