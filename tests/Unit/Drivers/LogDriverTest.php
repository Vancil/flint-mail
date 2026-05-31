<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\LogDriver;
use Vancil\FlintMail\MailMessage;

class LogDriverTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'flint_mail_log_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function test_writes_basic_fields_to_log(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->send(new MailMessage(
            to:      'user@example.com',
            toName:  'User',
            subject: 'Test Subject',
            from:    'sender@example.com',
        ));

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('user@example.com', $log);
        $this->assertStringContainsString('Test Subject', $log);
        $this->assertStringContainsString('sender@example.com', $log);
    }

    public function test_writes_reply_to_when_set(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->send(new MailMessage(
            to:      'user@example.com',
            subject: 'Hi',
            replyTo: 'reply@example.com',
        ));

        $this->assertStringContainsString('reply@example.com', file_get_contents($this->logFile));
    }

    public function test_writes_cc_recipients(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->send(new MailMessage(
            to:      'user@example.com',
            subject: 'Hi',
            cc:      [['email' => 'cc@example.com', 'name' => 'CC User']],
        ));

        $this->assertStringContainsString('cc@example.com', file_get_contents($this->logFile));
    }

    public function test_writes_attachment_names(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'attach_');
        file_put_contents($tmpFile, 'data');

        $driver = new LogDriver($this->logFile);
        $driver->send(new MailMessage(
            to:          'user@example.com',
            subject:     'Hi',
            attachments: [['path' => $tmpFile, 'name' => 'invoice.pdf', 'mime' => '']],
        ));

        unlink($tmpFile);
        $this->assertStringContainsString('invoice.pdf', file_get_contents($this->logFile));
    }

    public function test_appends_multiple_sends(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->send(new MailMessage(to: 'a@example.com', subject: 'First'));
        $driver->send(new MailMessage(to: 'b@example.com', subject: 'Second'));

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('a@example.com', $log);
        $this->assertStringContainsString('b@example.com', $log);
    }

    public function test_creates_log_directory_if_missing(): void
    {
        $dir     = sys_get_temp_dir() . '/flint_mail_test_' . uniqid('', true);
        $logFile = $dir . '/mail.log';
        $driver  = new LogDriver($logFile);

        $driver->send(new MailMessage(to: 'a@example.com', subject: 'Hi'));

        $this->assertFileExists($logFile);
        unlink($logFile);
        rmdir($dir);
    }
}
