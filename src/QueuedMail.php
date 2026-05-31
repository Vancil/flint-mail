<?php
declare(strict_types=1);

namespace Vancil\FlintMail;

use Flint\Model;

class QueuedMail extends Model
{
    protected string $table = 'queued_mails';

    protected array $fillable = [
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'reply_to',
        'cc',
        'bcc',
        'subject',
        'html_body',
        'text_body',
        'attachments',
        'status',
        'attempts',
        'error',
        'sent_at',
    ];

    public function ccArray(): array
    {
        return $this->cc ? json_decode($this->cc, true) : [];
    }

    public function bccArray(): array
    {
        return $this->bcc ? json_decode($this->bcc, true) : [];
    }

    public function attachmentsArray(): array
    {
        return $this->attachments ? json_decode($this->attachments, true) : [];
    }
}
