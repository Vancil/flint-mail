<?php
declare(strict_types=1);

namespace Vancil\FlintMail;

use Flint\Mail\Mailer as BaseMailer;

class Mailer extends BaseMailer
{
    public function __construct(private readonly Drivers\DriverInterface $flintMailDriver)
    {
        // Do not call parent::__construct() — we use our own driver
    }

    /** Begin building a mail to the given address. */
    public function to(string $email, string $name = ''): PendingMail
    {
        return new PendingMail($this->flintMailDriver, $email, $name);
    }

    /** Send a fully-built MailMessage directly (used by SendMailJob). */
    public function sendMessage(MailMessage $message): void
    {
        $this->flintMailDriver->send($message);
    }
}
