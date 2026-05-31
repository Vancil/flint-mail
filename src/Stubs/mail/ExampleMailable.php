<?php
declare(strict_types=1);

namespace App\Mail;

use Vancil\FlintMail\Mailable;

class ExampleMailable extends Mailable
{
    public function __construct(
        // Inject any data the email needs
    ) {}

    public function build(): void
    {
        $this->to('recipient@example.com')
             ->subject('Hello from ' . config('app.name', 'Flint'))
             ->view('emails.example');
    }
}
