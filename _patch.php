<?php
$content = file_get_contents('timetable.php');
$old = "\$dc = \$deptClass[\$dept] ?? 'ict';\n?>";
$new = "\$dc = \$deptClass[\$dept] ?? 'ict';\n\n    // Lecturers and students only see departments that have slots assigned to them\n    if (\$role !== 'admin' && \$totalSlots === 0) continue;\n?>";
if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents('timetable.php', $content);
    echo "REPLACED OK\n";
} else {
    echo "NOT FOUND\n";
    $idx = strpos($content, 'deptClass');
    echo substr($content, $idx, 150) . "\n";
}
