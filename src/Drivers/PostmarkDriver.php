<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class PostmarkDriver implements DriverInterface
{
    private const ENDPOINT = 'https://api.postmarkapp.com/email';

    public function __construct(private readonly string $token) {}

    public function send(MailMessage $message): void
    {
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');

        $payload = [
            'From'     => $fromName ? "{$fromName} <{$from}>" : $from,
            'To'       => $message->toName ? "{$message->toName} <{$message->to}>" : $message->to,
            'Subject'  => $message->subject,
            'TextBody' => $message->textBody ?: strip_tags($message->htmlBody),
        ];

        if ($message->htmlBody !== '') {
            $payload['HtmlBody'] = $message->htmlBody;
        }
        if ($message->replyTo !== '') {
            $payload['ReplyTo'] = $message->replyTo;
        }
        if (!empty($message->cc)) {
            $payload['Cc'] = $this->formatAddressList($message->cc);
        }
        if (!empty($message->bcc)) {
            $payload['Bcc'] = $this->formatAddressList($message->bcc);
        }

        if (!empty($message->attachments)) {
            $payload['Attachments'] = array_map(function (array $att): array {
                $name = $att['name'] ?: basename($att['path']);
                $mime = $att['mime'] ?: 'application/octet-stream';
                return [
                    'Name'        => $name,
                    'Content'     => base64_encode(file_get_contents($att['path'])),
                    'ContentType' => $mime,
                ];
            }, $message->attachments);
        }

        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [
            "X-Postmark-Server-Token: {$this->token}",
            'Content-Type: application/json',
            'Accept: application/json',
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

    private function formatAddressList(array $addresses): string
    {
        return implode(', ', array_map(
            fn($a) => $a['name'] !== '' ? "{$a['name']} <{$a['email']}>" : $a['email'],
            $addresses
        ));
    }

    private function assertSuccess(array $headers, string|false $body): void
    {
        $status = isset($headers[0]) ? (int) explode(' ', $headers[0])[1] : 0;
        if ($status < 200 || $status >= 300) {
            $error = is_string($body) ? (json_decode($body, true)['Message'] ?? $body) : 'unknown error';
            throw new \RuntimeException("Postmark request failed ({$status}): {$error}");
        }
    }
}
