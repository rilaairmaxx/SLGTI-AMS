<?php
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

define('MAILER_FROM_EMAIL',  'studentrilaslgti2024@gmail.com');
define('MAILER_FROM_NAME',   'SLGTI Attendance System');
define('MAILER_REPLY_TO',    'sao@slgti.ac.lk');
define('MAILER_USE_SMTP',    true);
define('SMTP_HOST',          'smtp-relay.brevo.com');
define('SMTP_PORT',          587);
define('SMTP_USERNAME',      'a658b8001@smtp-brevo.com');
define('SMTP_PASSWORD',      'xsmtpsib-5cb979050a64fe24355cae263fcf889461c0ab693e92221ef00d4974e003aaca-92Eiqd8ASw79m3wi');
define('SMTP_ENCRYPTION',    'tls');
define('SMTP_DEBUG',         0);

class Mailer
{
   
    public static function send(array $options): array
    {
        // Validate required fields
        $to      = trim($options['to']      ?? '');
        $subject = trim($options['subject'] ?? '');
        $body    = trim($options['body']    ?? '');

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email address.'];
        }
        if (empty($subject)) {
            return ['success' => false, 'message' => 'Email subject is required.'];
        }
        if (empty($body)) {
            return ['success' => false, 'message' => 'Email body is required.'];
        }

        // Wrap body in SLGTI branded template
        $htmlBody  = self::wrapTemplate($subject, $body);
        $plainText = $options['plainText'] ?? self::htmlToText($body);
        $toName    = $options['toName']    ?? '';
        $replyTo   = $options['replyTo']   ?? MAILER_REPLY_TO;
        $cc        = $options['cc']        ?? [];
        $bcc       = $options['bcc']       ?? [];

        if (MAILER_USE_SMTP) {
            return self::sendViaSMTP($to, $toName, $subject, $htmlBody, $plainText, $replyTo, $cc, $bcc);
        } else {
            return self::sendViaMail($to, $subject, $htmlBody, $replyTo);
        }
    }

    public static function sendPasswordReset(string $toEmail, string $toName, string $newPassword): array
    {
        $body = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>Password Updated</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Hello <strong>{$toName}</strong>, your password for the SLGTI Attendance System has been reset.
            </p>
            <div style='background:#f0f4fa;border-radius:12px;padding:18px 22px;margin-bottom:20px;'>
                <div style='font-size:.82rem;color:#5a6e87;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;'>Your New Password</div>
                <div style='font-size:1.3rem;font-weight:800;color:#0a2d6e;font-family:monospace;letter-spacing:.1em;'>{$newPassword}</div>
            </div>
            <p style='color:#dc2626;font-size:.84rem;'>
                <strong>Important:</strong> Please log in and change your password immediately.
            </p>
            <a href='https://www.slgti.ac.lk/attendance/login.php'
               style='display:inline-block;background:linear-gradient(135deg,#0a2d6e,#1456c8);color:#fff;padding:12px 24px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:10px;'>
                Login to SLGTI AMS
            </a>
        ";

        return self::send([
            'to'      => $toEmail,
            'toName'  => $toName,
            'subject' => 'SLGTI AMS — Your Password Has Been Reset',
            'body'    => $body,
        ]);
    }

    public static function sendAttendanceWarning(
        string $toEmail,
        string $toName,
        string $courseName,
        float  $percentage
    ): array {
        $pct    = round($percentage, 1);
        $colour = $pct < 50 ? '#dc2626' : '#d97706';
        $body   = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>Attendance Warning</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Dear <strong>{$toName}</strong>, your attendance in the following course is below the required minimum of <strong>75%</strong>.
            </p>
            <div style='background:#fff1f1;border:1.5px solid #fecaca;border-radius:12px;padding:18px 22px;margin-bottom:20px;'>
                <div style='font-size:.82rem;color:#5a6e87;font-weight:600;text-transform:uppercase;margin-bottom:4px;'>Course</div>
                <div style='font-size:1rem;font-weight:800;color:#0a2d6e;margin-bottom:12px;'>{$courseName}</div>
                <div style='font-size:.82rem;color:#5a6e87;font-weight:600;text-transform:uppercase;margin-bottom:4px;'>Your Attendance</div>
                <div style='font-size:2rem;font-weight:800;color:{$colour};'>{$pct}%</div>
            </div>
            <p style='color:#374151;font-size:.88rem;'>
                Please attend all remaining sessions to avoid academic penalties. Contact your lecturer or the administration office if you need support.
            </p>
            <a href='https://www.slgti.ac.lk/attendance/login.php'
               style='display:inline-block;background:linear-gradient(135deg,#0a2d6e,#1456c8);color:#fff;padding:12px 24px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:10px;'>
                View My Attendance
            </a>
        ";

        return self::send([
            'to'      => $toEmail,
            'toName'  => $toName,
            'subject' => "SLGTI AMS — Attendance Warning: {$courseName}",
            'body'    => $body,
        ]);
    }


    public static function sendMonthlySummary(
        string $toEmail,
        string $toName,
        array  $summary,
        string $month
    ): array {
        $rows = '';
        foreach ($summary as $s) {
            $pct    = round($s['pct'] ?? 0, 1);
            $colour = $pct >= 75 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');
            $rows  .= "
                <tr>
                    <td style='padding:10px 14px;border-bottom:1px solid #e4eaf3;font-weight:600;color:#0d1b2e;'>{$s['course_name']}</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e4eaf3;text-align:center;color:#059669;font-weight:700;'>{$s['present']}</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e4eaf3;text-align:center;color:#dc2626;font-weight:700;'>{$s['absent']}</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e4eaf3;text-align:center;font-weight:700;'>{$s['total']}</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e4eaf3;text-align:center;'><strong style='color:{$colour};'>{$pct}%</strong></td>
                </tr>
            ";
        }

        $body = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>Monthly Attendance Summary</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Hi <strong>{$toName}</strong>, here is your attendance summary for <strong>{$month}</strong>.
            </p>
            <table style='width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;'>
                <thead>
                    <tr style='background:linear-gradient(135deg,#0a2d6e,#1456c8);color:#fff;'>
                        <th style='padding:11px 14px;text-align:left;font-size:.78rem;letter-spacing:.06em;'>Course</th>
                        <th style='padding:11px 14px;text-align:center;font-size:.78rem;'>Present</th>
                        <th style='padding:11px 14px;text-align:center;font-size:.78rem;'>Absent</th>
                        <th style='padding:11px 14px;text-align:center;font-size:.78rem;'>Total</th>
                        <th style='padding:11px 14px;text-align:center;font-size:.78rem;'>Rate</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <a href='https://www.slgti.ac.lk/attendance/login.php'
               style='display:inline-block;background:linear-gradient(135deg,#0a2d6e,#1456c8);color:#fff;padding:12px 24px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:20px;'>
                View Full Report
            </a>
        ";

        return self::send([
            'to'      => $toEmail,
            'toName'  => $toName,
            'subject' => "SLGTI AMS — Attendance Summary for {$month}",
            'body'    => $body,
        ]);
    }

    public static function sendWelcome(
        string $toEmail,
        string $toName,
        string $username,
        string $role
    ): array {
        $body = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>Welcome to SLGTI AMS</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Hello <strong>{$toName}</strong>, your account has been created successfully.
            </p>
            <div style='background:#f0f4fa;border-radius:12px;padding:18px 22px;margin-bottom:20px;'>
                <div style='margin-bottom:10px;'><span style='font-size:.78rem;color:#5a6e87;font-weight:600;text-transform:uppercase;'>Username</span><br><strong style='font-size:1rem;color:#0a2d6e;'>{$username}</strong></div>
                <div><span style='font-size:.78rem;color:#5a6e87;font-weight:600;text-transform:uppercase;'>Role</span><br><strong style='font-size:1rem;color:#0a2d6e;'>" . ucfirst($role) . "</strong></div>
            </div>
            <p style='color:#374151;font-size:.88rem;'>Use your username and assigned password to log in. You may reset your password at any time from the login page.</p>
            <a href='https://www.slgti.ac.lk/attendance/login.php'
               style='display:inline-block;background:linear-gradient(135deg,#0a2d6e,#1456c8);color:#fff;padding:12px 24px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:10px;'>
                Login Now
            </a>
        ";

        return self::send([
            'to'      => $toEmail,
            'toName'  => $toName,
            'subject' => 'Welcome to SLGTI Attendance Management System',
            'body'    => $body,
        ]);
    }

    private static function sendViaMail(
        string $to,
        string $subject,
        string $htmlBody,
        string $replyTo
    ): array {
        $boundary = md5(uniqid());
        $headers  = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . MAILER_FROM_NAME . ' <' . MAILER_FROM_EMAIL . '>',
            'Reply-To: ' . $replyTo,
            'X-Mailer: PHP/' . phpversion(),
        ]);

        $plain = self::htmlToText($htmlBody);

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plain . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--";

        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headers);

        if ($sent) {
            return ['success' => true,  'message' => "Email sent to {$to}."];
        }
        return ['success' => false, 'message' => 'mail() failed. Check your PHP mail configuration.'];
    }

    /** Send using PHPMailer + SMTP (requires composer package) */
    private static function sendViaSMTP(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainText,
        string $replyTo,
        array  $cc,
        array  $bcc
    ): array {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log('PHPMailer not found. Falling back to mail().');
            return self::sendViaMail($to, $subject, $htmlBody, $replyTo);
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = 0;

            $mail->isSMTP();
            $mail->Host        = SMTP_HOST;
            $mail->SMTPAuth    = true;
            $mail->Username    = SMTP_USERNAME;
            $mail->Password    = SMTP_PASSWORD;
            $mail->SMTPSecure  = SMTP_ENCRYPTION === 'ssl' 
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS 
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port        = SMTP_PORT;
            $mail->CharSet     = 'UTF-8';
            $mail->SMTPAutoTLS = false;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
            $mail->addReplyTo($replyTo);

            $mail->addAddress($to, $toName);
            foreach ($cc  as $ccAddr)  $mail->addCC($ccAddr);
            foreach ($bcc as $bccAddr) $mail->addBCC($bccAddr);

            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $plainText;

            $mail->send();
            return ['success' => true, 'message' => "Email sent to {$to}."];

        } catch (PHPMailer\PHPMailer\Exception $e) {
            error_log('PHPMailer Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log('General Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        } catch (Throwable $e) {
            error_log('Throwable: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────
    //  TEMPLATE WRAPPER
    // ─────────────────────────────────────────────────────

    /** Wrap the body content in a branded SLGTI HTML email template */
    private static function wrapTemplate(string $subject, string $body): string
    {
        $year = date('Y');
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1.0">
          <title>{$subject}</title>
        </head>
        <body style="margin:0;padding:0;background:#f0f4fa;font-family:'Segoe UI',Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4fa;padding:32px 0;">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 32px rgba(10,45,110,.12);">

                <!-- Header -->
                <tr>
                  <td style="background:linear-gradient(135deg,#0a2d6e 0%,#1456c8 60%,#1e90ff 100%);padding:28px 36px;">
                    <table width="100%"><tr>
                      <td>
                        <div style="font-size:1.2rem;font-weight:800;color:#fff;margin-bottom:3px;">SLGTI</div>
                        <div style="font-size:.72rem;color:rgba(255,255,255,.65);font-weight:500;text-transform:uppercase;letter-spacing:.06em;">Attendance Management System</div>
                      </td>
                      <td align="right">
                        <div style="font-size:.72rem;color:rgba(255,255,255,.55);">Sri Lanka German Technical Institute</div>
                        <div style="font-size:.7rem;color:rgba(255,255,255,.4);">Kilinochchi 44000</div>
                      </td>
                    </tr></table>
                  </td>
                </tr>

                <!-- Body -->
                <tr>
                  <td style="padding:32px 36px 24px;">
                    {$body}
                  </td>
                </tr>

                <!-- Footer -->
                <tr>
                  <td style="background:#f8fafd;border-top:1px solid #e4eaf3;padding:18px 36px;text-align:center;">
                    <p style="margin:0 0 4px;font-size:.72rem;color:#94a3b8;">This is an automated message from the SLGTI Attendance System. Please do not reply directly.</p>
                    <p style="margin:0;font-size:.72rem;color:#94a3b8;">
                      &copy; {$year} SLGTI &mdash; Kilinochchi &nbsp;|&nbsp;
                      <a href="tel:0214927799" style="color:#1456c8;text-decoration:none;">0214 927 799</a> &nbsp;|&nbsp;
                      <a href="mailto:sao@slgti.ac.lk" style="color:#1456c8;text-decoration:none;">sao@slgti.ac.lk</a>
                    </p>
                  </td>
                </tr>

              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

   
    private static function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}