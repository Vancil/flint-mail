<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class MailgunDriver implements DriverInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $domain,
        private readonly string $region = 'us',
    ) {}

    public function send(MailMessage $message): void
    {
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');
        $boundary = '----FormBoundary' . bin2hex(random_bytes(8));

        $fields = [
            'from'    => $fromName ? "{$fromName} <{$from}>" : $from,
            'to'      => $message->toName ? "{$message->toName} <{$message->to}>" : $message->to,
            'subject' => $message->subject,
            'text'    => $message->textBody ?: strip_tags($message->htmlBody),
        ];

        if ($message->htmlBody !== '') {
            $fields['html'] = $message->htmlBody;
        }
        if ($message->replyTo !== '') {
            $fields['h:Reply-To'] = $message->replyTo;
        }
        if (!empty($message->cc)) {
            $fields['cc'] = $this->formatAddressList($message->cc);
        }
        if (!empty($message->bcc)) {
            $fields['bcc'] = $this->formatAddressList($message->bcc);
        }

        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }

        foreach ($message->attachments as $att) {
            $name    = $att['name'] ?: basename($att['path']);
            $content = file_get_contents($att['path']);
            $mime    = $att['mime'] ?: 'application/octet-stream';
            $body   .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"attachment\"; filename=\"{$name}\"\r\nContent-Type: {$mime}\r\n\r\n{$content}\r\n";
        }

        $body .= "--{$boundary}--";

        $host    = $this->region === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net';
        $url     = "https://{$host}/v3/{$this->domain}/messages";
        $headers = [
            'Authorization: Basic ' . base64_encode("api:{$this->key}"),
            "Content-Type: multipart/form-data; boundary={$boundary}",
            'Content-Length: ' . strlen($body),
        ];

        $this->httpPost($url, $body, $headers);
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
        $this->assertSuccess($http_response_header ?? [], $url, $response);
        return (string) $response;
    }

    private function formatAddressList(array $addresses): string
    {
        return implode(', ', array_map(
            fn($a) => $a['name'] !== '' ? "{$a['name']} <{$a['email']}>" : $a['email'],
            $addresses
        ));
    }

    private function assertSuccess(array $headers, string $url, string|false $body): void
    {
        $status = isset($headers[0]) ? (int) explode(' ', $headers[0])[1] : 0;
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Mailgun request to {$url} failed ({$status}): {$body}");
        }
    }
}
