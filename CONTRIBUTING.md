# Contributing to flint-mail

Thanks for your interest in contributing. Please read this before opening a PR.

---

## Philosophy

flint-mail extends Flint's built-in mail foundation with Mailable classes, async queueing, and HTTP API drivers. It should stay true to Flint's core principles:

- **No magic.** No facades or hidden state. `Mailable::send()` and `queue()` should be easy to follow in a debugger.
- **No new dependencies.** All HTTP drivers use PHP's built-in `file_get_contents` and `stream_context_create`. No curl, no Guzzle, no third-party HTTP clients.
- **Fail loudly.** Driver errors throw descriptive exceptions. No silent fallbacks or swallowed responses.
- **PHP 8.1+ only.** Use typed properties, constructor promotion, match expressions, and readonly where they add clarity.

---

## What Belongs Here

- New sending drivers (any service reachable via HTTP POST with no new PHP extension)
- Improvements to existing drivers (better attachment handling, edge cases, etc.)
- New `Mailable` / `PendingMail` builder methods
- Improvements to the `mail:install` command or the `make:mail` stub
- Bug fixes in `SendMailJob` or `QueuedMail`

Changes that require new Composer dependencies, modify the Flint core, or add non-mail concerns do not belong here.

---

## Reporting Bugs

Open a GitHub issue and include:

1. PHP version, OS, and the mail driver you're using
2. The minimal code to reproduce the problem
3. What you expected vs. what happened (include the full exception and stack trace)
4. If a driver issue: the raw HTTP response from the provider if available

---

## Suggesting Features

Open a GitHub issue before writing code. For new drivers, describe the provider's API, authentication method, and any PHP extension requirements (none preferred).

---

## Submitting a Pull Request

1. Fork the repo and create a branch from `master`.
2. Keep PRs small and focused — one driver or one feature per PR.
3. Follow the code style of the surrounding files (`declare(strict_types=1)`, no explanatory comments, constructor promotion).
4. Run the test suite before submitting:
   ```bash
   composer install
   vendor/bin/phpunit
   ```
5. New drivers must include tests. Use the `protected httpPost()` hook to capture requests without making real network calls — see the existing driver tests for the pattern.
6. Write a clear PR description explaining *why* the change is needed.

---

## Adding a New Driver

1. Create `src/Drivers/YourProviderDriver.php` implementing `Vancil\FlintMail\Drivers\DriverInterface`.
2. Override the `protected httpPost(string $url, string $body, array $headers): string` method — this is what tests intercept.
3. Add the driver to the `match` in `FlintMail::buildMailer()`.
4. Add the config keys to `src/Stubs/config/mail.php`.
5. Add the env keys to `MailInstall::writeEnvDefaults()`.
6. Write tests in `tests/Unit/Drivers/YourProviderDriverTest.php`.

---

## Code Style

- `declare(strict_types=1)` at the top of every file
- No inline comments explaining what the code does — name things well instead
- Prefer constructor property promotion over manual assignment
- Prefer `match` over `switch`
- No `else` after a `return` or `throw`

---

## License

By contributing you agree that your code will be released under the [MIT License](LICENSE).
