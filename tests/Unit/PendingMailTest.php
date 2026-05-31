<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\DriverInterface;
use Vancil\FlintMail\MailMessage;
use Vancil\FlintMail\PendingMail;

class PendingMailTest extends TestCase
{
    private function captureDriver(): array
    {
        $log = new \stdClass();
        $log->messages = [];

        $driver = new class($log) implements DriverInterface {
            public function __construct(private \stdClass $log) {}
            public function send(MailMessage $message): void { $this->log->messages[] = $message; }
        };

        return [$driver, $log];
    }

    public function test_send_passes_message_to_driver(): void
    {
        [$driver, $log] = $this->captureDriver();

        (new PendingMail($driver, 'a@example.com', 'Alice'))
            ->subject('Hello')
            ->html('<p>Hi</p>')
            ->send();

        $this->assertCount(1, $log->messages);
        $this->assertSame('a@example.com', $log->messages[0]->to);
        $this->assertSame('Alice', $log->messages[0]->toName);
        $this->assertSame('Hello', $log->messages[0]->subject);
        $this->assertSame('<p>Hi</p>', $log->messages[0]->htmlBody);
    }

    public function test_cc_is_included_in_message(): void
    {
        [$driver, $log] = $this->captureDriver();

        (new PendingMail($driver, 'a@example.com'))
            ->subject('Hi')
            ->cc('b@example.com', 'Bob')
            ->send();

        $this->assertCount(1, $log->messages[0]->cc);
        $this->assertSame('b@example.com', $log->messages[0]->cc[0]['email']);
    }

    public function test_bcc_is_included_in_message(): void
    {
        [$driver, $log] = $this->captureDriver();

        (new PendingMail($driver, 'a@example.com'))
            ->subject('Hi')
            ->bcc('c@example.com')
            ->send();

        $this->assertCount(1, $log->messages[0]->bcc);
        $this->assertSame('c@example.com', $log->messages[0]->bcc[0]['email']);
    }

    public function test_reply_to_is_included_in_message(): void
    {
        [$driver, $log] = $this->captureDriver();

        (new PendingMail($driver, 'a@example.com'))
            ->subject('Hi')
            ->replyTo('reply@example.com')
            ->send();

        $this->assertSame('reply@example.com', $log->messages[0]->replyTo);
    }

    public function test_attach_is_included_in_message(): void
    {
        [$driver, $log] = $this->captureDriver();

        $tmpFile = tempnam(sys_get_temp_dir(), 'pm_test_');
        file_put_contents($tmpFile, 'data');

        (new PendingMail($driver, 'a@example.com'))
            ->subject('Hi')
            ->attach($tmpFile, 'file.txt', 'text/plain')
            ->send();

        $this->assertCount(1, $log->messages[0]->attachments);
        $this->assertSame($tmpFile, $log->messages[0]->attachments[0]['path']);

        unlink($tmpFile);
    }

    public function test_multiple_cc_recipients(): void
    {
        [$driver, $log] = $this->captureDriver();

        (new PendingMail($driver, 'a@example.com'))
            ->subject('Hi')
            ->cc('b@example.com')
            ->cc('c@example.com')
            ->send();

        $this->assertCount(2, $log->messages[0]->cc);
    }
}
