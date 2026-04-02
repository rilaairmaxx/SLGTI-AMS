<?php
require_once "config/db.php";

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    session_start();
    if (!isset($_SESSION['user_id'])) { header('Content-Type: application/json'); echo json_encode([]); exit(); }
    $uid    = intval($_SESSION['user_id']);
    $role   = $_SESSION['role'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // ── Mark one or all as read ──
    if ($action === 'mark_read') {
        $nid = intval($_POST['id'] ?? 0);
        if ($nid) $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute() || true;
        $s = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $s->bind_param("ii", $nid, $uid); $s->execute();
        header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'mark_all_read') {
        $s = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $s->bind_param("i", $uid); $s->execute();
        header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit();
    }

    // ── Get unread count (for bell badge) ──
    if ($action === 'count') {
        $s = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=? AND is_read=0");
        $s->bind_param("i", $uid); $s->execute();
        $cnt = $s->get_result()->fetch_assoc()['cnt'] ?? 0;
        header('Content-Type: application/json'); echo json_encode(['count' => (int)$cnt]); exit();
    }

    header('Content-Type: application/json'); echo json_encode([]); exit();
}

require_once "includes/header.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$uid  = intval($_SESSION['user_id']);
$role = $_SESSION['role'];

// ── Ensure notifications table exists ──
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        ENUM('low_attendance','new_enrollment','general') DEFAULT 'general',
    title       VARCHAR(120) NOT NULL,
    message     TEXT NOT NULL,
    link        VARCHAR(200) DEFAULT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Generate low-attendance alerts (admin only, run on page load) ──
if ($role === 'admin') {
    // Find students below 75% attendance per course
    $lowRes = $conn->query("
        SELECT s.id AS student_id, s.student_name, c.id AS course_id,
               c.course_name, c.course_code,
               COUNT(a.id) AS total,
               SUM(CASE WHEN a.status='Present' OR a.status='Late' THEN 1 ELSE 0 END) AS present,
               ROUND(SUM(CASE WHEN a.status='Present' OR a.status='Late' THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(a.id),0),1) AS pct
        FROM enrollments e
        JOIN students s  ON s.id = e.student_id
        JOIN courses  c  ON c.id = e.course_id
        JOIN attendance a ON a.enrollment_id = e.id
        WHERE e.status = 'active'
        GROUP BY e.id
        HAVING total >= 5 AND pct < 75
    ");
    if ($lowRes) {
        while ($row = $lowRes->fetch_assoc()) {
            // Only insert if no unread alert for this student+course already exists today
            $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='low_attendance' AND message LIKE ? AND DATE(created_at)=CURDATE() LIMIT 1");
            $like = '%' . $row['student_name'] . '%' . $row['course_code'] . '%';
            $chk->bind_param("is", $uid, $like);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $title = "Low Attendance: " . $row['student_name'];
                $msg   = $row['student_name'] . " has " . $row['pct'] . "% attendance in " . $row['course_code'] . " — " . $row['course_name'] . ". Below 75% threshold.";
                $link  = "reports.php";
                $ins   = $conn->prepare("INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)");
                $ins->bind_param("issss", $uid, 'low_attendance', $title, $msg, $link);
                $ins->execute();
            }
        }
    }
}

// ── Fetch notifications for current user ──
$filter   = $_GET['filter'] ?? 'all';
$whereSql = $filter === 'unread' ? "AND is_read=0" : ($filter === 'read' ? "AND is_read=1" : "");
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? {$whereSql} ORDER BY created_at DESC LIMIT 100");
$stmt->bind_param("i", $uid);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unreadCount = 0;
$s = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=? AND is_read=0");
$s->bind_param("i", $uid); $s->execute();
$unreadCount = $s->get_result()->fetch_assoc()['cnt'] ?? 0;
?>
<style>
:root { --royal:#0a2d6e;--mid:#1456c8;--light:#f0f4fa;--border:#e4eaf3;--dark:#0d1b2e;--muted:#5a6e87;--green:#059669;--red:#dc2626;--amber:#d97706; }

.nt-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:14px; }
.nt-top-left { display:flex;align-items:center;gap:14px; }
.nt-top-icon { width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--royal),var(--mid));display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;box-shadow:0 4px 14px rgba(10,45,110,.25);flex-shrink:0; }
.nt-top-title { font-size:1.25rem;font-weight:800;color:var(--dark);margin:0 0 3px; }
.nt-top-sub { font-size:.8rem;color:var(--muted);margin:0; }
.nt-date-pill { display:inline-flex;align-items:center;gap:7px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:7px 16px;font-size:.8rem;font-weight:600;color:var(--muted); }

/* Filter tabs */
.nt-tabs { display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap; }
.nt-tab { padding:7px 18px;border-radius:20px;font-size:.8rem;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--muted);cursor:pointer;text-decoration:none;transition:all .2s; }
.nt-tab:hover,.nt-tab.active { background:linear-gradient(135deg,var(--mid),var(--royal));color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(10,45,110,.2); }
.nt-mark-all { margin-left:auto;padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--muted);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px; }
.nt-mark-all:hover { border-color:var(--green);color:var(--green); }

/* Card */
.nt-card { background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(10,45,110,.09);border:1px solid var(--border);overflow:hidden; }
.nt-card-head { background:linear-gradient(135deg,var(--royal),var(--mid));padding:16px 22px;display:flex;align-items:center;gap:12px; }
.nt-card-head h5 { margin:0;font-size:.93rem;font-weight:700;color:#fff; }
.nt-card-head .nt-badge { margin-left:auto;background:rgba(255,255,255,.18);color:#fff;border-radius:20px;padding:3px 12px;font-size:.72rem;font-weight:700; }

/* Notification item */
.nt-item { display:flex;align-items:flex-start;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border);transition:background .15s;cursor:pointer; }
.nt-item:last-child { border-bottom:none; }
.nt-item:hover { background:#f7f9fc; }
.nt-item.unread { background:linear-gradient(135deg,#f0f4fa,#f8fafd);border-left:3px solid var(--mid); }
.nt-item.unread:hover { background:#eff6ff; }

.nt-ico { width:40px;height:40px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.95rem; }
.nt-ico.low_attendance { background:#fff1f1;color:var(--red); }
.nt-ico.new_enrollment { background:#ecfdf5;color:var(--green); }
.nt-ico.general        { background:#eff6ff;color:var(--mid); }

.nt-body { flex:1;min-width:0; }
.nt-title { font-size:.86rem;font-weight:700;color:var(--dark);margin-bottom:3px;display:flex;align-items:center;gap:8px; }
.nt-unread-dot { width:8px;height:8px;border-radius:50%;background:var(--mid);flex-shrink:0; }
.nt-msg { font-size:.78rem;color:var(--muted);line-height:1.5; }
.nt-meta { font-size:.7rem;color:#c8d0db;margin-top:5px;display:flex;align-items:center;gap:6px; }
.nt-meta a { color:var(--mid);font-weight:700;text-decoration:none; }
.nt-meta a:hover { text-decoration:underline; }

.nt-actions { display:flex;flex-direction:column;gap:4px;flex-shrink:0; }
.nt-act-btn { width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;cursor:pointer;transition:all .15s; }
.nt-act-btn.read:hover { background:#ecfdf5;border-color:var(--green); }
.nt-act-btn.read i { color:var(--green); }

/* Empty */
.nt-empty { text-align:center;padding:56px 24px;color:#94a3b8; }
.nt-empty i { font-size:2.5rem;display:block;margin-bottom:14px;opacity:.25; }
.nt-empty p { font-size:.9rem;margin:0; }

/* Toast */
#ntToast { position:fixed;bottom:28px;right:28px;z-index:9999;min-width:260px;max-width:340px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(10,45,110,.18);border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:12px;transform:translateY(120%);opacity:0;transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s ease;pointer-events:none; }

[data-theme="dark"] .nt-top-title { color:#e2e8f0; }
[data-theme="dark"] .nt-top-sub { color:#94a3b8; }
[data-theme="dark"] .nt-date-pill { background:#1e293b;border-color:#334155;color:#94a3b8; }
[data-theme="dark"] .nt-tab { background:#1e293b;border-color:#334155;color:#94a3b8; }
[data-theme="dark"] .nt-tab:hover,
[data-theme="dark"] .nt-tab.active { background:linear-gradient(135deg,#1456c8,#0a2d6e);color:#fff;border-color:transparent; }
[data-theme="dark"] .nt-mark-all { background:#1e293b;border-color:#334155;color:#94a3b8; }
[data-theme="dark"] .nt-mark-all:hover { border-color:#059669;color:#059669; }
[data-theme="dark"] .nt-card { background:#1e293b;border-color:#334155;box-shadow:0 4px 24px rgba(0,0,0,.3); }
[data-theme="dark"] .nt-card-head { background:linear-gradient(135deg,#1e293b,#0f172a); }
[data-theme="dark"] .nt-item { border-bottom-color:#334155; }
[data-theme="dark"] .nt-item:hover { background:#0f172a; }
[data-theme="dark"] .nt-item.unread { background:linear-gradient(135deg,#1e293b,#0f172a);border-left-color:#1456c8; }
[data-theme="dark"] .nt-item.unread:hover { background:#0f172a; }
[data-theme="dark"] .nt-ico.low_attendance { background:rgba(220,38,38,.15);color:#fca5a5; }
[data-theme="dark"] .nt-ico.new_enrollment { background:rgba(5,150,105,.15);color:#6ee7b7; }
[data-theme="dark"] .nt-ico.general { background:rgba(20,86,200,.15);color:#93c5fd; }
[data-theme="dark"] .nt-title { color:#e2e8f0; }
[data-theme="dark"] .nt-unread-dot { background:#1456c8; }
[data-theme="dark"] .nt-msg { color:#94a3b8; }
[data-theme="dark"] .nt-meta { color:#64748b; }
[data-theme="dark"] .nt-meta a { color:#93c5fd; }
[data-theme="dark"] .nt-meta a:hover { color:#1456c8; }
[data-theme="dark"] .nt-act-btn { background:#1e293b;border-color:#334155; }
[data-theme="dark"] .nt-empty { color:#64748b; }
[data-theme="dark"] #ntToast { background:#1e293b;border-color:#334155;box-shadow:0 8px 32px rgba(0,0,0,.4); }
[data-theme="dark"] #ntToastTitle { color:#e2e8f0; }
[data-theme="dark"] #ntToastMsg { color:#94a3b8; }
</style>

<!-- PAGE HEADER -->
<div class="nt-top">
    <div class="nt-top-left">
        <div class="nt-top-icon"><i class="fas fa-bell"></i></div>
        <div>
            <h1 class="nt-top-title">Notifications</h1>
            <p class="nt-top-sub">
                <?php echo $unreadCount > 0 ? "<strong style='color:var(--mid);'>{$unreadCount}</strong> unread notification" . ($unreadCount > 1 ? 's' : '') : 'All caught up'; ?>
            </p>
        </div>
    </div>
    <div class="nt-date-pill"><i class="fas fa-calendar-alt"></i><?php echo date('l, d M Y'); ?></div>
</div>

<!-- FILTER TABS -->
<div class="nt-tabs">
    <a href="?filter=all"    class="nt-tab <?php echo ($filter==='all'    ? 'active' : ''); ?>"><i class="fas fa-list"></i> All</a>
    <a href="?filter=unread" class="nt-tab <?php echo ($filter==='unread' ? 'active' : ''); ?>"><i class="fas fa-circle" style="font-size:.5rem;"></i> Unread <?php echo $unreadCount > 0 ? "({$unreadCount})" : ''; ?></a>
    <a href="?filter=read"   class="nt-tab <?php echo ($filter==='read'   ? 'active' : ''); ?>"><i class="fas fa-check-double"></i> Read</a>
    <?php if ($unreadCount > 0): ?>
    <button class="nt-mark-all" onclick="markAllRead()">
        <i class="fas fa-check-double"></i> Mark all as read
    </button>
    <?php endif; ?>
</div>

<!-- NOTIFICATIONS LIST -->
<div class="nt-card">
    <div class="nt-card-head">
        <div style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;">
            <i class="fas fa-bell"></i>
        </div>
        <h5>Notification Centre</h5>
        <span class="nt-badge"><?php echo count($notifs); ?> total</span>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="nt-empty">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications<?php echo $filter !== 'all' ? ' in this category' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifs as $n):
            $isUnread = !$n['is_read'];
            $typeIco  = ['low_attendance'=>'fa-exclamation-triangle','new_enrollment'=>'fa-user-check','general'=>'fa-info-circle'];
            $ico      = $typeIco[$n['type']] ?? 'fa-bell';
            $ago      = '';
            $diff     = time() - strtotime($n['created_at']);
            if ($diff < 60)          $ago = 'Just now';
            elseif ($diff < 3600)    $ago = floor($diff/60) . 'm ago';
            elseif ($diff < 86400)   $ago = floor($diff/3600) . 'h ago';
            else                     $ago = date('d M Y', strtotime($n['created_at']));
        ?>
        <div class="nt-item <?php echo $isUnread ? 'unread' : ''; ?>" id="notif-<?php echo $n['id']; ?>"
             onclick="markRead(<?php echo $n['id']; ?>, this)">
            <div class="nt-ico <?php echo htmlspecialchars($n['type']); ?>">
                <i class="fas <?php echo $ico; ?>"></i>
            </div>
            <div class="nt-body">
                <div class="nt-title">
                    <?php if ($isUnread): ?><span class="nt-unread-dot"></span><?php endif; ?>
                    <?php echo htmlspecialchars($n['title']); ?>
                </div>
                <div class="nt-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                <div class="nt-meta">
                    <i class="fas fa-clock" style="font-size:.6rem;"></i> <?php echo $ago; ?>
                    <?php if ($n['link']): ?>
                        &bull; <a href="<?php echo htmlspecialchars($n['link']); ?>" onclick="event.stopPropagation()">View →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isUnread): ?>
            <div class="nt-actions">
                <button class="nt-act-btn read" title="Mark as read" onclick="event.stopPropagation();markRead(<?php echo $n['id']; ?>, document.getElementById('notif-<?php echo $n['id']; ?>'))">
                    <i class="fas fa-check"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- TOAST -->
<div id="ntToast">
    <div id="ntToastIco" style="width:34px;height:34px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.95rem;"></div>
    <div><div id="ntToastTitle" style="font-size:.8rem;font-weight:800;color:var(--dark);"></div><div id="ntToastMsg" style="font-size:.74rem;color:var(--muted);"></div></div>
</div>

<script>
function markRead(id, el) {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('id', id);
    fetch('notifications.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.success && el) {
                el.classList.remove('unread');
                const dot = el.querySelector('.nt-unread-dot');
                if (dot) dot.remove();
                const actions = el.querySelector('.nt-actions');
                if (actions) actions.remove();
                // Update bell badge
                const badge    = document.getElementById('notifBadge');
                const navBadge = document.getElementById('notifNavBadge');
                let cur = parseInt(badge?.textContent || '0') - 1;
                if (cur <= 0) {
                    if (badge)    badge.style.display    = 'none';
                    if (navBadge) navBadge.style.display = 'none';
                } else {
                    if (badge)    badge.textContent    = cur;
                    if (navBadge) navBadge.textContent = cur;
                }
            }
        });
}

function markAllRead() {
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fetch('notifications.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ntToastShow('success', 'All notifications marked as read.');
                setTimeout(() => location.reload(), 1000);
            }
        });
}

function ntToastShow(type, message) {
    const t   = document.getElementById('ntToast');
    const ico = document.getElementById('ntToastIco');
    const ttl = document.getElementById('ntToastTitle');
    const msg = document.getElementById('ntToastMsg');
    ico.style.background = type === 'success' ? '#ecfdf5' : '#fff1f1';
    ico.style.color      = type === 'success' ? '#059669' : '#dc2626';
    ico.innerHTML        = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';
    ttl.textContent = type === 'success' ? 'Done' : 'Error';
    msg.textContent = message;
    t.style.pointerEvents = 'auto'; t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    setTimeout(() => { t.style.transform='translateY(120%)'; t.style.opacity='0'; t.style.pointerEvents='none'; }, 3500);
}
</script>

<?php include "includes/footer.php"; ?>
