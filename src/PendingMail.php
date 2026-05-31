<?php
declare(strict_types=1);

namespace Vancil\FlintMail;

use Flint\Queue\Queue;
use Vancil\FlintMail\Jobs\SendMailJob;

class PendingMail
{
    private string $subject  = '';
    private string $htmlBody = '';
    private string $textBody = '';
    private string $replyTo  = '';
    private array  $cc       = [];
    private array  $bcc      = [];
    private array  $attachments = [];

    public function __construct(
        private readonly Drivers\DriverInterface $driver,
        private readonly string $to,
        private readonly string $toName = '',
    ) {}

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $body): static
    {
        $this->htmlBody = $body;
        return $this;
    }

    public function text(string $body): static
    {
        $this->textBody = $body;
        return $this;
    }

    /** Render an Ember view as the HTML body. */
    public function view(string $emberView, array $data = []): static
    {
        if (isset($GLOBALS['__flint_app'])) {
            $engine = $GLOBALS['__flint_app']->make(\Flint\View\EmberEngine::class);
            $this->htmlBody = $engine->render($emberView, $data);
        }
        return $this;
    }

    public function replyTo(string $email): static
    {
        $this->replyTo = $email;
        return $this;
    }

    public function cc(string $email, string $name = ''): static
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function bcc(string $email, string $name = ''): static
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function attach(string $path, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = ['path' => $path, 'name' => $name, 'mime' => $mime];
        return $this;
    }

    /** Send synchronously. */
    public function send(): void
    {
        $this->driver->send($this->buildMessage());
    }

    /** Persist to queued_mails and dispatch a SendMailJob. */
    public function queue(string $queueName = 'default'): void
    {
        $record = QueuedMail::create($this->persistableData());
        Queue::dispatch(new SendMailJob($record->id), $queueName);
    }

    private function buildMessage(): MailMessage
    {
        $from     = config('mail.from.address', 'hello@example.com');
        $fromName = config('mail.from.name', 'Flint');

        return new MailMessage(
            to:          $this->to,
            toName:      $this->toName,
            subject:     $this->subject,
            htmlBody:    $this->htmlBody,
            textBody:    $this->textBody,
            from:        $from,
            fromName:    $fromName,
            replyTo:     $this->replyTo,
            cc:          $this->cc,
            bcc:         $this->bcc,
            attachments: $this->attachments,
        );
    }

    private function persistableData(): array
    {
        return [
            'to_email'    => $this->to,
            'to_name'     => $this->toName,
            'from_email'  => config('mail.from.address', 'hello@example.com'),
            'from_name'   => config('mail.from.name', 'Flint'),
            'reply_to'    => $this->replyTo,
            'cc'          => empty($this->cc)          ? null : json_encode($this->cc),
            'bcc'         => empty($this->bcc)         ? null : json_encode($this->bcc),
            'subject'     => $this->subject,
            'html_body'   => $this->htmlBody,
            'text_body'   => $this->textBody,
            'attachments' => empty($this->attachments) ? null : json_encode($this->attachments),
            'status'      => 'pending',
        ];
    }
}
