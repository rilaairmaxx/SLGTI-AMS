<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include "config/db.php";

$role      = $_SESSION['role']      ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';

// ═══════════════════════════════════════════════════════════
//  SLGTI 2026 OFFICIAL CALENDAR DATA
//  Sources: SLGTI Calendar Image
// ═══════════════════════════════════════════════════════════

// Full Moon Holidays (red circle)
$fullmoon = [
    '2026-01-03','2026-02-01','2026-03-02','2026-04-01',
    '2026-05-01','2026-06-29','2026-07-29','2026-08-26',
    '2026-09-26','2026-10-25','2026-11-24','2026-12-23','2026-12-31',
];

// Public Holidays (yellow box)
$public_holidays = [
    '2026-01-15' => 'Tamil Thai Pongal Day',
    '2026-02-04' => 'National Independence Day',
    '2026-02-15' => 'Maha Sivaratri Day',
    '2026-03-21' => 'Id-Ul-Fitr (Ramazan Festival Day)',
    '2026-04-01' => 'Bak Full Moon Poya Day',
    '2026-04-03' => 'Good Friday',
    '2026-04-13' => 'Day prior to Sinhala & Tamil New Year Day',
    '2026-04-14' => 'Sinhala & Tamil New Year Day',
    '2026-05-01' => 'May Day (International Workers\' Day)',
    '2026-05-01' => 'Vesak Full Moon Poya Day',
    '2026-05-02' => 'Day following Vesak Full Moon Poya Day',
    '2026-05-28' => 'Id-Ul-Alha (Hadji Festival Day)',
    '2026-08-26' => 'Milad-Un-Nabi (Holy Prophet\'s Birthday)',
    '2026-11-08' => 'Deepavali',
    '2026-11-24' => 'Il Full Moon Poya Day',
    '2026-12-23' => 'Unduvap Full Moon Poya Day',
    '2026-12-25' => 'Christmas Day',
];

// Institute Programs / Events (pentagon shape)
$institute_events = [
    '2026-02-28' => 'Calling applications – NVQ Level 04 & 05 courses',
    '2026-03-23' => 'Ramzan Festival',
    '2026-04-04' => 'Entrance Examination',
    '2026-04-08' => 'Last Working Day Before Vacation',
    '2026-04-21' => 'First Working Day After Vacation',
    '2026-04-23' => 'Sinhala & Tamil New Year Festival',
    '2026-05-01' => 'Conducting Interviews',
    '2026-05-05' => 'Vesak Festival',
    '2026-05-14' => 'Blood Donation Day',
    '2026-05-30' => 'Adhi Poson Full Moon Poya Day',
    '2026-07-15' => 'Skill Day',
    '2026-07-18' => 'Institute Anniversary',
    '2026-07-30' => 'Intake Ceremony',
    '2026-08-07' => 'Last Working Day Before Vacation',
    '2026-08-17' => 'First Working Day After Vacation',
    '2026-09-09' => 'Awarding Ceremony for Diploma & Certificate Holders',
    '2026-09-22' => 'Saraswathi Pooja',
    '2026-12-16' => 'Year End Celebration',
    '2026-12-17' => 'Christmas Celebration',
    '2026-12-18' => 'Last Working Day Before Vacation',
    '2027-01-03' => 'First Working Day After Vacation',
];

// Current view month/year
$viewYear  = isset($_GET['year'])  ? (int)$_GET['year']  : 2026;
$viewMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($viewMonth < 1)  { $viewMonth = 12; $viewYear--; }
if ($viewMonth > 12) { $viewMonth =  1; $viewYear++; }

$prevMonth = $viewMonth - 1; $prevYear = $viewYear;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $viewMonth + 1; $nextYear = $viewYear;
if ($nextMonth > 12) { $nextMonth =  1; $nextYear++; }

$monthStart  = sprintf('%04d-%02d-01', $viewYear, $viewMonth);
$daysInMonth = (int)date('t', strtotime($monthStart));
$firstWeekday= (int)date('w', strtotime($monthStart));
$monthName   = date('F Y', strtotime($monthStart));

$MONTHS = ['','January','February','March','April','May','June',
           'July','August','September','October','November','December'];
?>
<?php include "includes/header.php"; ?>

<!-- ══ Calendar stylesheet (extracted from inline styles) ══ -->
<link rel="stylesheet" href="includes/css/calendar_style.css">

<!-- ══════════ PAGE HEADER ══════════ -->
<div class="cal-page-title">
    <i class="fas fa-calendar-alt" style="color:var(--cal-red);"></i>
    SLGTI Calendar 2026
</div>
<p class="cal-page-sub">
    Sri Lanka German Technical Institute — Official Academic Calendar
    &nbsp;|&nbsp; Kilinochchi
</p>

<!-- ══════════ LEGEND ══════════ -->
<div class="cal-legend-bar">
    <div class="leg-item">
        <div class="leg-circle fm">O</div>
        Full Moon Holidays
    </div>
    <div class="leg-item">
        <div class="leg-circle ph">□</div>
        Public Holidays
    </div>
    <div class="leg-item">
        <div class="leg-circle inst">⬠</div>
        Institute Programs
    </div>
    <div class="leg-item">
        <div class="leg-circle today">●</div>
        Today
    </div>
    <span class="ms-auto" style="font-size:.72rem;color:#94a3b8;">
        <i class="fas fa-hand-pointer me-1"></i>Click any marked date for details
    </span>
</div>

<!-- ══════════ YEAR NAV BANNER ══════════ -->
<div class="cal-year-banner">
    <div>
        <h4><i class="fas fa-university me-2"></i>Sri Lanka German Technical Institute</h4>
        <p>Academic Calendar 2026 &nbsp;—&nbsp; Kilinochchi, Sri Lanka</p>
    </div>
    <div style="text-align:right;">
        <div id="calLiveDate" style="color:#fff; font-size:.85rem; font-weight:700; letter-spacing:.02em;"></div>
        <div id="calLiveTime" style="color:rgba(255,255,255,.75); font-size:1.3rem; font-weight:800; font-variant-numeric:tabular-nums; letter-spacing:.05em;"></div>
    </div>
    <div class="cal-nav-btns">
        <a href="calendar.php?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>"
           class="cal-nav-btn" title="Previous"><i class="fas fa-chevron-left"></i></a>
        <a href="calendar.php?year=2026&month=<?php echo (int)date('m'); ?>"
           class="cal-nav-btn today">Today</a>
        <a href="calendar.php?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>"
           class="cal-nav-btn" title="Next"><i class="fas fa-chevron-right"></i></a>
    </div>
</div>

<script>
function updateCalClock() {
    const now = new Date();
    document.getElementById('calLiveDate').textContent =
        now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    document.getElementById('calLiveTime').textContent =
        now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}
updateCalClock();
setInterval(updateCalClock, 1000);
</script>

<!-- ══════════ TWO COLUMN: CALENDAR + SIDEBAR ══════════ -->
<div class="row g-4">

    <!-- ── Calendar grid ── -->
    <div class="col-lg-8">
        <div class="cal-month-card">
            <div class="cal-month-head">
                <?php echo strtoupper($MONTHS[$viewMonth] . ' ' . $viewYear); ?>
            </div>
            <div class="cal-dow-row">
                <span>SUN</span><span>MON</span><span>TUE</span>
                <span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
            </div>
            <div class="cal-grid">

                <?php
                // Leading empty cells
                for ($e = 0; $e < $firstWeekday; $e++):
                ?>
                    <div class="cal-cell empty"><div class="cal-day-num"></div></div>
                <?php endfor;

                // Day cells
                for ($d = 1; $d <= $daysInMonth; $d++):
                    $key     = sprintf('%04d-%02d-%02d', $viewYear, $viewMonth, $d);
                    $isToday = ($key === date('Y-m-d'));
                    $isFM    = in_array($key, $fullmoon);
                    $isPH    = isset($public_holidays[$key]);
                    $isInst  = isset($institute_events[$key]);

                    $classes = 'cal-cell';
                    if ($isToday) $classes .= ' is-today';
                    if ($isFM)    $classes .= ' fullmoon';
                    if ($isPH)    $classes .= ' pubhol';
                    if ($isInst)  $classes .= ' instevent';
                    if ($isFM || $isPH || $isInst) $classes .= ' has-ev';

                    // Build modal data
                    $modalEvs = [];
                    if ($isFM)   $modalEvs[] = ['type'=>'fm',   'icon'=>'fa-moon',      'title'=>'Full Moon Holiday',     'sub'=>'Poya Day'];
                    if ($isPH)   $modalEvs[] = ['type'=>'ph',   'icon'=>'fa-star',       'title'=>$public_holidays[$key],  'sub'=>'Public Holiday'];
                    if ($isInst) $modalEvs[] = ['type'=>'inst', 'icon'=>'fa-university', 'title'=>$institute_events[$key], 'sub'=>'Institute Program'];
                    $modalJson = htmlspecialchars(json_encode($modalEvs), ENT_QUOTES);
                ?>
                    <div class="<?php echo $classes; ?>"
                         <?php if ($isFM || $isPH || $isInst): ?>
                             onclick="showModal('<?php echo $key; ?>', <?php echo $modalJson; ?>)"
                         <?php endif; ?>>

                        <div class="cal-day-num"><?php echo $d; ?></div>

                        <?php
                        // Event dots
                        if ($isFM || $isPH || $isInst):
                        ?>
                        <div class="cal-ev-dots">
                            <?php if ($isFM):   ?><span class="cal-ev-dot dot-fm"></span><?php endif; ?>
                            <?php if ($isPH):   ?><span class="cal-ev-dot dot-ph"></span><?php endif; ?>
                            <?php if ($isInst): ?><span class="cal-ev-dot dot-inst"></span><?php endif; ?>
                        </div>
                        <?php endif;

                        // Short label — show first event name truncated
                        $firstLabel = '';
                        if ($isInst)     $firstLabel = $institute_events[$key];
                        elseif ($isPH)   $firstLabel = $public_holidays[$key];
                        elseif ($isFM)   $firstLabel = 'Full Moon';
                        if ($firstLabel):
                        ?>
                        <div class="cal-ev-label"><?php echo htmlspecialchars($firstLabel); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>

            </div><!-- /cal-grid -->
        </div><!-- /cal-month-card -->

        <!-- Quick jump: all months -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <small class="text-muted fw-bold me-1">Jump to:</small>
                    <?php foreach ($MONTHS as $mn => $mname):
                        if ($mn === 0) continue;
                    ?>
                        <a href="calendar.php?year=2026&month=<?php echo $mn; ?>"
                           class="btn btn-sm <?php echo ($mn == $viewMonth && $viewYear == 2026) ? 'btn-danger' : 'btn-outline-secondary'; ?> py-0 px-2"
                           style="font-size:.72rem;">
                            <?php echo substr($mname,0,3); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ── Sidebar: events for this month ── -->
    <div class="col-lg-4">
        <div class="events-panel">
            <div class="events-panel-head">
                <i class="fas fa-list-alt"></i>
                <?php echo $MONTHS[$viewMonth]; ?> <?php echo $viewYear; ?> Events
            </div>
            <div class="events-panel-body">
                <?php
                $monthHasEvents = false;
                for ($d = 1; $d <= $daysInMonth; $d++):
                    $key    = sprintf('%04d-%02d-%02d', $viewYear, $viewMonth, $d);
                    $isFM   = in_array($key, $fullmoon);
                    $isPH   = isset($public_holidays[$key]);
                    $isInst = isset($institute_events[$key]);
                    if (!$isFM && !$isPH && !$isInst) continue;
                    $monthHasEvents = true;
                    $dispDate = date('D, d M', strtotime($key));
                ?>
                    <?php if ($isFM): ?>
                    <div class="ev-row">
                        <div class="ev-badge fm"><i class="fas fa-moon"></i></div>
                        <div>
                            <div class="ev-date"><i class="fas fa-calendar me-1"></i><?php echo $dispDate; ?></div>
                            <div class="ev-name">Full Moon Holiday (Poya Day)</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($isPH): ?>
                    <div class="ev-row">
                        <div class="ev-badge ph"><i class="fas fa-star"></i></div>
                        <div>
                            <div class="ev-date"><i class="fas fa-calendar me-1"></i><?php echo $dispDate; ?></div>
                            <div class="ev-name"><?php echo htmlspecialchars($public_holidays[$key]); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($isInst): ?>
                    <div class="ev-row">
                        <div class="ev-badge inst"><i class="fas fa-university"></i></div>
                        <div>
                            <div class="ev-date"><i class="fas fa-calendar me-1"></i><?php echo $dispDate; ?></div>
                            <div class="ev-name"><?php echo htmlspecialchars($institute_events[$key]); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endfor;
                if (!$monthHasEvents): ?>
                    <div style="text-align:center;padding:28px 16px;color:#94a3b8;">
                        <i class="fas fa-calendar-times" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
                        No special events in <?php echo $MONTHS[$viewMonth]; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contact info card -->
        <div class="card border-0 shadow-sm mt-3" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#0a2d6e,#1456c8);padding:12px 16px;">
                <p class="mb-0 text-white fw-bold" style="font-size:.85rem;">
                    <i class="fas fa-university me-2"></i>SLGTI Contact
                </p>
            </div>
            <div class="card-body py-3 px-3" style="font-size:.78rem;color:#5a6e87;line-height:1.8;">
                <div><i class="fas fa-map-marker-alt me-2 text-danger"></i>Ariviyal Nagar, Kilinochchi 44000</div>
                <div><i class="fas fa-phone me-2 text-primary"></i>0214 927 799 / 070 306 0138</div>
                <div><i class="fas fa-envelope me-2 text-warning"></i>sao@slgti.ac.lk</div>
                <div><i class="fas fa-globe me-2 text-success"></i>www.slgti.ac.lk</div>
            </div>
        </div>

    </div><!-- /col-lg-4 -->
</div><!-- /row -->


<!-- ══════════ DAY DETAIL MODAL ══════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModalOuter(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-date-head" id="modalDateHead"></div>
        <div id="modalBody"></div>
    </div>
</div>


<!-- ══════════ JAVASCRIPT ══════════ -->
<script>
    function showModal(dateStr, events) {
        const overlay = document.getElementById('modalOverlay');
        const head    = document.getElementById('modalDateHead');
        const body    = document.getElementById('modalBody');

        const d = new Date(dateStr + 'T00:00:00');
        head.innerHTML = '<i class="fas fa-calendar-day me-2" style="color:#1456c8;"></i>'
            + d.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'});

        const iconMap = { fm:'fa-moon', ph:'fa-star', inst:'fa-university' };
        const typeMap = { fm:'Full Moon Holiday', ph:'Public Holiday', inst:'Institute Program' };

        body.innerHTML = events.map(ev => `
            <div class="modal-ev-item">
                <div class="modal-ev-icon ${ev.type}">
                    <i class="fas ${ev.icon || iconMap[ev.type]}"></i>
                </div>
                <div>
                    <div class="modal-ev-title">${ev.title}</div>
                    <div class="modal-ev-type">${ev.sub || typeMap[ev.type] || ''}</div>
                </div>
            </div>
        `).join('');

        overlay.classList.add('show');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('show');
    }
    function closeModalOuter(e) {
        if (e.target === document.getElementById('modalOverlay')) closeModal();
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include "includes/footer.php"; ?>