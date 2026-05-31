<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Commands;

use Flint\Console\Command;

class MailInstall extends Command
{
    private string $stubsPath;

    public function signature(): string
    {
        return 'mail:install';
    }

    public function description(): string
    {
        return 'Publish the flint-mail config and migration';
    }

    public function handle(array $args): void
    {
        $this->stubsPath = dirname(__DIR__) . '/Stubs';
        $basePath        = $this->app->basePath;

        $this->publishConfig($basePath);
        $this->publishMigration($basePath);
        $this->writeEnvDefaults($basePath);
        $this->printNextSteps();
    }

    private function publishConfig(string $basePath): void
    {
        $configDir  = $basePath . '/config';
        $configFile = $configDir . '/mail.php';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        if (file_exists($configFile)) {
            $this->warn('config/mail.php already exists — skipping.');
            return;
        }

        copy($this->stubsPath . '/config/mail.php', $configFile);
        $this->info('Config published: config/mail.php');
    }

    private function publishMigration(string $basePath): void
    {
        $destDir = $basePath . '/database/migrations';

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $existing = glob($destDir . '/*_create_queued_mails_table.php');
        if (!empty($existing)) {
            $this->warn('Migration create_queued_mails_table already exists — skipping.');
            return;
        }

        $timestamp = date('Y_m_d_His');
        $dest      = "{$destDir}/{$timestamp}_create_queued_mails_table.php";
        copy($this->stubsPath . '/migrations/create_queued_mails_table.php', $dest);
        $this->info('Migration published: database/migrations/' . basename($dest));
    }

    private function writeEnvDefaults(string $basePath): void
    {
        $envFile = $basePath . '/.env';

        if (!file_exists($envFile)) {
            return;
        }

        $env = file_get_contents($envFile);
        $defaults = [
            'MAIL_DRIVER'       => 'log',
            'MAIL_FROM_ADDRESS' => 'hello@example.com',
            'MAIL_FROM_NAME'    => 'Flint',
            'MAILGUN_SECRET'    => '',
            'MAILGUN_DOMAIN'    => '',
            'POSTMARK_TOKEN'    => '',
            'SENDGRID_API_KEY'  => '',
            'AWS_ACCESS_KEY_ID' => '',
            'AWS_SECRET_ACCESS_KEY' => '',
            'AWS_DEFAULT_REGION'    => 'us-east-1',
        ];

        $appended = false;
        foreach ($defaults as $key => $value) {
            if (!str_contains($env, $key . '=')) {
                $env     .= "{$key}={$value}\n";
                $appended = true;
            }
        }

        if ($appended) {
            file_put_contents($envFile, $env);
            $this->info('.env defaults written (MAIL_*, MAILGUN_*, POSTMARK_*, SENDGRID_*, AWS_*)');
        }
    }

    private function printNextSteps(): void
    {
        $this->line('');
        $this->info('flint-mail installed!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('  1. Add FlintMail to config/app.php packages:');
        $this->line('        \\Vancil\\FlintMail\\FlintMail::class,');
        $this->line('  2. Run migrations:  php flint migrate');
        $this->line('  3. Create a Mailable: php flint make:mail WelcomeEmail');
        $this->line('  4. Set MAIL_DRIVER in .env (log|smtp|mailgun|postmark|ses|sendgrid)');
    }
}
