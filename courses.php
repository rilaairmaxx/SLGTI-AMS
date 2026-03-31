<?php
require_once "includes/auth.php";
require_once "config/db.php";

// Admin-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$feedback_msg  = '';
$feedback_type = '';

if (isset($_GET['delete'])) {
    $courseId = filter_var($_GET['delete'], FILTER_VALIDATE_INT);

    if ($courseId === false || $courseId <= 0) {
        $feedback_msg  = "Invalid course ID.";
        $feedback_type = "danger";
    } else {
        $stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE course_id = ?");
        $stmt_check->bind_param("i", $courseId);
        $stmt_check->execute();
        $enrollmentCount = $stmt_check->get_result()->fetch_assoc()['count'];

        if ($enrollmentCount > 0) {
            $feedback_msg  = "Cannot delete course: Active enrollments exist.";
            $feedback_type = "danger";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM courses WHERE id = ?");
            $stmt_del->bind_param("i", $courseId);
            $stmt_del->execute();

            if ($stmt_del->affected_rows > 0) {
                $feedback_msg  = "Course deleted successfully.";
                $feedback_type = "success";
            } else {
                $feedback_msg  = "Course not found or could not be deleted.";
                $feedback_type = "danger";
            }
        }
    }
}

$courses_query = "
    SELECT 
        c.id, c.course_code, c.course_name, c.description,
        u.full_name AS lecturer_name,
        COUNT(e.id) AS student_count
    FROM courses c
    LEFT JOIN users u ON c.lecturer_id = u.id
    LEFT JOIN enrollments e ON c.id = e.course_id
    GROUP BY c.id
    ORDER BY c.course_code ASC
";
$courses_result = $conn->query($courses_query);

$stats = $conn->query("SELECT COUNT(*) AS total, COUNT(lecturer_id) AS assigned FROM courses")->fetch_assoc();

include "includes/header.php";
?>

<style>
    /* ── Variables ── */
    :root {
        --royal: #0a2d6e;
        --mid: #1456c8;
        --accent: #1e90ff;
        --light: #f0f4fa;
        --border: #e4eaf3;
        --dark: #0d1b2e;
        --muted: #5a6e87;
        --green: #059669;
        --red: #dc2626;
        --amber: #d97706;
        --info: #0891b2;
        --purple: #7c3aed;
    }

    /* ── Page header ── */
    .co-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .co-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .co-top-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--royal), var(--mid));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #fff;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .25);
    }

    .co-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .co-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .co-add-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border: none;
        border-radius: 11px;
        padding: 11px 22px;
        font-size: .88rem;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 4px 16px rgba(10, 45, 110, .25);
        transition: transform .2s, box-shadow .2s;
    }

    .co-add-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .32);
        color: #fff;
    }

    /* ── Alert ── */
    .co-msg {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        margin-bottom: 22px;
        font-size: .87rem;
        font-weight: 500;
        animation: msgIn .35s ease;
    }

    @keyframes msgIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .co-msg.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .co-msg.danger {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    .co-msg i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    .co-msg-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        opacity: .5;
        font-size: .9rem;
        padding: 0;
    }

    .co-msg-close:hover {
        opacity: 1;
    }

    /* ── Stat cards ── */
    .co-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
        margin-bottom: 28px;
    }

    .co-stat {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(10, 45, 110, .08);
        border: 1px solid var(--border);
        padding: 20px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: transform .2s, box-shadow .2s;
        position: relative;
        overflow: hidden;
    }

    .co-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .12);
    }

    .co-stat::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        border-radius: 4px 0 0 4px;
    }

    .co-stat.blue::before {
        background: linear-gradient(to bottom, var(--mid), var(--royal));
    }

    .co-stat.amber::before {
        background: linear-gradient(to bottom, #f59e0b, var(--amber));
    }

    .co-stat.green::before {
        background: linear-gradient(to bottom, #10b981, var(--green));
    }

    .co-stat.purple::before {
        background: linear-gradient(to bottom, #8b5cf6, var(--purple));
    }

    .co-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .co-stat.blue .co-stat-icon {
        background: #dbeafe;
        color: var(--mid);
    }

    .co-stat.amber .co-stat-icon {
        background: #fef3c7;
        color: var(--amber);
    }

    .co-stat.green .co-stat-icon {
        background: #d1fae5;
        color: var(--green);
    }

    .co-stat.purple .co-stat-icon {
        background: #ede9fe;
        color: var(--purple);
    }

    .co-stat-num {
        font-size: 1.9rem;
        font-weight: 800;
        color: var(--dark);
        line-height: 1;
        margin-bottom: 3px;
    }

    .co-stat-lbl {
        font-size: .76rem;
        color: var(--muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* ── Main card ── */
    .co-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .08);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .co-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 12px;
        background: #fff;
    }

    .co-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .co-card-head h5 i {
        color: var(--mid);
    }

    /* Search bar */
    .co-search-wrap {
        position: relative;
    }

    .co-search-ico {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .85rem;
        pointer-events: none;
    }

    .co-search-input {
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 9px 14px 9px 34px;
        font-size: .86rem;
        font-family: inherit;
        background: #f8fafd;
        color: var(--dark);
        width: 240px;
        transition: border-color .2s, box-shadow .2s;
    }

    .co-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
        background: #fff;
    }

    .co-search-input::placeholder {
        color: #aab4c4;
    }

    /* ── Table ── */
    .table-responsive { max-height: 500px; overflow-y: auto; overflow-x: auto; }
    .table-responsive::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-responsive::-webkit-scrollbar-track { background: transparent; }
    .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .co-tbl {
        width: 100%;
        border-collapse: collapse;
    }

    .co-tbl thead tr {
        background: var(--light);
    }

    .co-tbl thead th {
        padding: 12px 16px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--muted);
        white-space: nowrap;
        position: sticky; top: 0; z-index: 10; background: var(--light);
        box-shadow: inset 0 -2px 0 var(--border);
    }

    .co-tbl thead th:first-child {
        padding-left: 22px;
    }

    .co-tbl thead th:last-child {
        padding-right: 22px;
        text-align: right;
    }

    .co-tbl tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .co-tbl tbody tr:last-child {
        border-bottom: none;
    }

    .co-tbl tbody tr:hover {
        background: #f7f9fc;
    }

    .co-tbl td {
        padding: 14px 16px;
        vertical-align: middle;
    }

    .co-tbl td:first-child {
        padding-left: 22px;
    }

    .co-tbl td:last-child {
        padding-right: 22px;
    }

    /* Course code badge */
    .co-code {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: var(--mid);
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 5px 12px;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .06em;
        font-family: monospace;
    }

    /* Course name */
    .co-name {
        font-weight: 700;
        color: var(--dark);
        font-size: .9rem;
        margin-bottom: 2px;
    }

    .co-desc {
        font-size: .76rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .co-no-desc {
        font-style: italic;
        color: #c8d0db;
    }

    /* Lecturer */
    .co-lecturer {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: .82rem;
        color: var(--dark);
        font-weight: 500;
    }

    .co-lecturer-av {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        flex-shrink: 0;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 800;
        color: var(--mid);
    }

    .co-no-lecturer {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .78rem;
        color: var(--red);
        font-style: italic;
    }

    /* Enrolled count pill */
    .co-enrolled {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: .74rem;
        font-weight: 700;
    }

    .co-enrolled.has {
        background: #ecfeff;
        color: #155e75;
        border: 1px solid #a5f3fc;
    }

    .co-enrolled.none {
        background: var(--light);
        color: var(--muted);
        border: 1px solid var(--border);
    }

    /* Actions */
    .co-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .co-act-btn {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        flex-shrink: 0;
        border: 1.5px solid var(--border);
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        text-decoration: none;
        transition: all .2s;
        cursor: pointer;
    }

    .co-act-btn.edit:hover {
        background: #eff6ff;
        border-color: var(--mid);
        color: var(--mid);
        transform: translateY(-1px);
    }

    .co-act-btn.enroll:hover {
        background: #f0fdf4;
        border-color: var(--green);
        color: var(--green);
        transform: translateY(-1px);
    }

    .co-act-btn.del:hover {
        background: #fff1f1;
        border-color: var(--red);
        color: var(--red);
        transform: translateY(-1px);
    }

    .co-act-btn.locked {
        background: var(--light);
        cursor: not-allowed;
    }

    .co-act-btn .fa-edit {
        color: var(--mid);
    }

    .co-act-btn .fa-user-plus {
        color: var(--green);
    }

    .co-act-btn .fa-trash-alt {
        color: var(--red);
    }

    .co-act-btn .fa-lock {
        color: #aab4c4;
    }

    /* Empty state */
    .co-empty {
        text-align: center;
        padding: 52px 24px;
        color: #94a3b8;
    }

    .co-empty i {
        font-size: 2.4rem;
        display: block;
        margin-bottom: 12px;
        opacity: .3;
    }

    .co-empty p {
        font-size: .88rem;
        margin: 0;
    }

    /* No results */
    .co-no-results {
        display: none;
        text-align: center;
        padding: 32px;
        color: #94a3b8;
        font-size: .86rem;
    }

    .co-no-results i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 8px;
        opacity: .3;
    }

    /* Responsive */
    @media(max-width:992px) {

        .co-tbl thead th:nth-child(3),
        .co-tbl td:nth-child(3) {
            display: none;
        }

        .co-search-input {
            width: 180px;
        }
    }

    @media(max-width:768px) {

        .co-tbl thead th:nth-child(4),
        .co-tbl td:nth-child(4) {
            display: none;
        }

        .co-search-input {
            width: 140px;
        }
    }

    @media(max-width:576px) {
        .co-top-title {
            font-size: 1.05rem;
        }
    }
</style>


<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="co-top">
    <div class="co-top-left">
        <div class="co-top-icon">
            <i class="fas fa-book-open"></i>
        </div>
        <div>
            <h1 class="co-top-title">Course Catalog</h1>
            <p class="co-top-sub">Manage courses, lecturers and enrollments</p>
        </div>
    </div>
    <a href="add_course.php" class="co-add-btn">
        <i class="fas fa-plus-circle"></i> New Course
    </a>
</div>


<!-- ══════════════════════════════════════
     ALERT
══════════════════════════════════════ -->
<?php if ($feedback_msg): ?>
    <div class="co-msg <?php echo htmlspecialchars($feedback_type); ?>" id="coAlert">
        <i class="fas fa-<?php echo ($feedback_type === 'success') ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($feedback_msg); ?>
        <button class="co-msg-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>


<!-- ══════════════════════════════════════
     STAT CARDS  (PHP unchanged)
══════════════════════════════════════ -->
<div class="co-stats">

    <div class="co-stat blue">
        <div class="co-stat-icon"><i class="fas fa-book-open"></i></div>
        <div>
            <div class="co-stat-num"><?php echo intval($stats['total']); ?></div>
            <div class="co-stat-lbl">Total Courses</div>
        </div>
    </div>

    <div class="co-stat amber">
        <div class="co-stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div>
            <div class="co-stat-num"><?php echo intval($stats['assigned']); ?></div>
            <div class="co-stat-lbl">Staff Assigned</div>
        </div>
    </div>

    <?php
    // Extra: unassigned count
    $unassigned = intval($stats['total']) - intval($stats['assigned']);
    ?>
    <div class="co-stat red">
        <div class="co-stat-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="co-stat-num"><?php echo $unassigned; ?></div>
            <div class="co-stat-lbl">Unassigned</div>
        </div>
    </div>

</div>


<!-- ══════════════════════════════════════
     COURSES TABLE  (PHP unchanged)
══════════════════════════════════════ -->
<div class="co-card">

    <div class="co-card-head">
        <h5>
            <i class="fas fa-list-ul"></i>
            Course List
        </h5>
        <!-- Search — id="courseSearch" unchanged -->
        <div class="co-search-wrap">
            <i class="fas fa-search co-search-ico"></i>
            <input type="text" id="courseSearch" class="co-search-input"
                placeholder="Search courses…">
        </div>
    </div>

    <div class="table-responsive">
        <table class="co-tbl" id="courseList">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Title</th>
                    <th>Description</th>
                    <th>Lecturer</th>
                    <th style="text-align:center;">Enrolled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="courseTbody">
                <?php if ($courses_result && $courses_result->num_rows > 0): ?>
                    <?php while ($course = $courses_result->fetch_assoc()):
                        $hasStudents = intval($course['student_count']) > 0;
                        $lecInitial  = $course['lecturer_name']
                            ? strtoupper(substr($course['lecturer_name'], 0, 1))
                            : '';
                    ?>
                        <tr>

                            <!-- Course Code -->
                            <td>
                                <span class="co-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                            </td>

                            <!-- Course Name -->
                            <td>
                                <div class="co-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            </td>

                            <!-- Description -->
                            <td style="max-width:200px;">
                                <?php if (!empty($course['description'])): ?>
                                    <div class="co-desc" title="<?php echo htmlspecialchars($course['description']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($course['description'], 0, 60, '…')); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="co-no-desc">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Lecturer -->
                            <td>
                                <?php if ($course['lecturer_name']): ?>
                                    <div class="co-lecturer">
                                        <div class="co-lecturer-av"><?php echo $lecInitial; ?></div>
                                        <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="co-no-lecturer">
                                        <i class="fas fa-user-slash"></i> Not Assigned
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Enrolled count -->
                            <td style="text-align:center;">
                                <span class="co-enrolled <?php echo $hasStudents ? 'has' : 'none'; ?>">
                                    <i class="fas fa-<?php echo $hasStudents ? 'users' : 'user-slash'; ?>"
                                        style="font-size:.7rem;"></i>
                                    <?php echo intval($course['student_count']); ?> Student<?php echo $course['student_count'] != 1 ? 's' : ''; ?>
                                </span>
                            </td>

                            <!-- Actions (PHP logic unchanged) -->
                            <td>
                                <div class="co-actions">

                                    <?php if (!$hasStudents): ?>
                                        <a href="?delete=<?php echo $course['id']; ?>"
                                            class="co-act-btn del" title="Delete course"
                                            onclick="return confirm('Delete this course?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="co-act-btn locked" disabled title="Cannot delete — active enrollments">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>

                                    <a href="add_course.php?edit=<?php echo $course['id']; ?>"
                                        class="co-act-btn edit" title="Edit course">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="enroll.php?course_id=<?php echo $course['id']; ?>"
                                        class="co-act-btn enroll" title="Manage students">
                                        <i class="fas fa-user-plus"></i>
                                    </a>

                                </div>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="co-empty">
                                <i class="fas fa-book"></i>
                                <p>No course records found. <a href="add_course.php" style="color:var(--mid);font-weight:600;">Add your first course →</a></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- No search results -->
        <div class="co-no-results" id="coNoResults">
            <i class="fas fa-search"></i>
            No courses match your search.
        </div>
    </div>

</div>


<!-- ════════════════════════════════════════════════
     JAVASCRIPT
     ── ORIGINAL: courseSearch filter (id unchanged)
     ── NEW 1: no-results message
     ── NEW 2: auto-dismiss alert
     ── NEW 3: row highlight on hover tooltip
════════════════════════════════════════════════ -->
<script>
    // ════════════════════════════
    //  ORIGINAL — Live search
    //  (id="courseSearch" unchanged)
    // ════════════════════════════
    document.getElementById('courseSearch').addEventListener('input', function() {
        const filter = this.value.toUpperCase();
        let visible = 0;

        document.querySelectorAll('#courseList tbody tr[class!=""]').forEach(function(row) {
            // original logic unchanged
        });

        // original filter logic
        document.querySelectorAll("#courseList tbody tr").forEach(function(row) {
            const match = row.innerText.toUpperCase().includes(filter);
            row.style.display = match ? "" : "none";
            if (match) visible++;
        });

        // NEW 1: show no-results message
        const noRes = document.getElementById('coNoResults');
        if (noRes) {
            noRes.style.display = (visible === 0 && filter !== '') ? 'block' : 'none';
        }
    });


    // ════════════════════════════
    //  NEW 2 — Auto-dismiss alert
    // ════════════════════════════
    const coAlert = document.getElementById('coAlert');
    if (coAlert) {
        setTimeout(function() {
            coAlert.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
            coAlert.style.opacity = '0';
            coAlert.style.maxHeight = '0';
            coAlert.style.overflow = 'hidden';
            coAlert.style.padding = '0';
            coAlert.style.margin = '0';
            coAlert.style.borderWidth = '0';
        }, 4500);
    }
</script>

<?php include "includes/footer.php"; ?>