# flint-mail

Full-featured mail package for the [Flint framework](https://github.com/Vancil/flint). Mailable classes, async queueing, CC/BCC/attachments, and six sending drivers — all with zero new dependencies beyond Flint itself.

---

## Installation

```bash
composer require vancil/flint-mail
```

Register the package in `config/app.php`:

```php
'packages' => [
    \Vancil\FlintMail\FlintMail::class,
],
```

Run the installer to publish the config and migration:

```bash
php flint mail:install
php flint migrate
```

---

## Mailable Classes

Create a Mailable with the CLI:

```bash
php flint make:mail WelcomeEmail
```

This creates `app/Mail/WelcomeEmail.php`. Define your email inside `build()`:

```php
namespace App\Mail;

use Vancil\FlintMail\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(private User $user) {}

    public function build(): void
    {
        $this->to($this->user->email, $this->user->name)
             ->subject('Welcome to ' . config('app.name'))
             ->view('emails.welcome', ['user' => $this->user]);
    }
}
```

### Sending

```php
// Synchronous — sends immediately
(new WelcomeEmail($user))->send();

// Queued — dispatched to Flint's queue worker
(new WelcomeEmail($user))->queue();
(new WelcomeEmail($user))->queue('emails'); // named queue
```

### All Builder Methods

```php
$this->to('user@example.com', 'User')         // recipient
     ->from('sender@example.com', 'Sender')   // override default from
     ->replyTo('replies@example.com')
     ->subject('Hello')
     ->cc('manager@example.com', 'Manager')
     ->cc('another@example.com')
     ->bcc('audit@example.com')
     ->html('<p>Hello <b>world</b></p>')       // raw HTML body
     ->text('Hello world')                     // plain text fallback
     ->view('emails.welcome', ['user' => $u]) // Ember template as HTML
     ->attach('/path/to/file.pdf', 'Invoice.pdf', 'application/pdf');
```

---

## Fluent API (without Mailables)

You can also send ad-hoc emails via the injected `Mailer`:

```php
use Vancil\FlintMail\Mailer;

class NotificationController
{
    public function __construct(private readonly Mailer $mailer) {}

    public function notify(Request $request): Response
    {
        $this->mailer
            ->to('user@example.com', 'User')
            ->subject('New notification')
            ->html('<p>You have a new message.</p>')
            ->cc('admin@example.com')
            ->send();

        // Or queue it:
        $this->mailer
            ->to('user@example.com')
            ->subject('Report')
            ->attach('/tmp/report.pdf', 'Monthly Report.pdf')
            ->queue('reports');

        return Response::redirect('/');
    }
}
```

---

## Queued Mail

When `queue()` is called, flint-mail:

1. Renders the email body immediately
2. Persists a `QueuedMail` record to the database with `status = pending`
3. Dispatches a `SendMailJob` to Flint's queue system

Start the queue worker to process queued mail:

```bash
php flint queue:work
php flint queue:work --queue=emails   # process a named queue
```

The `queued_mails` table tracks each email's lifecycle:

| Column | Description |
|--------|-------------|
| `status` | `pending` → `processing` → `sent` or `failed` |
| `attempts` | How many send attempts have been made |
| `error` | Last error message if `failed` |
| `sent_at` | Timestamp when successfully delivered |

Failed jobs are retried up to 3 times (60 second delay between attempts).

---

## Drivers

Set `MAIL_DRIVER` in `.env` to choose your sending backend.

### Log (default)

Writes emails to `storage/logs/mail.log`. Use this locally.

```env
MAIL_DRIVER=log
```

### SMTP

Raw socket SMTP — works with any SMTP server (Gmail, Mailgun SMTP, SES SMTP, etc.).

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### Mailgun

```env
MAIL_DRIVER=mailgun
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_REGION=us    # or 'eu' for EU region
```

### Postmark

```env
MAIL_DRIVER=postmark
POSTMARK_TOKEN=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

### Amazon SES

```env
MAIL_DRIVER=ses
AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXXXXXXXX
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=us-east-1
```

SES requests are signed with AWS Signature Version 4, implemented inline with no external SDK.

### SendGrid

```env
MAIL_DRIVER=sendgrid
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## Configuration

`config/mail.php` (published by `php flint mail:install`):

```php
return [
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Flint'),
    ],

    'mailgun'  => ['key' => env('MAILGUN_SECRET'), 'domain' => env('MAILGUN_DOMAIN'), 'region' => 'us'],
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses'      => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'sendgrid' => ['key' => env('SENDGRID_API_KEY')],
];
```

---

## Email Templates

Email HTML bodies can be written as [Ember](https://github.com/Vancil/flint) templates:

`resources/views/emails/welcome.ember`:
```html
<!DOCTYPE html>
<html>
<body>
    <h1>Welcome, {{ $user->name }}!</h1>
    <p>Thanks for joining. Your account is ready.</p>
</body>
</html>
```

Then reference it in your Mailable:

```php
$this->view('emails.welcome', ['user' => $user]);
```

---

## Writing a Custom Driver

Implement `Vancil\FlintMail\Drivers\DriverInterface`:

```php
namespace App\Mail\Drivers;

use Vancil\FlintMail\Drivers\DriverInterface;
use Vancil\FlintMail\MailMessage;

class ResendDriver implements DriverInterface
{
    public function send(MailMessage $message): void
    {
        // $message->to, ->toName, ->subject, ->htmlBody, ->textBody
        // ->from, ->fromName, ->replyTo, ->cc, ->bcc, ->attachments
    }
}
```

Register it in `FlintMail::register()` by extending the package or binding it in your `Application::boot()`.

---

## License

MIT — [Vancil](https://github.com/Vancil)
