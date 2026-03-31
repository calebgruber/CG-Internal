<?php
/**
 * shared/email.php
 * Email and SMS notification helpers.
 *
 * Uses PHP mail() or SMTP (via sockets) depending on SMTP_HOST config.
 * For production: swap out with PHPMailer/Symfony Mailer as needed.
 */

/**
 * Send an email.
 *
 * @param string|array $to       Recipient email or ['email', 'name']
 * @param string       $subject
 * @param string       $html     HTML body
 * @param string       $text     Plain-text fallback (auto-generated if empty)
 * @return bool
 */
function send_email($to, string $subject, string $html, string $text = ''): bool {
    if (is_array($to)) {
        $to_addr = $to[0];
        $to_name = $to[1] ?? '';
        $to_header = $to_name ? "{$to_name} <{$to_addr}>" : $to_addr;
    } else {
        $to_addr   = $to;
        $to_header = $to;
    }

    if ($text === '') {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));
    }

    $boundary = md5(uniqid('', true));
    $from_name = MAIL_FROM_NAME;
    $from_addr = MAIL_FROM;

    $headers  = "From: {$from_name} <{$from_addr}>\r\n";
    $headers .= "Reply-To: {$from_addr}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: CG-Internal/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode($text) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode(email_html_wrapper($subject, $html)) . "\r\n";
    $body .= "--{$boundary}--";

    if (SMTP_HOST !== '') {
        return smtp_send($to_addr, $from_addr, $subject, $headers, $body);
    }
    return mail($to_header, $subject, $body, $headers);
}

/**
 * Wrap email content in a branded HTML template.
 */
function email_html_wrapper(string $subject, string $content): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:Inter,Arial,sans-serif;background:#f1f5f9;margin:0;padding:2rem}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0}
.header{background:#0f172a;padding:1.5rem;text-align:center}
.header h1{color:#f8fafc;font-size:1.125rem;margin:0}
.header span{color:#3b82f6;font-size:1.5rem}
.body{padding:1.5rem;color:#0f172a;line-height:1.6;font-size:0.9375rem}
.footer{padding:1rem 1.5rem;border-top:1px solid #e2e8f0;font-size:0.75rem;color:#64748b;text-align:center}
.btn{display:inline-block;background:#3b82f6;color:#fff;padding:.625rem 1.25rem;border-radius:8px;text-decoration:none;font-weight:600}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>' . htmlspecialchars(APP_NAME) . '</h1></div>
  <div class="body">' . $content . '</div>
  <div class="footer">You received this because you have an account on ' .
    htmlspecialchars(APP_URL) . '.</div>
</div></body></html>';
}

/**
 * Minimal SMTP sender (no TLS in this implementation – configure your server
 * to relay via localhost, or replace with PHPMailer for full SMTP+TLS support).
 */
function smtp_send(string $to, string $from, string $subject, string $headers, string $body): bool {
    // This is a placeholder that falls back to mail().
    // For full SMTP: install PHPMailer via Composer and configure here.
    return mail($to, $subject, $body, $headers);
}

/**
 * Send an SMS via Twilio REST API.
 *
 * @param string $to      E.164 phone number e.g. +12025551234
 * @param string $message
 * @return bool
 */
function send_sms(string $to, string $message): bool {
    if (TWILIO_SID === '' || TWILIO_TOKEN === '' || TWILIO_FROM === '') {
        error_log('CG-Internal: Twilio not configured. SMS not sent to ' . $to);
        return false;
    }

    $url  = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";
    $data = http_build_query([
        'From' => TWILIO_FROM,
        'To'   => $to,
        'Body' => $message,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode(TWILIO_SID . ':' . TWILIO_TOKEN),
                'Content-Length: ' . strlen($data),
            ],
            'content' => $data,
            'timeout' => 10,
        ],
    ]);

    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        error_log('CG-Internal: Twilio API call failed for ' . $to);
        return false;
    }
    $json = json_decode($result, true);
    return isset($json['sid']);
}

/**
 * Notify the user (email + optional SMS) about an upcoming assignment/task.
 *
 * @param array  $user          User row from DB
 * @param array  $item          Assignment or task row
 * @param string $type          'assignment' | 'task'
 */
function send_due_notification(array $user, array $item, string $type = 'assignment'): bool {
    $label    = $type === 'assignment' ? 'Assignment' : 'Task';
    $title    = $item['title'] ?? 'Untitled';
    $due_ts   = isset($item['due_date']) ? strtotime($item['due_date']) : false;
    $due      = $due_ts ? date('D, M j', $due_ts) . ' at ' . date('g:i A', $due_ts) : 'TBD';
    $subject  = "[CG Internal] {$label} Due Reminder: {$title}";

    $html = "<h2>Upcoming {$label} Reminder</h2>
<p>Hi {$user['display_name']},</p>
<p>This is a reminder that your {$type} <strong>" . htmlspecialchars($title) . "</strong>
is due on <strong>{$due}</strong>.</p>
<p><a class='btn' href='" . APP_URL . "/edu/assignments.php'>View Assignments</a></p>
<p>Stay on track! – CG Internal EDU Hub</p>";

    $email_ok = send_email($user['email'], $subject, $html);

    $sms_ok = true;
    if (!empty($user['phone'])) {
        $sms_msg = "CG Internal: {$label} reminder – \"{$title}\" due {$due}. Log in at " . APP_URL;
        $sms_ok  = send_sms($user['phone'], $sms_msg);
    }

    return $email_ok;
}
