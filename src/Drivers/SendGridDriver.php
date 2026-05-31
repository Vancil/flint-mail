<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class SendGridDriver implements DriverInterface
{
    private const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    public function __construct(private readonly string $key) {}

    public function send(MailMessage $message): void
    {
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');

        $personalization = [
            'to' => [['email' => $message->to, 'name' => $message->toName]],
        ];

        if (!empty($message->cc)) {
            $personalization['cc'] = $message->cc;
        }
        if (!empty($message->bcc)) {
            $personalization['bcc'] = $message->bcc;
        }

        $payload = [
            'personalizations' => [$personalization],
            'from'             => ['email' => $from, 'name' => $fromName],
            'subject'          => $message->subject,
            'content'          => [
                ['type' => 'text/plain', 'value' => $message->textBody ?: strip_tags($message->htmlBody)],
            ],
        ];

        if ($message->htmlBody !== '') {
            $payload['content'][] = ['type' => 'text/html', 'value' => $message->htmlBody];
        }

        if ($message->replyTo !== '') {
            $payload['reply_to'] = ['email' => $message->replyTo];
        }

        if (!empty($message->attachments)) {
            $payload['attachments'] = array_map(function (array $att): array {
                $name = $att['name'] ?: basename($att['path']);
                $mime = $att['mime'] ?: 'application/octet-stream';
                return [
                    'content'     => base64_encode(file_get_contents($att['path'])),
                    'type'        => $mime,
                    'filename'    => $name,
                    'disposition' => 'attachment',
                ];
            }, $message->attachments);
        }

        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [
            "Authorization: Bearer {$this->key}",
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ];

        $this->httpPost(self::ENDPOINT, $body, $headers);
    }

    protected function httpPost(string $url, string $body, array $headers): string
    {
        $context  = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents($url, false, $context);
        $this->assertSuccess($http_response_header ?? [], $response);
        return (string) $response;
    }

    private function assertSuccess(array $headers, string|false $body): void
    {
        $status = isset($headers[0]) ? (int) explode(' ', $headers[0])[1] : 0;
        if ($status < 200 || $status >= 300) {
            $error = is_string($body) ? ($body ?: 'unknown error') : 'unknown error';
            throw new \RuntimeException("SendGrid request failed ({$status}): {$error}");
        }
    }
}
