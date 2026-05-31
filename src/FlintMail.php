<?php
declare(strict_types=1);

namespace Vancil\FlintMail;

use Flint\Application;
use Flint\Container;
use Vancil\FlintMail\Commands\MailInstall;
use Vancil\FlintMail\Commands\MakeMail;
use Vancil\FlintMail\Drivers\LogDriver;
use Vancil\FlintMail\Drivers\MailgunDriver;
use Vancil\FlintMail\Drivers\PostmarkDriver;
use Vancil\FlintMail\Drivers\SesDriver;
use Vancil\FlintMail\Drivers\SendGridDriver;
use Vancil\FlintMail\Drivers\SmtpDriver;

class FlintMail
{
    public static function register(Application $app): void
    {
        $mailer = self::buildMailer($app->basePath);

        // Register as both FlintMail\Mailer and Flint\Mail\Mailer so all injection
        // works regardless of which type is declared in controller constructors
        $container = $app->make(Container::class);
        $container->instance(Mailer::class, $mailer);
        $container->instance(\Flint\Mail\Mailer::class, $mailer);
    }

    public static function commands(Application $app): array
    {
        return [
            new MailInstall($app),
            new MakeMail($app),
        ];
    }

    private static function buildMailer(string $basePath): Mailer
    {
        $driver = match (config('mail.driver', 'log')) {
            'smtp' => new SmtpDriver(
                host:       config('mail.host', '127.0.0.1'),
                port:       (int) config('mail.port', 587),
                username:   config('mail.username', ''),
                password:   config('mail.password', ''),
                encryption: config('mail.encryption', 'tls'),
            ),
            'mailgun' => new MailgunDriver(
                key:    config('mail.mailgun.key', ''),
                domain: config('mail.mailgun.domain', ''),
                region: config('mail.mailgun.region', 'us'),
            ),
            'postmark' => new PostmarkDriver(
                token: config('mail.postmark.token', ''),
            ),
            'ses' => new SesDriver(
                key:    config('mail.ses.key', ''),
                secret: config('mail.ses.secret', ''),
                region: config('mail.ses.region', 'us-east-1'),
            ),
            'sendgrid' => new SendGridDriver(
                key: config('mail.sendgrid.key', ''),
            ),
            default => new LogDriver($basePath . '/storage/logs/mail.log'),
        };

        return new Mailer($driver);
    }
}
