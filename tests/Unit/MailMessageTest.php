<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vancil\FlintMail\MailMessage;

class MailMessageTest extends TestCase
{
    public function test_inherits_base_fields(): void
    {
        $msg = new MailMessage(
            to:      'user@example.com',
            toName:  'User',
            subject: 'Hello',
        );

        $this->assertSame('user@example.com', $msg->to);
        $this->assertSame('User', $msg->toName);
        $this->assertSame('Hello', $msg->subject);
    }

    public function test_extended_fields_default_to_empty(): void
    {
        $msg = new MailMessage();

        $this->assertSame('', $msg->replyTo);
        $this->assertSame([], $msg->cc);
        $this->assertSame([], $msg->bcc);
        $this->assertSame([], $msg->attachments);
    }

    public function test_extended_fields_can_be_set(): void
    {
        $msg = new MailMessage(
            replyTo: 'reply@example.com',
            cc:      [['email' => 'cc@example.com', 'name' => 'CC']],
            bcc:     [['email' => 'bcc@example.com', 'name' => '']],
        );

        $this->assertSame('reply@example.com', $msg->replyTo);
        $this->assertCount(1, $msg->cc);
        $this->assertCount(1, $msg->bcc);
    }
}
