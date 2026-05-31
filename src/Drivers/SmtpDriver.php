<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class SmtpDriver implements DriverInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port       = 587,
        private readonly string $username   = '',
        private readonly string $password   = '',
        private readonly string $encryption = 'tls',
    ) {}

    public function send(MailMessage $message): void
    {
        $socket = $this->connect();

        try {
            $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->expect($socket, 220);
            $this->command($socket, "EHLO {$hostname}");
            $this->read($socket);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command($socket, "EHLO {$hostname}");
                $this->read($socket);
            }

            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN');
                $this->expect($socket, 334);
                $this->command($socket, base64_encode($this->username));
                $this->expect($socket, 334);
                $this->command($socket, base64_encode($this->password));
                $this->expect($socket, 235);
            }

            $from = $message->from ?: config('mail.from.address', 'hello@example.com');
            $this->command($socket, "MAIL FROM:<{$from}>");
            $this->expect($socket, 250);

            foreach ($this->allRecipients($message) as $email) {
                $this->command($socket, "RCPT TO:<{$email}>");
                $this->expect($socket, 250);
            }

            $this->command($socket, 'DATA');
            $this->expect($socket, 354);

            fwrite($socket, $this->buildRawMessage($message) . "\r\n.\r\n");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect(): mixed
    {
        $prefix = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = stream_socket_client(
            "{$prefix}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        return $socket;
    }

    private function buildRawMessage(MailMessage $message): string
    {
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "To: =?UTF-8?B?" . base64_encode($message->toName) . "?= <{$message->to}>\r\n";

        if ($message->replyTo !== '') {
            $headers .= "Reply-To: {$message->replyTo}\r\n";
        }

        if (!empty($message->cc)) {
            $headers .= "Cc: " . $this->formatAddressList($message->cc) . "\r\n";
        }

        if (!empty($message->bcc)) {
            $headers .= "Bcc: " . $this->formatAddressList($message->bcc) . "\r\n";
        }

        $headers .= "Subject: =?UTF-8?B?" . base64_encode($message->subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if (empty($message->attachments)) {
            return $headers . $this->buildAlternativePart($message);
        }

        $mixedBoundary = 'mixed_' . md5(uniqid('', true));
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n";

        $body  = "--{$mixedBoundary}\r\n";
        $body .= $this->buildAlternativePart($message);

        foreach ($message->attachments as $att) {
            $content  = base64_encode(file_get_contents($att['path']));
            $name     = $att['name'] ?: basename($att['path']);
            $mime     = $att['mime'] ?: ($this->guessMime($att['path']));
            $body    .= "--{$mixedBoundary}\r\n";
            $body    .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $body    .= "Content-Transfer-Encoding: base64\r\n";
            $body    .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $body    .= chunk_split($content) . "\r\n";
        }

        $body .= "--{$mixedBoundary}--";

        return $headers . "\r\n" . $body;
    }

    private function buildAlternativePart(MailMessage $message): string
    {
        $altBoundary = 'alt_' . md5(uniqid('', true));
        $part  = "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $part .= "--{$altBoundary}\r\n";
        $part .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $part .= ($message->textBody ?: strip_tags($message->htmlBody)) . "\r\n";

        if ($message->htmlBody !== '') {
            $part .= "--{$altBoundary}\r\n";
            $part .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $part .= $message->htmlBody . "\r\n";
        }

        $part .= "--{$altBoundary}--\r\n";
        return $part;
    }

    private function formatAddressList(array $addresses): string
    {
        return implode(', ', array_map(
            fn($a) => $a['name'] !== '' ? "\"{$a['name']}\" <{$a['email']}>" : $a['email'],
            $addresses
        ));
    }

    /** Returns all envelope recipient addresses (To + CC + BCC). */
    private function allRecipients(MailMessage $message): array
    {
        $emails = [$message->to];
        foreach (array_merge($message->cc, $message->bcc) as $a) {
            $emails[] = $a['email'];
        }
        return array_filter($emails);
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'csv'  => 'text/csv',
            'txt'  => 'text/plain',
            'zip'  => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    private function command(mixed $socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function read(mixed $socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function expect(mixed $socket, int $code): void
    {
        $response = $this->read($socket);
        $actual   = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException("SMTP expected {$code}, got {$actual}: {$response}");
        }
    }
}
