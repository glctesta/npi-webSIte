<?php
/**
 * GTMC-NPI Mailer — Authenticated SMTP
 */
/**
 * Dominio di posta derivato dall'indirizzo From configurato (MAIL_FROM).
 * Usato per EHLO e Message-ID: devono coincidere col dominio del mittente,
 * altrimenti SPF/DMARC del dominio mittente falliscono e le email finiscono
 * nello spam. Sostituisce gli hostname fissi gtmc-npi.eu.
 */
function mail_domain() {
    if (defined('MAIL_FROM') && strpos(MAIL_FROM, '@') !== false) {
        return strtolower(substr(MAIL_FROM, strrpos(MAIL_FROM, '@') + 1));
    }
    $host = parse_url(SITE_URL, PHP_URL_HOST);
    return $host ? preg_replace('/^www\./', '', $host) : 'localhost';
}

class SmtpMailer {
    private $socket;
    private $log = [];
    
    private function read() {
        $data = '';
        while ($line = fgets($this->socket, 515)) {
            $data .= $line;
            // Last line of multi-line response has space at position 3
            if (isset($line[3]) && $line[3] === ' ') break;
            // Single line response
            if (!isset($line[3])) break;
        }
        $this->log[] = "< " . trim($data);
        return $data;
    }
    
    private function send($cmd) {
        $this->log[] = "> " . trim($cmd);
        fputs($this->socket, $cmd . "\r\n");
        return $this->read();
    }
    
    public function sendMail($to, $subject, $htmlBody, $extraHeaders = []) {
        $this->log = [];
        
        // Try port 465 SSL
        $this->socket = @fsockopen('ssl://' . SMTP_HOST, 465, $errno, $errstr, 15);
        if (!$this->socket) {
            $this->log[] = "SSL:465 failed ($errstr), trying 587...";
            // Try port 587 with STARTTLS
            $this->socket = @fsockopen(SMTP_HOST, 587, $errno, $errstr, 15);
            if (!$this->socket) {
                $this->log[] = "All connections failed: $errstr";
                return false;
            }
            $this->read(); // greeting
            $this->send("EHLO " . mail_domain());
            $resp = $this->send("STARTTLS");
            if (strpos($resp, '220') !== false) {
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $this->send("EHLO " . mail_domain()); // EHLO again after TLS
            }
        } else {
            $this->read(); // greeting
            $this->send("EHLO " . mail_domain());
        }
        
        // AUTH LOGIN
        $resp = $this->send("AUTH LOGIN");
        if (strpos($resp, '334') === false) {
            // Try AUTH PLAIN
            $auth_string = base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS);
            $resp = $this->send("AUTH PLAIN " . $auth_string);
            if (strpos($resp, '235') === false) {
                $this->log[] = "AUTH PLAIN also failed";
                fclose($this->socket);
                return false;
            }
        } else {
            $this->send(base64_encode(SMTP_USER));
            $resp = $this->send(base64_encode(SMTP_PASS));
            if (strpos($resp, '235') === false) {
                $this->log[] = "AUTH LOGIN failed";
                fclose($this->socket);
                return false;
            }
        }
        
        // MAIL FROM
        $this->send("MAIL FROM: <" . MAIL_FROM . ">");
        
        // RCPT TO
        $resp = $this->send("RCPT TO: <$to>");
        if (strpos($resp, '250') === false) {
            $this->log[] = "RCPT rejected";
            fclose($this->socket);
            return false;
        }
        
        // DATA
        $this->send("DATA");
        
        // Build message
        $msg = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $msg .= "To: $to\r\n";
        // RFC 2047 encode subject if it contains non-ASCII characters
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        } else {
            $msg .= "Subject: $subject\r\n";
        }
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: <" . uniqid('gtmc_') . "@" . mail_domain() . ">\r\n";
        foreach ($extraHeaders as $hName => $hValue) {
            $hName = preg_replace('/[^A-Za-z0-9-]/', '', $hName);
            $hValue = str_replace(["\r", "\n"], '', $hValue);
            if ($hName !== '') $msg .= "$hName: $hValue\r\n";
        }
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
        
        $resp = $this->send($msg . "\r\n.");
        
        $this->send("QUIT");
        fclose($this->socket);
        
        return strpos($resp, '250') !== false;
    }
    
    public function sendMailWithAttachment($to, $subject, $htmlBody, $attachmentContent, $attachmentFilename, $attachmentMimeType = 'application/pdf') {
        $this->log = [];
        
        // Try port 465 SSL
        $this->socket = @fsockopen('ssl://' . SMTP_HOST, 465, $errno, $errstr, 15);
        if (!$this->socket) {
            $this->log[] = "SSL:465 failed ($errstr), trying 587...";
            // Try port 587 with STARTTLS
            $this->socket = @fsockopen(SMTP_HOST, 587, $errno, $errstr, 15);
            if (!$this->socket) {
                $this->log[] = "All connections failed: $errstr";
                return false;
            }
            $this->read(); // greeting
            $this->send("EHLO " . mail_domain());
            $resp = $this->send("STARTTLS");
            if (strpos($resp, '220') !== false) {
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $this->send("EHLO " . mail_domain()); // EHLO again after TLS
            }
        } else {
            $this->read(); // greeting
            $this->send("EHLO " . mail_domain());
        }
        
        // AUTH LOGIN
        $resp = $this->send("AUTH LOGIN");
        if (strpos($resp, '334') === false) {
            // Try AUTH PLAIN
            $auth_string = base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS);
            $resp = $this->send("AUTH PLAIN " . $auth_string);
            if (strpos($resp, '235') === false) {
                $this->log[] = "AUTH PLAIN also failed";
                fclose($this->socket);
                return false;
            }
        } else {
            $this->send(base64_encode(SMTP_USER));
            $resp = $this->send(base64_encode(SMTP_PASS));
            if (strpos($resp, '235') === false) {
                $this->log[] = "AUTH LOGIN failed";
                fclose($this->socket);
                return false;
            }
        }
        
        // MAIL FROM
        $this->send("MAIL FROM: <" . MAIL_FROM . ">");
        
        // RCPT TO
        $resp = $this->send("RCPT TO: <$to>");
        if (strpos($resp, '250') === false) {
            $this->log[] = "RCPT rejected";
            fclose($this->socket);
            return false;
        }
        
        // DATA
        $this->send("DATA");
        
        // Build message
        $boundary = '----=_Part_' . uniqid();
        $msg = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $msg .= "To: $to\r\n";
        // RFC 2047 encode subject if it contains non-ASCII characters
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        } else {
            $msg .= "Subject: $subject\r\n";
        }
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: <" . uniqid('gtmc_') . "@" . mail_domain() . ">\r\n";
        $msg .= "\r\n";
        
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
        
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: $attachmentMimeType; name=\"$attachmentFilename\"\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"$attachmentFilename\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($attachmentContent), 76, "\r\n");
        
        $msg .= "--$boundary--\r\n";
        
        $resp = $this->send($msg . "\r\n.");
        
        $this->send("QUIT");
        fclose($this->socket);
        
        return strpos($resp, '250') !== false;
    }
    
    public function getLog() {
        return implode("\n", $this->log);
    }
}

function send_verify_email($to_email, $name, $verify_url) {
    $subject = SITE_NAME . ' - ' . t('email.verify_subject');
    
    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
    $body .= '<div style="max-width:500px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
    $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">' . SITE_NAME . '</h2></div>';
    $body .= '<p style="font-size:1.1em;">' . t('email.greeting') . ' <strong>' . htmlspecialchars($name) . '</strong>,</p>';
    $body .= '<p style="line-height:1.6;">' . t('email.verify_msg') . '</p>';
    $body .= '<div style="text-align:center;margin:35px 0;">';
    $body .= '<a href="' . $verify_url . '" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">' . t('email.verify_btn') . '</a>';
    $body .= '</div>';
    $body .= '<p style="color:#888;font-size:0.85em;text-align:center;">' . t('email.expiry') . '</p>';
    $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
    $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; 2026 ' . mail_domain() . ' | help@' . mail_domain() . '</p>';
    $body .= '</div></body></html>';
    
    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($to_email, $subject, $body);
    
    if (!$result) {
        error_log("SMTP failed. Log:\n" . $mailer->getLog());
    }

    return $result;
}

function send_demo_verify_email($to_email, $name, $verify_url) {
    $subject = SITE_NAME . ' - ' . t('email.demo_verify_subject');

    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
    $body .= '<div style="max-width:500px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
    $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">' . SITE_NAME . '</h2></div>';
    $body .= '<p style="font-size:1.1em;">' . t('email.greeting') . ' <strong>' . htmlspecialchars($name) . '</strong>,</p>';
    $body .= '<p style="line-height:1.6;">' . t('email.demo_verify_msg') . '</p>';
    $body .= '<div style="text-align:center;margin:35px 0;">';
    $body .= '<a href="' . $verify_url . '" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">' . t('email.demo_verify_btn') . '</a>';
    $body .= '</div>';
    $body .= '<p style="color:#888;font-size:0.85em;text-align:center;">' . t('email.expiry') . '</p>';
    $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
    $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; 2026 ' . mail_domain() . ' | help@' . mail_domain() . '</p>';
    $body .= '</div></body></html>';

    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($to_email, $subject, $body);

    if (!$result) {
        error_log("SMTP failed. Log:\n" . $mailer->getLog());
    }

    return $result;
}

function send_reset_email($to_email, $name, $reset_url) {
    $subject = SITE_NAME . ' - ' . t('email.reset_subject');
    
    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
    $body .= '<div style="max-width:500px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
    $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">' . SITE_NAME . '</h2></div>';
    $body .= '<p style="font-size:1.1em;">' . t('email.greeting') . ' <strong>' . htmlspecialchars($name) . '</strong>,</p>';
    $body .= '<p style="line-height:1.6;">' . t('email.reset_msg') . '</p>';
    $body .= '<div style="text-align:center;margin:35px 0;">';
    $body .= '<a href="' . $reset_url . '" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">' . t('email.reset_btn') . '</a>';
    $body .= '</div>';
    $body .= '<p style="color:#888;font-size:0.85em;text-align:center;">' . t('email.reset_expiry') . '</p>';
    $body .= '<p style="color:#666;font-size:0.8em;">' . t('email.reset_ignore') . '</p>';
    $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
    $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; 2026 ' . mail_domain() . ' | help@' . mail_domain() . '</p>';
    $body .= '</div></body></html>';
    
    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($to_email, $subject, $body);
    
    if (!$result) {
        error_log("SMTP reset email failed. Log:\n" . $mailer->getLog());
    }
    
    return $result;
}

function send_installation_key_email($to_email, $name, $key, $version) {
    $subject = SITE_NAME . ' - Your Installation Key (v' . $version . ')';
    
    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
    $body .= '<div style="max-width:500px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
    $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">' . SITE_NAME . '</h2></div>';
    $body .= '<p style="font-size:1.1em;">Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>';
    $body .= '<p style="line-height:1.6;">Thank you for downloading GTMC-NPI v' . htmlspecialchars($version) . '. Your unique installation key is:</p>';
    $body .= '<div style="text-align:center;margin:35px 0;">';
    $body .= '<div style="background:rgba(0,0,0,0.3);color:#10b981;padding:20px;border-radius:8px;font-family:monospace;font-size:1.5em;letter-spacing:2px;display:inline-block;">' . htmlspecialchars($key) . '</div>';
    $body .= '</div>';
    $body .= '<p style="line-height:1.6;">You will need this key during the setup wizard to activate your software.</p>';
    $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
    $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; ' . date('Y') . ' ' . mail_domain() . ' | services@' . mail_domain() . '</p>';
    $body .= '</div></body></html>';
    
    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($to_email, $subject, $body);
    
    if (!$result) {
        error_log("SMTP install key email failed. Log:\n" . $mailer->getLog());
    }

    return $result;
}

/**
 * Email #2 del doppio opt-in demo: consegna la chiave di licenza
 * NPI- che il programma di installazione chiede allo step "Licență".
 * Le traduzioni seguono la lingua del link di conferma (?lang=…).
 */
function send_demo_license_email($to_email, $name, $key, $expires_at) {
    $subject = SITE_NAME . ' - ' . t('email.demo_key_subject');

    $expiry_fmt = $expires_at ? date('d.m.Y', strtotime($expires_at)) : '';
    $key_html   = htmlspecialchars($key);
    $name_html  = htmlspecialchars($name);
    $download_url = SITE_URL . '/download.php';

    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0a0a1a;color:#e0e0e0;padding:40px;">';
    $body .= '<div style="max-width:560px;margin:0 auto;background:#1a1a2e;border-radius:16px;padding:40px;border:1px solid rgba(99,102,241,0.3);">';
    $body .= '<div style="text-align:center;margin-bottom:30px;"><h2 style="color:#6366f1;">' . SITE_NAME . '</h2></div>';
    $body .= '<p style="font-size:1.1em;">' . t('email.greeting') . ' <strong>' . $name_html . '</strong>,</p>';
    $body .= '<p style="line-height:1.6;">' . t('email.demo_key_msg') . '</p>';

    $body .= '<div style="background:rgba(0,0,0,0.3);border:1px solid rgba(16,185,129,0.4);border-radius:8px;padding:20px;margin:25px 0;text-align:center;">';
    $body .= '<p style="margin:0 0 8px;color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:1px;">' . t('email.demo_key_label') . '</p>';
    $body .= '<code style="display:inline-block;color:#10b981;padding:12px 20px;background:rgba(0,0,0,0.4);border-radius:6px;font-family:monospace;font-size:1.2em;letter-spacing:2px;">' . $key_html . '</code>';
    $body .= '</div>';

    $body .= '<div style="background:rgba(16,185,129,0.08);border-left:4px solid #10b981;padding:15px 20px;border-radius:0 8px 8px 0;margin:25px 0;">';
    $body .= '<p style="margin:0 0 10px;font-weight:600;color:#10b981;">' . t('email.demo_key_steps_title') . '</p>';
    $body .= '<ol style="margin:0;padding-left:20px;line-height:2;">';
    $body .= '<li>' . t('email.demo_key_step1') . '</li>';
    $body .= '<li>' . t('email.demo_key_step2') . '</li>';
    $body .= '<li>' . t('email.demo_key_step3') . '</li>';
    $body .= '</ol>';
    $body .= '</div>';

    if ($expiry_fmt) {
        $body .= '<p style="line-height:1.6;">' . t('email.demo_key_trial_until') . ' <strong>' . $expiry_fmt . '</strong>.</p>';
    }
    $body .= '<p style="line-height:1.6;color:#888;font-size:0.9em;">' . t('email.demo_key_account') . '</p>';
    $body .= '<div style="text-align:center;margin:30px 0;">';
    $body .= '<a href="' . htmlspecialchars($download_url) . '" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">' . t('email.demo_key_download_btn') . '</a>';
    $body .= '</div>';
    $body .= '<hr style="border:none;border-top:1px solid rgba(99,102,241,0.2);margin:25px 0;">';
    $body .= '<p style="color:#666;font-size:0.8em;text-align:center;">&copy; ' . date('Y') . ' ' . mail_domain() . ' | help@' . mail_domain() . '</p>';
    $body .= '</div></body></html>';

    $mailer = new SmtpMailer();
    $result = $mailer->sendMail($to_email, $subject, $body);

    if (!$result) {
        error_log("SMTP demo license email failed. Log:\n" . $mailer->getLog());
    }

    return $result;
}
