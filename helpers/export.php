<?php

class Exporter
{

    public static function csv(string $filename, array $headings, array $rows): void
    {
        self::csvHeaders($filename);

        $out = fopen('php://output', 'w');
        fputcsv($out, $headings);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit();
    }

    public static function excel(
        string $filename,
        array  $headings,
        array  $rows,
        array  $metaRows = []
    ): void {
        self::csvHeaders($filename);
        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        $out = fopen('php://output', 'w');

        // Metadata block
        foreach ($metaRows as $meta) {
            fputcsv($out, $meta);
        }
        if (!empty($metaRows)) {
            fputcsv($out, []); // blank separator
        }

        fputcsv($out, $headings);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }

        fclose($out);
        exit();
    }

    public static function attendancePdf(
        mysqli $conn,
        int    $courseId,
        string $dateFrom,
        string $dateTo
    ): void {
        $url = self::exportBase() . 'export_pdf.php'
            . '?course_id=' . (int)$courseId
            . '&date_from=' . urlencode($dateFrom)
            . '&date_to='   . urlencode($dateTo);

        header('Location: ' . $url);
        exit();
    }

    public static function attendanceCsv(
        mysqli $conn,
        int    $courseId,
        string $dateFrom,
        string $dateTo
    ): void {
        $url = self::exportBase() . 'export_excel.php'
            . '?course_id=' . (int)$courseId
            . '&date_from=' . urlencode($dateFrom)
            . '&date_to='   . urlencode($dateTo);

        header('Location: ' . $url);
        exit();
    }

    public static function pdf(string $title, string $tableHtml, array $meta = []): void
    {
        $year     = date('Y');
        $now      = date('d M Y, h:i A');
        $metaHtml = '';

        foreach ($meta as $label => $value) {
            $metaHtml .= '
            <div class="info-item">
                <span class="info-label">' . htmlspecialchars($label) . '</span>
                <span class="info-value">' . htmlspecialchars($value) . '</span>
            </div>';
        }

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <title>{$title}</title>
          <style>
            *{box-sizing:border-box;margin:0;padding:0;}

            body{
                font-family:'Segoe UI',Arial,sans-serif;
                font-size:13px;color:#1e293b;background:#fff;
            }

            .report-header{
                background:linear-gradient(135deg,#0a2d6e,#1456c8);
                color:#fff;padding:24px 32px;
                display:flex;align-items:center;
                justify-content:space-between;gap:16px;
            }

            .report-logo{
                width:52px;
                height:52px;
                border-radius:12px;
                background:rgba(255,255,255,.15);
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.2rem;
                font-weight:800;
                color:#fff;
                border:2px solid rgba(255,255,255,.25);
                margin-right:14px;
            }

            .report-logo-wrap{
                display:flex;
                align-items:center;
            }

            .report-org h1{
                font-size:1rem;
                font-weight:800;
                margin-bottom:2px;
            }

            .report-org p{
                font-size:.7rem;
                color:rgba(255,255,255,.65);
            }

            .report-meta{
                text-align:right;
                font-size:.7rem;color:rgba(255,255,255,.65);
                line-height:1.8;
            }

            .report-meta strong{
                color:#fff;
            }

            .report-title-strip{
                background:#f0f4fa;
                border-bottom:2px solid #e4eaf3;
                padding:13px 32px;
            }
                
            .report-title-strip h2{
                font-size:.95rem;
                font-weight:800;
                color:#0a2d6e;
            }

            .course-info{
                display:flex;
                gap:20px;
                padding:13px 32px;
                border-bottom:1px solid #e4eaf3;
                flex-wrap:wrap;
            }

            .info-item{
                display:flex;
                flex-direction:column;
                gap:2px;
            }

            .info-label{
                font-size:.63rem;
                font-weight:700;
                text-transform:uppercase;
                letter-spacing:.08em;
                color:#94a3b8;
            }

            .info-value{
                font-size:.84rem;
                font-weight:700;
                color:#0d1b2e;
            }

            .report-body{
                padding:20px 32px 32px;
            }
            
            .report-body table{
                width:100%;border-collapse:collapse;
            }

            .report-body thead tr{
                background:#0a2d6e;
                color:#fff;
            }

            .report-body thead th{
                padding:9px 13px;
                font-size:.7rem;
                font-weight:700;
                text-transform:uppercase;
                letter-spacing:.06em;text-align:left;
            }

            .report-body tbody tr:nth-child(even){
                background:#f8fafd;
            }

            .report-body tbody td{
                padding:9px 13px;
                font-size:.82rem;
                color:#374151;
                border-bottom:1px solid #e4eaf3;
            }
            .report-footer{
                border-top:2px solid #e4eaf3;
                padding:13px 32px;
                display:flex;
                justify-content:space-between;
                font-size:.7rem;
                color:#94a3b8;
            }
            .action-bar{
                padding:12px 32px;
                background:#f0f4fa;
                border-bottom:1px solid #e4eaf3;
                display:flex;
                gap:10px;
            }
            .btn-print{
                display:inline-flex;
                align-items:center;
                gap:7px;
                background:linear-gradient(135deg,#0a2d6e,#1456c8);
                color:#fff;
                border:none;
                border-radius:9px;
                padding:8px 18px;
                font-size:.84rem;
                font-weight:700;
                cursor:pointer;
            }
            @media print{
                .action-bar{
                    display:none!important;
                }
                @page{
                    margin:1cm;
                }
            }
          </style>
        </head>
        <body>
          <div class="action-bar">
            <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
          </div>
          <div class="report-header">
            <div class="report-logo-wrap">
              <div class="report-logo">SL</div>
              <div class="report-org">
                <h1>Sri Lanka German Technical Institute</h1>
                <p>SLGTI — Ariviyal Nagar, Kilinochchi 44000</p>
              </div>
            </div>
            <div class="report-meta">
              <div><strong>Report Generated</strong></div>
              <div>{$now}</div>
            </div>
          </div>
          <div class="report-title-strip"><h2>&#128202; {$title}</h2></div>
          <div class="course-info">{$metaHtml}</div>
          <div class="report-body">{$tableHtml}</div>
          <div class="report-footer">
            <span>SLGTI Attendance Management System &mdash; Confidential</span>
            <span>Printed: {$now}</span>
          </div>
        </body>
        </html>
        HTML;
        exit();
    }

    private static function csvHeaders(string $filename): void
    {
        // Sanitise filename
        $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private static function exportBase(): string
    {
        // Build an absolute URL to /exports/
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        // Walk up to the project root (one level above any subfolder)
        $root   = rtrim(dirname($script), '/\\');
        // If called from a subfolder like /exports/, go up one more
        if (basename(dirname($script)) === 'exports') {
            $root = dirname($root);
        }
        return $scheme . '://' . $host . $root . '/exports/';
    }
}
