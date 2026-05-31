<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

interface DriverInterface
{
    public function send(MailMessage $message): void;
}
