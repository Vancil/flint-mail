<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class LogDriver implements DriverInterface
{
    public function __construct(private readonly string $logPath) {}

    public function send(MailMessage $message): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $lines = [
            str_repeat('-', 60),
            'Date:    ' . date('Y-m-d H:i:s'),
            "To:      {$message->toName} <{$message->to}>",
            "From:    {$message->fromName} <{$message->from}>",
            "Subject: {$message->subject}",
        ];

        if ($message->replyTo !== '') {
            $lines[] = "Reply-To: {$message->replyTo}";
        }

        if (!empty($message->cc)) {
            $cc = implode(', ', array_map(fn($a) => "{$a['name']} <{$a['email']}>", $message->cc));
            $lines[] = "CC: {$cc}";
        }

        if (!empty($message->bcc)) {
            $bcc = implode(', ', array_map(fn($a) => "{$a['name']} <{$a['email']}>", $message->bcc));
            $lines[] = "BCC: {$bcc}";
        }

        if (!empty($message->attachments)) {
            $names   = array_map(fn($a) => $a['name'] ?: basename($a['path']), $message->attachments);
            $lines[] = "Attachments: " . implode(', ', $names);
        }

        $lines[] = '';
        $lines[] = $message->textBody ?: strip_tags($message->htmlBody);
        $lines[] = '';

        file_put_contents($this->logPath, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }
}
