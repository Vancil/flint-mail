<?php
declare(strict_types=1);

namespace Vancil\FlintMail\Commands;

use Flint\Console\Command;

class MakeMail extends Command
{
    public function signature(): string
    {
        return 'make:mail';
    }

    public function description(): string
    {
        return 'Create a new Mailable class';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php flint make:mail <ClassName>');
            exit(1);
        }

        $mailDir = $this->app->basePath . '/app/Mail';
        if (!is_dir($mailDir)) {
            mkdir($mailDir, 0755, true);
        }

        $dest = "{$mailDir}/{$name}.php";

        if (file_exists($dest)) {
            $this->warn("Mailable already exists: app/Mail/{$name}.php");
            return;
        }

        $stub = file_get_contents(dirname(__DIR__) . '/Stubs/mail/ExampleMailable.php');
        $stub = str_replace('ExampleMailable', $name, $stub);
        file_put_contents($dest, $stub);

        $this->info("Mailable created: app/Mail/{$name}.php");
    }
}
