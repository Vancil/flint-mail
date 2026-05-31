<?php
declare(strict_types=1);

namespace Tests\Unit;

use Flint\Application;
use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Commands\MailInstall;
use Vancil\FlintMail\Commands\MakeMail;
use Vancil\FlintMail\FlintMail;
use Vancil\FlintMail\Mailer;

class FlintMailTest extends TestCase
{
    public function test_commands_returns_mail_install_and_make_mail(): void
    {
        $app      = new Application(sys_get_temp_dir());
        $commands = FlintMail::commands($app);

        $this->assertCount(2, $commands);
        $this->assertInstanceOf(MailInstall::class, $commands[0]);
        $this->assertInstanceOf(MakeMail::class, $commands[1]);
    }

    public function test_mail_install_command_has_correct_signature(): void
    {
        $app     = new Application(sys_get_temp_dir());
        $command = new MailInstall($app);

        $this->assertSame('mail:install', $command->signature());
    }

    public function test_make_mail_command_has_correct_signature(): void
    {
        $app     = new Application(sys_get_temp_dir());
        $command = new MakeMail($app);

        $this->assertSame('make:mail', $command->signature());
    }
}
