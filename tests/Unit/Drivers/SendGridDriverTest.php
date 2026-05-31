<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\SendGridDriver;
use Vancil\FlintMail\MailMessage;

class SendGridDriverTest extends TestCase
{
    private function captureDriver(string $key = 'sg-test-key'): array
    {
        $log = new \stdClass();
        $log->requests = [];

        $driver = new class($key, $log) extends SendGridDriver {
            public function __construct(string $key, private \stdClass $log) {
                parent::__construct($key);
            }
            protected function httpPost(string $url, string $body, array $headers): string {
                $this->log->requests[] = compact('url', 'body', 'headers');
                return '';
            }
        };

        return [$driver, $log];
    }

    public function test_posts_to_sendgrid_endpoint(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $this->assertStringContainsString('sendgrid.com', $log->requests[0]['url']);
    }

    public function test_bearer_token_is_in_header(): void
    {
        [$driver, $log] = $this->captureDriver('my-sg-key');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $this->assertStringContainsString('Bearer my-sg-key', implode(' ', $log->requests[0]['headers']));
    }

    public function test_body_is_valid_json_with_personalizations(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'user@example.com', toName: 'User', subject: 'Hi'));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertArrayHasKey('personalizations', $decoded);
        $this->assertSame('user@example.com', $decoded['personalizations'][0]['to'][0]['email']);
        $this->assertSame('User', $decoded['personalizations'][0]['to'][0]['name']);
    }

    public function test_cc_is_in_personalizations(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            cc: [['email' => 'cc@example.com', 'name' => 'CC']],
        ));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertSame('cc@example.com', $decoded['personalizations'][0]['cc'][0]['email']);
    }

    public function test_attachments_are_base64_encoded(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sg_att_');
        file_put_contents($tmpFile, 'data');

        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            attachments: [['path' => $tmpFile, 'name' => 'file.txt', 'mime' => 'text/plain']],
        ));

        unlink($tmpFile);
        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertSame(base64_encode('data'), $decoded['attachments'][0]['content']);
        $this->assertSame('file.txt', $decoded['attachments'][0]['filename']);
    }

    public function test_reply_to_is_set(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi', replyTo: 'reply@example.com'));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertSame('reply@example.com', $decoded['reply_to']['email']);
    }
}
