<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Jobs;

use Flint\Queue\Job;
use Vancil\FlintMail\MailMessage;
use Vancil\FlintMail\Mailer;
use Vancil\FlintMail\QueuedMail;

class SendMailJob extends Job
{
    public int $tries      = 3;
    public int $retryAfter = 60;

    public function __construct(private readonly int $queuedMailId) {}

    public function handle(): void
    {
        $record = QueuedMail::find($this->queuedMailId);

        if (!$record || $record->status === 'sent') {
            return;
        }

        $record->update([
            'status'   => 'processing',
            'attempts' => (int) $record->attempts + 1,
        ]);

        $message = new MailMessage(
            to:          $record->to_email,
            toName:      $record->to_name ?? '',
            subject:     $record->subject,
            htmlBody:    $record->html_body ?? '',
            textBody:    $record->text_body ?? '',
            from:        $record->from_email,
            fromName:    $record->from_name ?? '',
            replyTo:     $record->reply_to ?? '',
            cc:          $record->ccArray(),
            bcc:         $record->bccArray(),
            attachments: $record->attachmentsArray(),
        );

        $GLOBALS['__flint_app']->make(Mailer::class)->sendMessage($message);

        $record->update([
            'status'  => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'error'   => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        QueuedMail::find($this->queuedMailId)?->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
}
