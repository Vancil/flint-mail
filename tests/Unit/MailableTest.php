<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\Mailable;
use Vancil\FlintMail\MailMessage;

class MailableTest extends TestCase
{
    private function makeMailable(callable $build): Mailable
    {
        return new class($build) extends Mailable {
            public function __construct(private readonly \Closure $buildFn) {}
            public function build(): void { ($this->buildFn)($this); }
        };
    }

    public function test_to_sets_recipient(): void
    {
        $m = $this->makeMailable(fn($m) => $m->to('a@example.com', 'Alice')->subject('Hi'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('a@example.com', $msg->to);
        $this->assertSame('Alice', $msg->toName);
    }

    public function test_subject_is_set(): void
    {
        $m = $this->makeMailable(fn($m) => $m->to('a@example.com')->subject('Test subject'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('Test subject', $msg->subject);
    }

    public function test_html_body_is_set(): void
    {
        $m = $this->makeMailable(fn($m) => $m->to('a@example.com')->subject('Hi')->html('<p>Hello</p>'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('<p>Hello</p>', $msg->htmlBody);
    }

    public function test_text_body_is_set(): void
    {
        $m = $this->makeMailable(fn($m) => $m->to('a@example.com')->subject('Hi')->text('Hello'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('Hello', $msg->textBody);
    }

    public function test_reply_to_is_set(): void
    {
        $m = $this->makeMailable(fn($m) => $m->to('a@example.com')->subject('Hi')->replyTo('r@example.com'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('r@example.com', $msg->replyTo);
    }

    public function test_cc_appends_recipients(): void
    {
        $m = $this->makeMailable(fn($m) => $m
            ->to('a@example.com')
            ->subject('Hi')
            ->cc('b@example.com', 'Bob')
            ->cc('c@example.com'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertCount(2, $msg->cc);
        $this->assertSame('b@example.com', $msg->cc[0]['email']);
        $this->assertSame('Bob', $msg->cc[0]['name']);
        $this->assertSame('c@example.com', $msg->cc[1]['email']);
    }

    public function test_bcc_appends_recipients(): void
    {
        $m = $this->makeMailable(fn($m) => $m
            ->to('a@example.com')
            ->subject('Hi')
            ->bcc('d@example.com', 'Dave'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertCount(1, $msg->bcc);
        $this->assertSame('d@example.com', $msg->bcc[0]['email']);
    }

    public function test_attach_adds_attachment(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mail_test_');
        file_put_contents($tmpFile, 'content');

        $m = $this->makeMailable(fn($m) => $m
            ->to('a@example.com')
            ->subject('Hi')
            ->attach($tmpFile, 'doc.txt', 'text/plain'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertCount(1, $msg->attachments);
        $this->assertSame($tmpFile, $msg->attachments[0]['path']);
        $this->assertSame('doc.txt', $msg->attachments[0]['name']);
        $this->assertSame('text/plain', $msg->attachments[0]['mime']);

        unlink($tmpFile);
    }

    public function test_from_overrides_defaults(): void
    {
        $m = $this->makeMailable(fn($m) => $m
            ->to('a@example.com')
            ->from('custom@sender.com', 'Custom')
            ->subject('Hi'));
        $m->build();

        $msg = $this->extractMessage($m);
        $this->assertSame('custom@sender.com', $msg->from);
        $this->assertSame('Custom', $msg->fromName);
    }

    /** Use reflection to call the private toMessage() method for inspection. */
    private function extractMessage(Mailable $mailable): MailMessage
    {
        $ref = new \ReflectionMethod($mailable, 'toMessage');
        return $ref->invoke($mailable);
    }
}
