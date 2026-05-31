<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\PostmarkDriver;
use Vancil\FlintMail\MailMessage;

class PostmarkDriverTest extends TestCase
{
    private function captureDriver(string $token = 'test-token'): array
    {
        $log = new \stdClass();
        $log->requests = [];

        $driver = new class($token, $log) extends PostmarkDriver {
            public function __construct(string $token, private \stdClass $log) {
                parent::__construct($token);
            }
            protected function httpPost(string $url, string $body, array $headers): string {
                $this->log->requests[] = compact('url', 'body', 'headers');
                return '{"MessageID":"abc"}';
            }
        };

        return [$driver, $log];
    }

    public function test_posts_to_postmark_endpoint(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $this->assertStringContainsString('postmarkapp.com', $log->requests[0]['url']);
    }

    public function test_token_is_in_header(): void
    {
        [$driver, $log] = $this->captureDriver('my-postmark-token');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $this->assertStringContainsString('my-postmark-token', implode('', $log->requests[0]['headers']));
    }

    public function test_body_is_valid_json(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi', htmlBody: '<p>Hello</p>'));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('HtmlBody', $decoded);
        $this->assertSame('<p>Hello</p>', $decoded['HtmlBody']);
    }

    public function test_cc_is_included_in_payload(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            cc: [['email' => 'cc@example.com', 'name' => 'CC']],
        ));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertStringContainsString('cc@example.com', $decoded['Cc']);
    }

    public function test_attachments_are_base64_encoded(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pm_att_');
        file_put_contents($tmpFile, 'hello');

        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            attachments: [['path' => $tmpFile, 'name' => 'test.txt', 'mime' => 'text/plain']],
        ));

        unlink($tmpFile);
        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertSame(base64_encode('hello'), $decoded['Attachments'][0]['Content']);
    }
}
