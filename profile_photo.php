<?php
// profile_photo.php — SLGTI AMS — All roles + Admin management panel
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "config/db.php";
include "includes/header.php";
$userId    = (int)$_SESSION['user_id'];
$role      = $_SESSION['role']      ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';

if ($role === 'student') {
    $q = $conn->prepare("SELECT photo, student_name AS display_name, student_number AS sub FROM students WHERE id=?");
} else {
    $q = $conn->prepare("SELECT photo, full_name AS display_name, username AS sub FROM users WHERE id=?");
}
$q->bind_param("i", $userId);
$q->execute();
$userRow      = $q->get_result()->fetch_assoc();
$currentPhoto = $userRow['photo']        ?? null;
$displayName  = $userRow['display_name'] ?? $full_name;
$subLine      = $userRow['sub']          ?? '';
$initial      = strtoupper(substr($displayName, 0, 1));

$allStaff = [];
$allStudents = [];
if ($role === 'admin') {
    $sr = $conn->query("SELECT id,full_name,username,role,photo FROM users WHERE role IN('admin','lecturer') ORDER BY role,full_name ASC");
    while ($r = $sr->fetch_assoc()) $allStaff[] = $r;
    $sr2 = $conn->query("SELECT id,student_name,student_number,photo FROM students ORDER BY student_name ASC");
    while ($r = $sr2->fetch_assoc()) $allStudents[] = $r;
}
?>
<style>
    :root {
        --royal: #0a2d6e;
        --mid: #1456c8;
        --light: #f0f4fa;
        --border: #e4eaf3;
        --dark: #0d1b2e;
        --muted: #5a6e87;
        --green: #059669;
        --red: #dc2626;
        --amber: #d97706;
    }

    /* PAGE HEADER */
    .pp-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .pp-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .pp-top-icon {
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

    .pp-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .pp-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .pp-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        color: var(--muted);
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 8px 16px;
        font-size: .82rem;
        font-weight: 600;
        text-decoration: none;
        transition: all .2s;
    }

    .pp-back-btn:hover {
        border-color: var(--mid);
        color: var(--mid);
    }

    /* CARD */
    .pp-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .pp-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pp-card-head-ico {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #fff;
        flex-shrink: 0;
    }

    .pp-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .pp-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .pp-card-body {
        padding: 28px 24px;
    }

    /* LAYOUT */
    .pp-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 24px;
        align-items: start;
        margin-bottom: 32px;
    }

    @media(max-width:992px) {
        .pp-layout {
            grid-template-columns: 1fr;
        }
    }

    /* OWN AVATAR */
    .pp-avatar-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .pp-avatar {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: 0 8px 24px rgba(10, 45, 110, .18);
        object-fit: cover;
        transition: opacity .3s;
    }

    .pp-avatar-fallback {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.6rem;
        font-weight: 800;
        color: #fff;
        border: 4px solid #fff;
        box-shadow: 0 8px 24px rgba(10, 45, 110, .18);
    }

    .pp-avatar-fallback.admin {
        background: linear-gradient(135deg, #dc2626, #991b1b);
    }

    .pp-avatar-fallback.lecturer {
        background: linear-gradient(135deg, var(--mid), var(--royal));
    }

    .pp-avatar-fallback.student {
        background: linear-gradient(135deg, var(--green), #065f46);
    }

    .pp-display-name {
        font-size: .98rem;
        font-weight: 800;
        color: var(--dark);
        text-align: center;
        margin: 0 0 2px;
    }

    .pp-display-sub {
        font-size: .76rem;
        color: var(--muted);
        text-align: center;
        margin: 0;
    }

    .pp-role-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .pp-role-pill.admin {
        background: #fff1f1;
        color: #dc2626;
    }

    .pp-role-pill.lecturer {
        background: #eff6ff;
        color: var(--mid);
    }

    .pp-role-pill.student {
        background: #f0fdf4;
        color: var(--green);
    }

    /* DROP ZONE */
    .pp-drop-zone {
        border: 2.5px dashed var(--border);
        border-radius: 16px;
        padding: 32px 24px;
        text-align: center;
        cursor: pointer;
        transition: all .25s ease;
        background: #fafbfd;
    }

    .pp-drop-zone:hover,
    .pp-drop-zone.drag-over {
        border-color: var(--mid);
        background: linear-gradient(135deg, #eff6ff, #f0f8ff);
        transform: scale(1.01);
    }

    .pp-drop-zone.drag-over {
        box-shadow: 0 0 0 4px rgba(20, 86, 200, .12);
    }

    .pp-drop-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        margin: 0 auto 12px;
        background: linear-gradient(135deg, #dbeafe, #eff6ff);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--mid);
        transition: transform .2s;
    }

    .pp-drop-zone:hover .pp-drop-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .pp-drop-title {
        font-size: .9rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 5px;
    }

    .pp-drop-sub {
        font-size: .76rem;
        color: var(--muted);
        margin-bottom: 14px;
        line-height: 1.6;
    }

    .pp-browse-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 20px;
        font-size: .84rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .25);
        transition: transform .2s;
    }

    .pp-browse-btn:hover {
        transform: translateY(-2px);
    }

    #photoInput {
        display: none;
    }

    /* PREVIEW */
    .pp-preview-wrap {
        display: none;
        margin-top: 20px;
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }

    .pp-preview-head {
        background: var(--light);
        padding: 10px 14px;
        font-size: .76rem;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pp-preview-body {
        padding: 14px;
        text-align: center;
    }

    .pp-preview-img {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .12);
    }

    .pp-preview-info {
        margin-top: 8px;
        font-size: .76rem;
        color: var(--muted);
    }

    .pp-preview-name {
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 2px;
    }

    /* PROGRESS */
    .pp-progress-wrap {
        display: none;
        margin-top: 14px;
    }

    .pp-progress-track {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
    }

    .pp-progress-fill {
        height: 100%;
        border-radius: 3px;
        width: 0%;
        background: linear-gradient(90deg, var(--mid), var(--green));
        transition: width .3s ease;
    }

    .pp-progress-lbl {
        font-size: .72rem;
        color: var(--muted);
        margin-top: 4px;
        text-align: center;
    }

    /* STATUS */
    .pp-status {
        display: none;
        align-items: center;
        gap: 10px;
        padding: 11px 14px;
        border-radius: 11px;
        margin-top: 14px;
        font-size: .84rem;
        font-weight: 500;
        animation: msgIn .3s ease;
    }

    @keyframes msgIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .pp-status.show {
        display: flex;
    }

    .pp-status.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .pp-status.error {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    /* BUTTONS */
    .pp-upload-actions {
        display: flex;
        gap: 10px;
        margin-top: 14px;
    }

    .pp-upload-btn {
        flex: 1;
        background: linear-gradient(135deg, var(--green), #065f46);
        color: #fff;
        border: none;
        border-radius: 11px;
        padding: 12px;
        font-size: .9rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 16px rgba(5, 150, 105, .25);
        transition: transform .2s;
    }

    .pp-upload-btn:hover {
        transform: translateY(-2px);
    }

    .pp-upload-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    .pp-cancel-btn {
        background: #fff;
        color: var(--muted);
        border: 1.5px solid var(--border);
        border-radius: 11px;
        padding: 12px 16px;
        font-size: .86rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 7px;
        transition: all .2s;
    }

    .pp-cancel-btn:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fff1f1;
    }

    .pp-remove-btn {
        display: none;
        width: 100%;
        margin-top: 10px;
        background: #fff;
        color: var(--red);
        border: 1.5px solid #fecaca;
        border-radius: 10px;
        padding: 9px;
        font-size: .82rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: all .2s;
    }

    .pp-remove-btn.show {
        display: flex;
    }

    .pp-remove-btn:hover {
        background: #fff1f1;
        border-color: var(--red);
    }

    /* RULES */
    .pp-rules {
        background: var(--light);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 14px 16px;
        margin-top: 18px;
    }

    .pp-rules-title {
        font-size: .76rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pp-rule-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .73rem;
        color: var(--muted);
        margin-bottom: 4px;
    }

    .pp-rule-item i {
        color: var(--green);
        font-size: .78rem;
        flex-shrink: 0;
    }

    /* ADMIN GRID */
    .adm-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .adm-tab {
        padding: 8px 18px;
        border-radius: 10px;
        font-size: .82rem;
        font-weight: 700;
        border: 1.5px solid var(--border);
        background: #fff;
        color: var(--muted);
        cursor: pointer;
        transition: all .2s;
        font-family: inherit;
    }

    .adm-tab:hover {
        border-color: var(--mid);
        color: var(--mid);
    }

    .adm-tab.active {
        background: var(--mid);
        border-color: var(--mid);
        color: #fff;
    }

    .adm-tab-content {
        display: none;
    }

    .adm-tab-content.show {
        display: block;
    }

    .adm-search-wrap {
        position: relative;
        max-width: 300px;
        margin-bottom: 16px;
    }

    .adm-search-ico {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .82rem;
        pointer-events: none;
    }

    .adm-search-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 9px 13px 9px 32px;
        font-size: .84rem;
        font-family: inherit;
        background: #f8fafd;
        color: var(--dark);
        transition: border-color .2s, box-shadow .2s;
    }

    .adm-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
        background: #fff;
    }

    .adm-search-input::placeholder {
        color: #aab4c4;
    }

    .adm-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 16px;
    }

    .adm-person-card {
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 16px;
        padding: 20px 14px;
        text-align: center;
        transition: transform .2s, box-shadow .2s, border-color .2s;
    }

    .adm-person-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .1);
        border-color: #bfdbfe;
    }

    .adm-av-wrap {
        position: relative;
        width: 72px;
        height: 72px;
        margin: 0 auto 10px;
        cursor: pointer;
    }

    .adm-av {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 4px 12px rgba(10, 45, 110, .14);
        object-fit: cover;
        display: block;
    }

    .adm-av-fallback {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 800;
        color: #fff;
        border: 3px solid #fff;
        box-shadow: 0 4px 12px rgba(10, 45, 110, .14);
    }

    .adm-av-fallback.admin {
        background: linear-gradient(135deg, #dc2626, #991b1b);
    }

    .adm-av-fallback.lecturer {
        background: linear-gradient(135deg, var(--mid), var(--royal));
    }

    .adm-av-fallback.student {
        background: linear-gradient(135deg, var(--green), #065f46);
    }

    .adm-av-overlay {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(10, 45, 110, .55);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity .2s;
        cursor: pointer;
        color: #fff;
        font-size: .9rem;
    }

    .adm-av-wrap:hover .adm-av-overlay {
        opacity: 1;
    }

    .adm-person-name {
        font-size: .86rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 2px;
    }

    .adm-person-sub {
        font-size: .72rem;
        color: var(--muted);
        margin-bottom: 8px;
    }

    .adm-role-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .67rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 12px;
    }

    .adm-role-pill.admin {
        background: #fff1f1;
        color: #dc2626;
    }

    .adm-role-pill.lecturer {
        background: #eff6ff;
        color: var(--mid);
    }

    .adm-role-pill.student {
        background: #f0fdf4;
        color: var(--green);
    }

    .adm-card-actions {
        display: flex;
        gap: 6px;
        justify-content: center;
    }

    .adm-upload-lbl {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: .75rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 3px 10px rgba(10, 45, 110, .2);
        transition: transform .2s;
        font-family: inherit;
    }

    .adm-upload-lbl:hover {
        transform: translateY(-1px);
    }

    .adm-remove-lbl {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #fff;
        color: var(--red);
        border: 1.5px solid #fecaca;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: .75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all .2s;
        font-family: inherit;
    }

    .adm-remove-lbl:hover {
        background: #fff1f1;
        border-color: var(--red);
    }

    .adm-remove-lbl.hidden {
        display: none;
    }

    .adm-file-input {
        display: none;
    }

    /* TOAST */
    .adm-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        display: none;
        align-items: center;
        gap: 10px;
        padding: 13px 18px;
        border-radius: 13px;
        max-width: 340px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .2);
        font-size: .86rem;
        font-weight: 600;
        animation: toastIn .3s ease;
    }

    @keyframes toastIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .adm-toast.show {
        display: flex;
    }

    .adm-toast.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .adm-toast.error {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    @media(max-width:576px) {
        .adm-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        }
    }

    [data-theme="dark"] .pp-top-title { color:#e2e8f0; }
    [data-theme="dark"] .pp-top-sub { color:#94a3b8; }
    [data-theme="dark"] .pp-back-btn { background:#1e293b;color:#94a3b8;border-color:#334155; }
    [data-theme="dark"] .pp-back-btn:hover { border-color:#1456c8;color:#1456c8; }
    [data-theme="dark"] .pp-card { background:#1e293b;border-color:#334155;box-shadow:0 4px 24px rgba(0,0,0,.3); }
    [data-theme="dark"] .pp-card-body { background:#1e293b; }
    [data-theme="dark"] .pp-display-name { color:#e2e8f0; }
    [data-theme="dark"] .pp-display-sub { color:#94a3b8; }
    [data-theme="dark"] .pp-avatar { border-color:#334155; }
    [data-theme="dark"] .pp-avatar-fallback { border-color:#334155; }
    [data-theme="dark"] .pp-role-pill.admin { background:rgba(220,38,38,.15);color:#fca5a5; }
    [data-theme="dark"] .pp-role-pill.lecturer { background:rgba(20,86,200,.15);color:#93c5fd; }
    [data-theme="dark"] .pp-role-pill.student { background:rgba(5,150,105,.15);color:#6ee7b7; }
    [data-theme="dark"] .pp-drop-zone { border-color:#334155;background:#0f172a;color:#94a3b8; }
    [data-theme="dark"] .pp-drop-zone:hover { border-color:#1456c8;background:linear-gradient(135deg,#1e293b,#0f172a); }
    [data-theme="dark"] .pp-drop-icon { background:linear-gradient(135deg,#1e293b,#334155);color:#94a3b8; }
    [data-theme="dark"] .pp-drop-title { color:#e2e8f0; }
    [data-theme="dark"] .pp-drop-sub { color:#94a3b8; }
    [data-theme="dark"] .pp-preview-wrap { border-color:#334155;background:#0f172a; }
    [data-theme="dark"] .pp-preview-head { background:#1e293b;color:#cbd5e1; }
    [data-theme="dark"] .pp-preview-name { color:#e2e8f0; }
    [data-theme="dark"] .pp-progress-track { background:#334155; }
    [data-theme="dark"] .pp-status.success { background:rgba(5,150,105,.15);color:#6ee7b7;border-color:#059669; }
    [data-theme="dark"] .pp-status.error { background:rgba(220,38,38,.15);color:#fca5a5;border-color:#dc2626; }
    [data-theme="dark"] .pp-upload-btn { opacity:.85; }
    [data-theme="dark"] .pp-cancel-btn { background:#1e293b;color:#94a3b8;border-color:#334155; }
    [data-theme="dark"] .pp-cancel-btn:hover { background:rgba(220,38,38,.1);border-color:#dc2626;color:#fca5a5; }
    [data-theme="dark"] .pp-remove-btn { background:#1e293b;color:#fca5a5;border-color:rgba(220,38,38,.3); }
    [data-theme="dark"] .pp-remove-btn:hover { background:rgba(220,38,38,.1);border-color:#dc2626; }
    [data-theme="dark"] .pp-rules { background:#0f172a;border-color:#334155; }
    [data-theme="dark"] .pp-rules-title { color:#cbd5e1; }
    [data-theme="dark"] .pp-rule-item { color:#94a3b8; }
    [data-theme="dark"] .adm-tab { background:#1e293b;border-color:#334155;color:#94a3b8; }
    [data-theme="dark"] .adm-tab:hover { border-color:#1456c8;color:#1456c8; }
    [data-theme="dark"] .adm-tab.active { background:#1456c8;border-color:#1456c8;color:#fff; }
    [data-theme="dark"] .adm-search-input { background:#0f172a;border-color:#334155;color:#e2e8f0; }
    [data-theme="dark"] .adm-search-input:focus { background:#1e293b;border-color:#1456c8; }
    [data-theme="dark"] .adm-search-input::placeholder { color:#64748b; }
    [data-theme="dark"] .adm-person-card { background:#1e293b;border-color:#334155; }
    [data-theme="dark"] .adm-person-card:hover { border-color:#475569;box-shadow:0 8px 24px rgba(0,0,0,.4); }
    [data-theme="dark"] .adm-av { border-color:#334155; }
    [data-theme="dark"] .adm-av-fallback { border-color:#334155; }
    [data-theme="dark"] .adm-av-overlay { background:rgba(0,0,0,.6); }
    [data-theme="dark"] .adm-person-name { color:#e2e8f0; }
    [data-theme="dark"] .adm-person-sub { color:#94a3b8; }
    [data-theme="dark"] .adm-role-pill.admin { background:rgba(220,38,38,.15);color:#fca5a5; }
    [data-theme="dark"] .adm-role-pill.lecturer { background:rgba(20,86,200,.15);color:#93c5fd; }
    [data-theme="dark"] .adm-role-pill.student { background:rgba(5,150,105,.15);color:#6ee7b7; }
    [data-theme="dark"] .adm-upload-lbl { opacity:.85; }
    [data-theme="dark"] .adm-remove-lbl { background:#1e293b;border-color:rgba(220,38,38,.3);color:#fca5a5; }
    [data-theme="dark"] .adm-remove-lbl:hover { background:rgba(220,38,38,.1);border-color:#dc2626; }
    [data-theme="dark"] .adm-toast { background:#1e293b;border-color:#334155; }
    [data-theme="dark"] .adm-toast.success { background:rgba(5,150,105,.15);color:#6ee7b7;border-color:#059669; }
    [data-theme="dark"] .adm-toast.error { background:rgba(220,38,38,.15);color:#fca5a5;border-color:#dc2626; }
</style>

<!-- PAGE HEADER -->
<div class="pp-top">
    <div class="pp-top-left">
        <div class="pp-top-icon"><i class="fas fa-camera"></i></div>
        <div>
            <h1 class="pp-top-title">Profile Photos</h1>
            <p class="pp-top-sub"><?php echo $role === 'admin' ? 'Manage your own photo and all staff &amp; student photos' : 'Upload or update your profile picture'; ?></p>
        </div>
    </div>
    <a href="dashboard.php" class="pp-back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- OWN PHOTO — ALL ROLES -->
<div class="pp-layout" style="margin-bottom:<?php echo $role === 'admin' ? '40px' : '0'; ?>;">
    <!-- Current photo card -->
    <div>
        <div class="pp-card">
            <div class="pp-card-head">
                <div class="pp-card-head-ico"><i class="fas fa-user-circle"></i></div>
                <div>
                    <h5>Your Current Photo</h5>
                    <p>Your profile picture</p>
                </div>
            </div>
            <div class="pp-card-body">
                <div class="pp-avatar-wrap">
                    <?php if ($currentPhoto): ?>
                        <img src="<?php echo htmlspecialchars($currentPhoto); ?>?v=<?php echo time(); ?>"
                            alt="Profile" class="pp-avatar" id="currentAvatarImg"
                            onerror="this.style.display='none';document.getElementById('currentAvatarFallback').style.display='flex';">
                        <div class="pp-avatar-fallback <?php echo $role; ?>" id="currentAvatarFallback" style="display:none;"><?php echo $initial; ?></div>
                    <?php else: ?>
                        <img src="" alt="" class="pp-avatar" id="currentAvatarImg" style="display:none;">
                        <div class="pp-avatar-fallback <?php echo $role; ?>" id="currentAvatarFallback"><?php echo $initial; ?></div>
                    <?php endif; ?>
                    <div>
                        <p class="pp-display-name"><?php echo htmlspecialchars($displayName); ?></p>
                        <p class="pp-display-sub"><?php echo htmlspecialchars($subLine); ?></p>
                    </div>
                    <span class="pp-role-pill <?php echo $role; ?>"><i class="fas fa-circle" style="font-size:.4rem;"></i> <?php echo ucfirst($role); ?></span>
                </div>
                <button class="pp-remove-btn <?php echo $currentPhoto ? 'show' : ''; ?>" id="removePhotoBtn" onclick="removeOwnPhoto()">
                    <i class="fas fa-trash-alt"></i> Remove My Photo
                </button>
                <div class="pp-rules">
                    <div class="pp-rules-title"><i class="fas fa-info-circle" style="color:var(--mid);"></i> Guidelines</div>
                    <div class="pp-rule-item"><i class="fas fa-check"></i> JPG, PNG, WEBP or GIF</div>
                    <div class="pp-rule-item"><i class="fas fa-check"></i> Max size: 2 MB</div>
                    <div class="pp-rule-item"><i class="fas fa-check"></i> Square images work best</div>
                    <div class="pp-rule-item"><i class="fas fa-check"></i> Old photo replaced automatically</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload zone (own) -->
    <div>
        <div class="pp-card">
            <div class="pp-card-head">
                <div class="pp-card-head-ico"><i class="fas fa-cloud-upload-alt"></i></div>
                <div>
                    <h5>Upload New Photo</h5>
                    <p>Drag &amp; drop or click to browse</p>
                </div>
            </div>
            <div class="pp-card-body">
                <div class="pp-drop-zone" id="dropZone"
                    onclick="document.getElementById('photoInput').click()"
                    ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                    <div class="pp-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="pp-drop-title">Drop your photo here</div>
                    <div class="pp-drop-sub">JPG &middot; PNG &middot; WEBP &middot; GIF &nbsp;|&nbsp; Max <strong>2 MB</strong></div>
                    <button type="button" class="pp-browse-btn" onclick="event.stopPropagation();document.getElementById('photoInput').click();">
                        <i class="fas fa-folder-open"></i> Browse Files
                    </button>
                </div>
                <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"
                    onchange="handleFileSelect(this.files[0])">

                <div class="pp-preview-wrap" id="previewWrap">
                    <div class="pp-preview-head">
                        <span><i class="fas fa-eye me-1"></i>Preview</span>
                        <span id="previewFilename" style="color:var(--muted);font-weight:500;font-size:.7rem;"></span>
                    </div>
                    <div class="pp-preview-body">
                        <img src="" alt="Preview" class="pp-preview-img" id="previewImg">
                        <div class="pp-preview-info">
                            <div class="pp-preview-name" id="previewName"></div>
                            <div id="previewSize"></div>
                        </div>
                    </div>
                </div>

                <div class="pp-progress-wrap" id="progressWrap">
                    <div class="pp-progress-track">
                        <div class="pp-progress-fill" id="progressFill"></div>
                    </div>
                    <div class="pp-progress-lbl" id="progressLbl">Uploading...</div>
                </div>

                <div class="pp-status" id="statusMsg">
                    <i class="fas fa-check-circle" id="statusIcon"></i>
                    <span id="statusText"></span>
                </div>

                <div class="pp-upload-actions" id="uploadActions" style="display:none;">
                    <button type="button" class="pp-upload-btn" id="uploadBtn" onclick="uploadOwnPhoto()">
                        <i class="fas fa-upload"></i> Upload Photo
                    </button>
                    <button type="button" class="pp-cancel-btn" onclick="resetUpload()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'admin'): ?>
    <!-- ADMIN: MANAGE ALL PHOTOS -->
    <div class="pp-card" style="margin-bottom:24px;">
        <div class="pp-card-head">
            <div class="pp-card-head-ico"><i class="fas fa-users-cog"></i></div>
            <div>
                <h5>Manage All Photos</h5>
                <p>Upload or remove photos for any staff member or student</p>
            </div>
        </div>
        <div class="pp-card-body">

            <div class="adm-tabs">
                <button class="adm-tab active" onclick="switchTab('Staff',this)">
                    <i class="fas fa-chalkboard-teacher me-1"></i> Staff (<?php echo count($allStaff); ?>)
                </button>
                <button class="adm-tab" onclick="switchTab('Students',this)">
                    <i class="fas fa-user-graduate me-1"></i> Students (<?php echo count($allStudents); ?>)
                </button>
            </div>

            <div class="adm-search-wrap">
                <i class="fas fa-search adm-search-ico"></i>
                <input type="text" class="adm-search-input" id="admSearch" placeholder="Search by name..." oninput="admFilter(this.value)">
            </div>

            <!-- Staff grid -->
            <div class="adm-tab-content show" id="tabStaff">
                <?php if (empty($allStaff)): ?>
                    <div style="text-align:center;padding:32px;color:#94a3b8;"><i class="fas fa-users-slash" style="font-size:2rem;opacity:.3;display:block;margin-bottom:10px;"></i>No staff found.</div>
                <?php else: ?>
                    <div class="adm-grid" id="staffGrid">
                        <?php foreach ($allStaff as $p):
                            $pInit = $p['full_name'] ? strtoupper(substr($p['full_name'], 0, 1)) : '?';
                            $hasP = !empty($p['photo']);
                        ?>
                            <div class="adm-person-card" data-name="<?php echo strtolower(htmlspecialchars($p['full_name'])); ?>">
                                <div class="adm-av-wrap" onclick="triggerAdmUpload('user',<?php echo $p['id']; ?>)">
                                    <?php if ($hasP): ?>
                                        <img src="<?php echo htmlspecialchars($p['photo']); ?>?v=<?php echo time(); ?>"
                                            class="adm-av" id="adm-av-user-<?php echo $p['id']; ?>"
                                            onerror="this.style.display='none';document.getElementById('adm-fb-user-<?php echo $p['id']; ?>').style.display='flex';"
                                            alt="<?php echo htmlspecialchars($p['full_name']); ?>">
                                        <div class="adm-av-fallback <?php echo $p['role']; ?>" id="adm-fb-user-<?php echo $p['id']; ?>" style="display:none;"><?php echo $pInit; ?></div>
                                    <?php else: ?>
                                        <img src="" class="adm-av" id="adm-av-user-<?php echo $p['id']; ?>" style="display:none;" alt="">
                                        <div class="adm-av-fallback <?php echo $p['role']; ?>" id="adm-fb-user-<?php echo $p['id']; ?>"><?php echo $pInit; ?></div>
                                    <?php endif; ?>
                                    <div class="adm-av-overlay"><i class="fas fa-camera"></i></div>
                                </div>
                                <div class="adm-person-name"><?php echo htmlspecialchars($p['full_name']); ?></div>
                                <div class="adm-person-sub">@<?php echo htmlspecialchars($p['username']); ?></div>
                                <div class="adm-role-pill <?php echo $p['role']; ?>"><i class="fas fa-circle" style="font-size:.35rem;"></i> <?php echo ucfirst($p['role']); ?></div>
                                <div class="adm-card-actions">
                                    <label class="adm-upload-lbl" title="Upload photo">
                                        <i class="fas fa-camera"></i> Upload
                                        <input type="file" class="adm-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                            onchange="admUploadPhoto(this,'user',<?php echo $p['id']; ?>)">
                                    </label>
                                    <button class="adm-remove-lbl <?php echo $hasP ? '' : 'hidden'; ?>"
                                        id="adm-rm-user-<?php echo $p['id']; ?>"
                                        onclick="admRemovePhoto('user',<?php echo $p['id']; ?>)" title="Remove">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Students grid -->
            <div class="adm-tab-content" id="tabStudents">
                <?php if (empty($allStudents)): ?>
                    <div style="text-align:center;padding:32px;color:#94a3b8;"><i class="fas fa-user-graduate" style="font-size:2rem;opacity:.3;display:block;margin-bottom:10px;"></i>No students found.</div>
                <?php else: ?>
                    <div class="adm-grid" id="studentsGrid">
                        <?php foreach ($allStudents as $s):
                            $sInit = $s['student_name'] ? strtoupper(substr($s['student_name'], 0, 1)) : '?';
                            $hasP = !empty($s['photo']);
                        ?>
                            <div class="adm-person-card" data-name="<?php echo strtolower(htmlspecialchars($s['student_name'])); ?>">
                                <div class="adm-av-wrap" onclick="triggerAdmUpload('student',<?php echo $s['id']; ?>)">
                                    <?php if ($hasP): ?>
                                        <img src="<?php echo htmlspecialchars($s['photo']); ?>?v=<?php echo time(); ?>"
                                            class="adm-av" id="adm-av-student-<?php echo $s['id']; ?>"
                                            onerror="this.style.display='none';document.getElementById('adm-fb-student-<?php echo $s['id']; ?>').style.display='flex';"
                                            alt="<?php echo htmlspecialchars($s['student_name']); ?>">
                                        <div class="adm-av-fallback student" id="adm-fb-student-<?php echo $s['id']; ?>" style="display:none;"><?php echo $sInit; ?></div>
                                    <?php else: ?>
                                        <img src="" class="adm-av" id="adm-av-student-<?php echo $s['id']; ?>" style="display:none;" alt="">
                                        <div class="adm-av-fallback student" id="adm-fb-student-<?php echo $s['id']; ?>"><?php echo $sInit; ?></div>
                                    <?php endif; ?>
                                    <div class="adm-av-overlay"><i class="fas fa-camera"></i></div>
                                </div>
                                <div class="adm-person-name"><?php echo htmlspecialchars($s['student_name']); ?></div>
                                <div class="adm-person-sub">#<?php echo htmlspecialchars($s['student_number']); ?></div>
                                <div class="adm-role-pill student"><i class="fas fa-circle" style="font-size:.35rem;"></i> Student</div>
                                <div class="adm-card-actions">
                                    <label class="adm-upload-lbl" title="Upload photo">
                                        <i class="fas fa-camera"></i> Upload
                                        <input type="file" class="adm-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                            onchange="admUploadPhoto(this,'student',<?php echo $s['id']; ?>)">
                                    </label>
                                    <button class="adm-remove-lbl <?php echo $hasP ? '' : 'hidden'; ?>"
                                        id="adm-rm-student-<?php echo $s['id']; ?>"
                                        onclick="admRemovePhoto('student',<?php echo $s['id']; ?>)" title="Remove">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="adm-toast" id="admToast">
        <i class="fas fa-check-circle" id="admToastIcon"></i>
        <span id="admToastText"></span>
    </div>
<?php endif; ?>

<script>
    // ── OWN PHOTO ──
    let selectedFile = null;

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('drag-over');
        const f = e.dataTransfer.files[0];
        if (f) handleFileSelect(f);
    }

    function handleFileSelect(file) {
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            showStatus('error', 'Invalid file type. Use JPG, PNG, WEBP or GIF.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showStatus('error', 'File too large. Max 2 MB.');
            return;
        }
        selectedFile = file;
        hideStatus();
        const r = new FileReader();
        r.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = file.name;
            document.getElementById('previewSize').textContent = fmtSize(file.size);
            document.getElementById('previewFilename').textContent = file.name;
            document.getElementById('previewWrap').style.display = 'block';
            document.getElementById('uploadActions').style.display = 'flex';
        };
        r.readAsDataURL(file);
    }

    function uploadOwnPhoto() {
        if (!selectedFile) return;
        const btn = document.getElementById('uploadBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
        const pw = document.getElementById('progressWrap'),
            pf = document.getElementById('progressFill'),
            pl = document.getElementById('progressLbl');
        pw.style.display = 'block';
        const fd = new FormData();
        fd.append('photo', selectedFile);
        const xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const p = Math.round(e.loaded / e.total * 100);
                pf.style.width = p + '%';
                pl.textContent = 'Uploading... ' + p + '%';
            }
        });
        xhr.addEventListener('load', () => {
            pw.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Upload Photo';
            try {
                const d = JSON.parse(xhr.responseText);
                if (d.success) {
                    showStatus('success', d.message);
                    updateOwnAvatar(d.photo_url);
                    document.getElementById('removePhotoBtn').classList.add('show');
                    resetUpload();
                } else {
                    showStatus('error', d.message || 'Upload failed.');
                }
            } catch (e) {
                showStatus('error', 'Unexpected server response.');
            }
        });
        xhr.addEventListener('error', () => {
            pw.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Upload Photo';
            showStatus('error', 'Network error.');
        });
        xhr.open('POST', 'uploads/upload_photo.php');
        xhr.send(fd);
    }

    function removeOwnPhoto() {
        if (!confirm('Remove your profile photo?')) return;
        fetch('uploads/remove_photo.php', {
            method: 'POST'
        }).then(r => r.json()).then(d => {
            if (d.success) {
                document.getElementById('currentAvatarImg').style.display = 'none';
                document.getElementById('currentAvatarFallback').style.display = 'flex';
                document.getElementById('removePhotoBtn').classList.remove('show');
                showStatus('success', 'Photo removed.');
            } else showStatus('error', d.message || 'Could not remove.');
        }).catch(() => showStatus('error', 'Network error.'));
    }

    function updateOwnAvatar(url) {
        const i = document.getElementById('currentAvatarImg'),
            f = document.getElementById('currentAvatarFallback');
        i.src = url;
        i.style.display = 'block';
        f.style.display = 'none';
    }

    function resetUpload() {
        selectedFile = null;
        document.getElementById('photoInput').value = '';
        document.getElementById('previewWrap').style.display = 'none';
        document.getElementById('uploadActions').style.display = 'none';
        document.getElementById('progressWrap').style.display = 'none';
        document.getElementById('progressFill').style.width = '0%';
    }

    function showStatus(type, msg) {
        const b = document.getElementById('statusMsg');
        document.getElementById('statusIcon').className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        document.getElementById('statusText').textContent = msg;
        b.className = 'pp-status show ' + type;
        setTimeout(() => b.classList.remove('show'), 6000);
    }

    function hideStatus() {
        document.getElementById('statusMsg').classList.remove('show');
    }

    function fmtSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(2) + ' MB';
    }

    // ── ADMIN ──
    function switchTab(tab, btn) {
        document.querySelectorAll('.adm-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.adm-tab-content').forEach(c => c.classList.remove('show'));
        btn.classList.add('active');
        document.getElementById('tab' + tab).classList.add('show');
        document.getElementById('admSearch').value = '';
    }

    function admFilter(q) {
        const lq = q.toLowerCase();
        document.querySelectorAll('.adm-person-card').forEach(c => {
            c.style.display = (!lq || (c.dataset.name || '').includes(lq)) ? '' : 'none';
        });
    }

    function triggerAdmUpload(type, id) {
        const card = document.getElementById('adm-av-' + type + '-' + id).closest('.adm-person-card');
        const inp = card.querySelector('.adm-file-input');
        if (inp) inp.click();
    }

    function admUploadPhoto(input, type, id) {
        const file = input.files[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            showAdmToast('error', 'Invalid file type.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showAdmToast('error', 'File too large. Max 2 MB.');
            return;
        }
        const fd = new FormData();
        fd.append('photo', file);
        fd.append('target_id', id);
        fd.append('target_type', type);
        fd.append('action', 'upload');
        fetch('uploads/admin_upload_photo.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const img = document.getElementById('adm-av-' + type + '-' + id),
                    fb = document.getElementById('adm-fb-' + type + '-' + id);
                img.src = d.photo_url;
                img.style.display = 'block';
                fb.style.display = 'none';
                const rm = document.getElementById('adm-rm-' + type + '-' + id);
                if (rm) rm.classList.remove('hidden');
                showAdmToast('success', d.message);
            } else showAdmToast('error', d.message || 'Upload failed.');
        }).catch(() => showAdmToast('error', 'Network error.'));
        input.value = '';
    }

    function admRemovePhoto(type, id) {
        if (!confirm('Remove this profile photo?')) return;
        const fd = new FormData();
        fd.append('target_id', id);
        fd.append('target_type', type);
        fd.append('action', 'remove');
        fetch('uploads/admin_upload_photo.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const img = document.getElementById('adm-av-' + type + '-' + id),
                    fb = document.getElementById('adm-fb-' + type + '-' + id);
                img.style.display = 'none';
                fb.style.display = 'flex';
                const rm = document.getElementById('adm-rm-' + type + '-' + id);
                if (rm) rm.classList.add('hidden');
                showAdmToast('success', d.message);
            } else showAdmToast('error', d.message || 'Could not remove.');
        }).catch(() => showAdmToast('error', 'Network error.'));
    }

    function showAdmToast(type, msg) {
        const t = document.getElementById('admToast');
        if (!t) return;
        document.getElementById('admToastIcon').className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        document.getElementById('admToastText').textContent = msg;
        t.className = 'adm-toast show ' + type;
        setTimeout(() => t.classList.remove('show'), 5000);
    }
</script>
<?php include "includes/footer.php"; ?>