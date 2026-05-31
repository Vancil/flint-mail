<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Drivers;

use Vancil\FlintMail\MailMessage;

class SesDriver implements DriverInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $region = 'us-east-1',
    ) {}

    public function send(MailMessage $message): void
    {
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');

        $payload = [
            'FromEmailAddress' => $fromName ? "{$fromName} <{$from}>" : $from,
            'Destination'      => [
                'ToAddresses' => [$message->toName ? "{$message->toName} <{$message->to}>" : $message->to],
            ],
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $message->subject, 'Charset' => 'UTF-8'],
                    'Body'    => [
                        'Text' => ['Data' => $message->textBody ?: strip_tags($message->htmlBody), 'Charset' => 'UTF-8'],
                    ],
                ],
            ],
        ];

        if ($message->htmlBody !== '') {
            $payload['Content']['Simple']['Body']['Html'] = ['Data' => $message->htmlBody, 'Charset' => 'UTF-8'];
        }

        if ($message->replyTo !== '') {
            $payload['ReplyToAddresses'] = [$message->replyTo];
        }

        if (!empty($message->cc)) {
            $payload['Destination']['CcAddresses'] = array_map(
                fn($a) => $a['name'] ? "{$a['name']} <{$a['email']}>" : $a['email'],
                $message->cc
            );
        }

        if (!empty($message->bcc)) {
            $payload['Destination']['BccAddresses'] = array_map(
                fn($a) => $a['name'] ? "{$a['name']} <{$a['email']}>" : $a['email'],
                $message->bcc
            );
        }

        // Attachments require the Raw message format in SES
        if (!empty($message->attachments)) {
            unset($payload['Content']['Simple']);
            $payload['Content']['Raw'] = ['Data' => base64_encode($this->buildRawMime($message, $from, $fromName))];
        }

        $endpoint = "https://email.{$this->region}.amazonaws.com/v2/email/outbound-emails";
        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers  = $this->signRequest('POST', $endpoint, $body);

        $this->httpPost($endpoint, $body, $headers);
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

    private function buildRawMime(MailMessage $message, string $from, string $fromName): string
    {
        $boundary = '----SESBoundary' . bin2hex(random_bytes(8));
        $to       = $message->toName ? "{$message->toName} <{$message->to}>" : $message->to;

        $raw  = "From: {$fromName} <{$from}>\r\n";
        $raw .= "To: {$to}\r\n";
        $raw .= "Subject: {$message->subject}\r\n";
        $raw .= "MIME-Version: 1.0\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

        $raw .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        $raw .= ($message->textBody ?: strip_tags($message->htmlBody)) . "\r\n";

        if ($message->htmlBody !== '') {
            $raw .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
            $raw .= $message->htmlBody . "\r\n";
        }

        foreach ($message->attachments as $att) {
            $name    = $att['name'] ?: basename($att['path']);
            $mime    = $att['mime'] ?: 'application/octet-stream';
            $content = chunk_split(base64_encode(file_get_contents($att['path'])));
            $raw    .= "--{$boundary}\r\n";
            $raw    .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $raw    .= "Content-Transfer-Encoding: base64\r\n";
            $raw    .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $raw    .= $content . "\r\n";
        }

        $raw .= "--{$boundary}--";
        return $raw;
    }

    /** Build AWS SigV4 signed headers for the request. Returns array of header strings. */
    private function signRequest(string $method, string $url, string $body): array
    {
        $parsed    = parse_url($url);
        $host      = $parsed['host'];
        $path      = $parsed['path'] ?? '/';
        $service   = 'ses';
        $amzDate   = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $bodyHash  = hash('sha256', $body);

        $canonicalHeaders = "content-type:application/json\nhost:{$host}\nx-amz-date:{$amzDate}\n";
        $signedHeaders    = 'content-type;host;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $bodyHash,
        ]);

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($dateStamp);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            "Host: {$host}",
            'Content-Type: application/json',
            "X-Amz-Date: {$amzDate}",
            "Authorization: {$authorization}",
            'Content-Length: ' . strlen($body),
        ];
    }

    private function deriveSigningKey(string $dateStamp): string
    {
        $kDate    = hash_hmac('sha256', $dateStamp,       'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region,    $kDate,                 true);
        $kService = hash_hmac('sha256', 'ses',             $kRegion,               true);
        return     hash_hmac('sha256', 'aws4_request',    $kService,              true);
    }

    private function assertSuccess(array $headers, string|false $body): void
    {
        $status = isset($headers[0]) ? (int) explode(' ', $headers[0])[1] : 0;
        if ($status < 200 || $status >= 300) {
            $error = is_string($body) ? (json_decode($body, true)['message'] ?? $body) : 'unknown error';
            throw new \RuntimeException("SES request failed ({$status}): {$error}");
        }
    }
}
