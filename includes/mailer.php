<?php
// includes/mailer.php
// Minimal SMTP mailer (STARTTLS / SSL + AUTH LOGIN) driven by the 'mail' section of app_config.
// When SMTP credentials are still the placeholders, mail is written to uploads/mail_outbox/
// instead, mirroring the OTP simulator log used in development.

function mail_config()
{
    $config = require __DIR__ . '/../config/app_config.php';
    $mail = $config['mail'] ?? [];
    $local_path = __DIR__ . '/../config/app_config.local.php';
    if (file_exists($local_path)) {
        $local = require $local_path;
        if (isset($local['mail'])) {
            $mail = array_merge($mail, $local['mail']);
        }
    }
    return $mail;
}

function mail_is_configured($mail)
{
    $user = $mail['username'] ?? '';
    $pass = $mail['password'] ?? '';
    return !empty($mail['host']) && $user !== '' && $pass !== ''
        && $user !== 'your_email@gmail.com' && $pass !== 'your_app_password';
}

/**
 * Send an HTML email.
 *
 * @return array ['ok' => bool, 'simulated' => bool, 'error' => string|null]
 */
function send_email($to, $to_name, $subject, $html, $text = null)
{
    $mail = mail_config();
    $text = $text ?? trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)[^>]*>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

    if (!mail_is_configured($mail)) {
        $dir = __DIR__ . '/../uploads/mail_outbox';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]+/i', '_', $to) . '.html';
        @file_put_contents($file, "<!-- To: $to_name <$to> | Subject: $subject -->\n" . $html);
        @file_put_contents($dir . '/outbox.log', "[" . date('Y-m-d H:i:s') . "] SIMULATED mail to $to: $subject (" . basename($file) . ")\n", FILE_APPEND);
        return ['ok' => true, 'simulated' => true, 'error' => null];
    }

    try {
        smtp_deliver($mail, $to, $to_name, $subject, $html, $text);
        return ['ok' => true, 'simulated' => false, 'error' => null];
    } catch (Exception $e) {
        dev_log("SMTP delivery to $to failed: " . $e->getMessage(), 'error');
        return ['ok' => false, 'simulated' => false, 'error' => $e->getMessage()];
    }
}

function smtp_deliver($mail, $to, $to_name, $subject, $html, $text)
{
    $host = $mail['host'];
    $port = (int) ($mail['port'] ?? 587);
    $enc = strtolower($mail['encryption'] ?? 'tls');
    $from = $mail['from_address'] ?: $mail['username'];
    $from_name = $mail['from_name'] ?? '';

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $sock = @stream_socket_client($remote, $errno, $errstr, 20);
    if (!$sock) {
        throw new Exception("Cannot connect to $host:$port ($errstr)");
    }
    stream_set_timeout($sock, 20);

    $read = function () use ($sock) {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function ($line, $expect) use ($sock, $read) {
        if ($line !== null) fwrite($sock, $line . "\r\n");
        $resp = $read();
        if ((int) substr($resp, 0, 3) !== $expect) {
            throw new Exception("SMTP error after '" . strtok((string) $line, ' ') . "': " . trim($resp));
        }
        return $resp;
    };

    try {
        $ehlo = 'EHLO ' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost');
        $cmd(null, 220);
        $cmd($ehlo, 250);
        if ($enc === 'tls') {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("STARTTLS negotiation failed");
            }
            $cmd($ehlo, 250);
        }
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode($mail['username']), 334);
        $cmd(base64_encode($mail['password']), 235);
        $cmd("MAIL FROM:<$from>", 250);
        $cmd("RCPT TO:<$to>", 250);
        $cmd('DATA', 354);

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $enc_name = fn($n) => $n === '' ? '' : '=?UTF-8?B?' . base64_encode($n) . '?= ';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $enc_name($from_name) . "<$from>",
            'To: ' . $enc_name($to_name) . "<$to>",
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--";

        // Base64 bodies never start a line with '.', so no dot-stuffing is needed.
        $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", 250);
        fwrite($sock, "QUIT\r\n"); // message already accepted; QUIT reply doesn't matter
    } finally {
        fclose($sock);
    }
}
