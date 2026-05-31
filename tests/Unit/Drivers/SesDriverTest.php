<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\SesDriver;
use Vancil\FlintMail\MailMessage;

class SesDriverTest extends TestCase
{
    private function captureDriver(string $key = 'AKIATEST', string $secret = 'secret', string $region = 'us-east-1'): array
    {
        $log = new \stdClass();
        $log->requests = [];

        $driver = new class($key, $secret, $region, $log) extends SesDriver {
            public function __construct(
                string $key, string $secret, string $region,
                private \stdClass $log,
            ) {
                parent::__construct($key, $secret, $region);
            }
            protected function httpPost(string $url, string $body, array $headers): string {
                $this->log->requests[] = compact('url', 'body', 'headers');
                return '{"MessageId":"abc"}';
            }
        };

        return [$driver, $log];
    }

    public function test_posts_to_ses_endpoint_with_correct_region(): void
    {
        [$driver, $log] = $this->captureDriver(region: 'eu-west-1');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $this->assertStringContainsString('eu-west-1', $log->requests[0]['url']);
        $this->assertStringContainsString('amazonaws.com', $log->requests[0]['url']);
    }

    public function test_authorization_header_uses_aws_sigv4(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $authHeader = $this->findHeader($log->requests[0]['headers'], 'Authorization');
        $this->assertStringContainsString('AWS4-HMAC-SHA256', $authHeader);
        $this->assertStringContainsString('AKIATEST', $authHeader);
    }

    public function test_x_amz_date_header_is_present(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $dateHeader = $this->findHeader($log->requests[0]['headers'], 'X-Amz-Date');
        $this->assertNotEmpty($dateHeader);
        $this->assertMatchesRegularExpression('/\d{8}T\d{6}Z/', $dateHeader);
    }

    public function test_body_is_valid_ses_json_structure(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to:       'user@example.com',
            toName:   'User',
            subject:  'Test',
            htmlBody: '<p>Hello</p>',
        ));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertArrayHasKey('Destination', $decoded);
        $this->assertArrayHasKey('Content', $decoded);
        $this->assertArrayHasKey('Simple', $decoded['Content']);
        $this->assertSame('Test', $decoded['Content']['Simple']['Subject']['Data']);
        $this->assertSame('<p>Hello</p>', $decoded['Content']['Simple']['Body']['Html']['Data']);
    }

    public function test_cc_is_in_destination(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            cc: [['email' => 'cc@example.com', 'name' => '']],
        ));

        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertContains('cc@example.com', $decoded['Destination']['CcAddresses']);
    }

    public function test_attachments_switch_to_raw_message_format(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ses_att_');
        file_put_contents($tmpFile, 'data');

        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            attachments: [['path' => $tmpFile, 'name' => 'file.pdf', 'mime' => 'application/pdf']],
        ));

        unlink($tmpFile);
        $decoded = json_decode($log->requests[0]['body'], true);
        $this->assertArrayHasKey('Raw', $decoded['Content']);
        $this->assertArrayNotHasKey('Simple', $decoded['Content']);
    }

    private function findHeader(array $headers, string $name): string
    {
        foreach ($headers as $h) {
            if (str_starts_with($h, $name . ':')) return $h;
        }
        return '';
    }
}
