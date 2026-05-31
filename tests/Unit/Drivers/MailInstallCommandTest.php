<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use Flint\Application;
use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Commands\MailInstall;
use Vancil\FlintMail\Commands\MakeMail;

class MailInstallCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/flint_mail_install_' . uniqid('', true);
        mkdir($this->dir);
        mkdir($this->dir . '/database/migrations', 0755, true);
        file_put_contents($this->dir . '/.env', "APP_NAME=Test\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function test_publishes_config(): void
    {
        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        $this->assertFileExists($this->dir . '/config/mail.php');
        $this->assertStringContainsString('MAIL_DRIVER', file_get_contents($this->dir . '/config/mail.php'));
    }

    public function test_publishes_migration(): void
    {
        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        $migrations = glob($this->dir . '/database/migrations/*_create_queued_mails_table.php');
        $this->assertCount(1, $migrations);
    }

    public function test_does_not_duplicate_migration(): void
    {
        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        $cmd->handle([]);
        ob_end_clean();

        $migrations = glob($this->dir . '/database/migrations/*_create_queued_mails_table.php');
        $this->assertCount(1, $migrations);
    }

    public function test_does_not_overwrite_existing_config(): void
    {
        mkdir($this->dir . '/config', 0755, true);
        file_put_contents($this->dir . '/config/mail.php', '<?php // existing');

        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        $this->assertSame('<?php // existing', file_get_contents($this->dir . '/config/mail.php'));
    }

    public function test_writes_env_defaults(): void
    {
        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        $env = file_get_contents($this->dir . '/.env');
        $this->assertStringContainsString('MAIL_DRIVER=', $env);
        $this->assertStringContainsString('MAILGUN_SECRET=', $env);
        $this->assertStringContainsString('SENDGRID_API_KEY=', $env);
    }

    public function test_env_defaults_not_duplicated(): void
    {
        file_put_contents($this->dir . '/.env', "MAIL_DRIVER=smtp\n");

        $cmd = new MailInstall(new Application($this->dir));
        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        $env = file_get_contents($this->dir . '/.env');
        $this->assertSame(1, substr_count($env, 'MAIL_DRIVER='));
    }

    public function test_make_mail_creates_mailable_file(): void
    {
        $cmd = new MakeMail(new Application($this->dir));
        ob_start();
        $cmd->handle(['WelcomeEmail']);
        ob_end_clean();

        $this->assertFileExists($this->dir . '/app/Mail/WelcomeEmail.php');
        $content = file_get_contents($this->dir . '/app/Mail/WelcomeEmail.php');
        $this->assertStringContainsString('class WelcomeEmail extends Mailable', $content);
        $this->assertStringNotContainsString('ExampleMailable', $content);
    }

    public function test_make_mail_does_not_overwrite_existing(): void
    {
        mkdir($this->dir . '/app/Mail', 0755, true);
        file_put_contents($this->dir . '/app/Mail/WelcomeEmail.php', '<?php // existing');

        $cmd = new MakeMail(new Application($this->dir));
        ob_start();
        $cmd->handle(['WelcomeEmail']);
        ob_end_clean();

        $this->assertSame('<?php // existing', file_get_contents($this->dir . '/app/Mail/WelcomeEmail.php'));
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }
        rmdir($path);
    }
}
