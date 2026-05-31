<?php
declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Drivers\MailgunDriver;
use Vancil\FlintMail\MailMessage;

class MailgunDriverTest extends TestCase
{
    private function captureDriver(string $key = 'key', string $domain = 'mg.example.com', string $region = 'us'): array
    {
        $log = new \stdClass();
        $log->requests = [];

        $driver = new class($key, $domain, $region, $log) extends MailgunDriver {
            public function __construct(
                string $key, string $domain, string $region,
                private \stdClass $log,
            ) {
                parent::__construct($key, $domain, $region);
            }
            protected function httpPost(string $url, string $body, array $headers): string {
                $this->log->requests[] = compact('url', 'body', 'headers');
                return '{"id":"test","message":"Queued"}';
            }
        };

        return [$driver, $log];
    }

    public function test_uses_correct_us_endpoint(): void
    {
        [$driver, $log] = $this->captureDriver(domain: 'mg.test.com', region: 'us');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi', htmlBody: 'Body'));

        $this->assertStringContainsString('api.mailgun.net', $log->requests[0]['url']);
        $this->assertStringContainsString('mg.test.com', $log->requests[0]['url']);
    }

    public function test_uses_eu_endpoint_for_eu_region(): void
    {
        [$driver, $log] = $this->captureDriver(domain: 'mg.test.com', region: 'eu');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi', htmlBody: 'Body'));

        $this->assertStringContainsString('api.eu.mailgun.net', $log->requests[0]['url']);
    }

    public function test_authorization_header_uses_api_key(): void
    {
        [$driver, $log] = $this->captureDriver(key: 'my-secret-key');
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'Hi'));

        $authHeader = $this->findHeader($log->requests[0]['headers'], 'Authorization');
        $this->assertStringContainsString(base64_encode('api:my-secret-key'), $authHeader);
    }

    public function test_body_contains_to_address(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'user@example.com', subject: 'Hi'));

        $this->assertStringContainsString('user@example.com', $log->requests[0]['body']);
    }

    public function test_body_contains_subject(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(to: 'a@b.com', subject: 'My Subject'));

        $this->assertStringContainsString('My Subject', $log->requests[0]['body']);
    }

    public function test_body_contains_cc_addresses(): void
    {
        [$driver, $log] = $this->captureDriver();
        $driver->send(new MailMessage(
            to: 'a@b.com', subject: 'Hi',
            cc: [['email' => 'cc@example.com', 'name' => 'CC']],
        ));

        $this->assertStringContainsString('cc@example.com', $log->requests[0]['body']);
    }

    private function findHeader(array $headers, string $name): string
    {
        foreach ($headers as $h) {
            if (str_starts_with($h, $name . ':')) return $h;
        }
        return '';
    }
}
