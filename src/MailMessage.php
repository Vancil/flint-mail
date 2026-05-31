<?php
declare(strict_types=1);

namespace Vancil\FlintMail;

use Flint\Mail\MailMessage as BaseMailMessage;

class MailMessage extends BaseMailMessage
{
    public function __construct(
        string $to        = '',
        string $toName    = '',
        string $subject   = '',
        string $htmlBody  = '',
        string $textBody  = '',
        string $from      = '',
        string $fromName  = '',
        public string $replyTo     = '',
        public array  $cc          = [],
        public array  $bcc         = [],
        public array  $attachments = [],
    ) {
        parent::__construct($to, $toName, $subject, $htmlBody, $textBody, $from, $fromName);
    }
}
