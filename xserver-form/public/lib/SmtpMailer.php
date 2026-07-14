<?php
// Smart Labo Works — 依存ライブラリなしの最小SMTPクライアント(STARTTLS + AUTH LOGIN)
//
// PHPMailer等の外部ライブラリを同梱せず、標準のstream関数のみで実装した。
// 問い合わせフォームという低頻度・低ボリュームの用途に見合った、監査しやすい
// 最小実装とすることを優先している。HTML/添付ファイル等の高度な機能が
// 将来必要になった場合は、PHPMailerへの置き換えを検討すること。

final class SlwSmtpMailer
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $bodyText): void
    {
        $socket = @stream_socket_client(
            'tcp://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            10
        );
        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        try {
            $this->expect($socket, '220');
            $this->command($socket, 'EHLO smartlaboworks.com', '250');
            $this->command($socket, 'STARTTLS', '220');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }

            $this->command($socket, 'EHLO smartlaboworks.com', '250');
            $this->command($socket, 'AUTH LOGIN', '334');
            $this->command($socket, base64_encode($this->user), '334');
            $this->command($socket, base64_encode($this->pass), '235');

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', '250');
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', '250');
            $this->command($socket, 'DATA', '354');

            $headers = [
                'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
                'To: <' . $toEmail . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'Date: ' . date('r'),
            ];

            // 本文をbase64化することで、SMTPのドットスタッフィング(行頭"."のエスケープ)を
            // 考慮する必要をなくしている(base64アルファベットに"."は含まれない)。
            $encodedBody = chunk_split(base64_encode($bodyText));

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.";
            $this->command($socket, $data, '250');

            $this->command($socket, 'QUIT', '221');
        } finally {
            fclose($socket);
        }
    }

    private function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /** @param resource $socket */
    private function expect($socket, string $expectedCode): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException('Unexpected SMTP response (expected ' . $expectedCode . '): ' . trim($response));
        }
        return $response;
    }

    /** @param resource $socket */
    private function command($socket, string $command, string $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCode);
    }
}
