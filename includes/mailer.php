<?php
/**
 * Gmail SMTP Mail Gönderici
 * Tamamen yerel, bağımlılıksız SMTP client (STARTTLS + AUTH LOGIN)
 */

if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

class SmtpClient {
    private $sock;
    private string $host;
    private int    $port;
    private string $secure;   // 'tls' | 'ssl' | ''
    private string $user;
    private string $pass;
    private int    $timeout   = 30;
    private array  $log       = [];
    public  bool   $debug     = false;

    public function __construct(string $host, int $port, string $secure, string $user, string $pass) {
        $this->host   = $host;
        $this->port   = $port;
        $this->secure = $secure;
        $this->user   = $user;
        $this->pass   = $pass;
    }

    public function getLog(): array { return $this->log; }

    private function connect(): void {
        $errno = 0; $errstr = '';
        $remote = ($this->secure === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $this->sock = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) {
            throw new RuntimeException("SMTP bağlantı hatası: [$errno] $errstr");
        }
        stream_set_timeout($this->sock, $this->timeout);
        $this->expect(220);
    }

    private function send(string $cmd): void {
        if ($this->debug) $this->log[] = '> ' . $cmd;
        fwrite($this->sock, $cmd . "\r\n");
    }

    private function readLine(): string {
        $line = fgets($this->sock, 8192);
        if ($line === false) throw new RuntimeException('SMTP: sunucu yanıt vermedi');
        if ($this->debug) $this->log[] = '< ' . rtrim($line);
        return $line;
    }

    /**
     * Çok satırlı yanıtları oku (250-XYZ\r\n250 OK gibi)
     */
    private function expect(int $code): string {
        $out = '';
        while (true) {
            $line = $this->readLine();
            $out .= $line;
            if (strlen($line) < 4) break;
            // "250 " = son satır, "250-" = devam var
            if ($line[3] === ' ') break;
        }
        $actual = (int)substr($out, 0, 3);
        if ($actual !== $code) {
            throw new RuntimeException("SMTP beklenmedik yanıt: bekleniyor $code, gelen: " . trim($out));
        }
        return $out;
    }

    public function connectTest(): void {
        $this->connect();
        $ehlo = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->send('EHLO ' . $ehlo);
        $this->expect(250);

        if ($this->secure === 'tls') {
            $this->send('STARTTLS');
            $this->expect(220);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($this->sock, true, $crypto)) {
                throw new RuntimeException('STARTTLS şifreleme başarısız');
            }
            $this->send('EHLO ' . $ehlo);
            $this->expect(250);
        }

        $this->send('AUTH LOGIN');
        $this->expect(334);
        $this->send(base64_encode($this->user));
        $this->expect(334);
        $this->send(base64_encode($this->pass));
        $this->expect(235);

        $this->send('QUIT');
        fclose($this->sock);
    }

    /**
     * Mail gönder
     */
    public function send_mail(string $from, string $fromName, array $to, array $cc, string $subject, string $html, array $attachments = []): void {
        $this->connect();
        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';

        $this->send('EHLO ' . $ehloHost);
        $this->expect(250);

        if ($this->secure === 'tls') {
            $this->send('STARTTLS');
            $this->expect(220);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($this->sock, true, $crypto)) {
                throw new RuntimeException('STARTTLS şifreleme başarısız');
            }
            $this->send('EHLO ' . $ehloHost);
            $this->expect(250);
        }

        if ($this->user !== '' && $this->pass !== '') {
            $this->send('AUTH LOGIN');
            $this->expect(334);
            $this->send(base64_encode($this->user));
            $this->expect(334);
            $this->send(base64_encode($this->pass));
            $this->expect(235);
        }

        $this->send('MAIL FROM:<' . $from . '>');
        $this->expect(250);

        foreach (array_merge($to, $cc) as $rcpt) {
            if (!$rcpt) continue;
            $this->send('RCPT TO:<' . $rcpt . '>');
            $this->expect(250);
        }

        $this->send('DATA');
        $this->expect(354);

        $body = $this->build_message($from, $fromName, $to, $cc, $subject, $html, $attachments);
        // Dot-stuffing
        $body = preg_replace('/^\./m', '..', $body);
        fwrite($this->sock, $body . "\r\n.\r\n");
        $this->expect(250);

        $this->send('QUIT');
        fclose($this->sock);
    }

    /**
     * RFC5322 uyumlu mesaj oluştur
     */
    private function build_message(string $from, string $fromName, array $to, array $cc, string $subject, string $html, array $attachments): string {
        $boundary = 'AKM_BOUND_' . bin2hex(random_bytes(8));
        $altBound = 'AKM_ALT_'   . bin2hex(random_bytes(8));

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>';
        $headers[] = 'From: ' . $this->encode_header_name($fromName) . ' <' . $from . '>';
        $headers[] = 'To: ' . implode(', ', array_map(fn($x) => '<' . $x . '>', $to));
        if (!empty($cc)) {
            $headers[] = 'Cc: ' . implode(', ', array_map(fn($x) => '<' . $x . '>', $cc));
        }
        $headers[] = 'Subject: ' . $this->encode_header_value($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: AKM-SMTP/1.0';

        if (!empty($attachments)) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        } else {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBound . '"';
        }

        $altPart = $this->build_alternative($altBound, $html);

        if (!empty($attachments)) {
            $out = implode("\r\n", $headers) . "\r\n\r\n";
            $out .= "--$boundary\r\n";
            $out .= "Content-Type: multipart/alternative; boundary=\"$altBound\"\r\n\r\n";
            $out .= $altPart;
            foreach ($attachments as $a) {
                $name    = $a['name']    ?? 'ek.pdf';
                $type    = $a['type']    ?? 'application/octet-stream';
                $content = $a['content'] ?? (isset($a['path']) && is_file($a['path']) ? file_get_contents($a['path']) : '');
                if ($content === '') continue;
                $encoded = chunk_split(base64_encode($content));
                $out .= "--$boundary\r\n";
                $out .= "Content-Type: $type; name=\"$name\"\r\n";
                $out .= "Content-Transfer-Encoding: base64\r\n";
                $out .= "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n";
                $out .= $encoded . "\r\n";
            }
            $out .= "--$boundary--";
            return $out;
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $altPart;
    }

    private function build_alternative(string $boundary, string $html): string {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html))));
        $out  = '';
        $out .= "--$boundary\r\n";
        $out .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= chunk_split(base64_encode($text)) . "\r\n";
        $out .= "--$boundary\r\n";
        $out .= "Content-Type: text/html; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= chunk_split(base64_encode($html)) . "\r\n";
        $out .= "--$boundary--\r\n";
        return $out;
    }

    private function encode_header_value(string $v): string {
        if (preg_match('/[^\x20-\x7E]/', $v)) {
            return '=?UTF-8?B?' . base64_encode($v) . '?=';
        }
        return $v;
    }

    private function encode_header_name(string $v): string {
        $v = str_replace('"', '', $v);
        if (preg_match('/[^\x20-\x7E]/', $v)) {
            return '=?UTF-8?B?' . base64_encode($v) . '?=';
        }
        return '"' . $v . '"';
    }
}

/**
 * Ayarlarla SMTP client üret
 */
function mailer_make(): SmtpClient {
    return new SmtpClient(
        ayar_get('smtp_host', 'smtp.gmail.com'),
        (int)ayar_get('smtp_port', '587'),
        ayar_get('smtp_secure', 'tls'),
        ayar_get('smtp_user', ''),
        ayar_get('smtp_pass', '')
    );
}

/**
 * Mail gönder
 * @return array ['ok'=>bool,'msg'=>string]
 */
function mail_gonder(string $to, string $konu, string $html, array $cc = [], array $attachments = []): array {
    $fromEmail = ayar_get('smtp_from_email', '') ?: ayar_get('smtp_user', '');
    $fromName  = ayar_get('smtp_from_name', SITE_NAME);
    if (!$fromEmail) {
        return ['ok' => false, 'msg' => 'Gönderen e-postası tanımlı değil. Ayarlar > E-posta bölümünden tanımlayın.'];
    }
    try {
        $c = mailer_make();
        $c->debug = DEBUG;
        $c->send_mail($fromEmail, $fromName, [$to], array_values(array_filter($cc)), $konu, $html, $attachments);
        return ['ok' => true, 'msg' => 'Gönderildi'];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}

function mail_test_connection(): array {
    try {
        $c = mailer_make();
        $c->debug = DEBUG;
        $c->connectTest();
        return ['ok' => true, 'msg' => 'SMTP bağlantısı ve kimlik doğrulama başarılı.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}
