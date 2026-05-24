# Contributing

Thanks for your interest in Laravel Plenum.

## Bugs and feature requests

Please [open an issue](../../issues) to report bugs or suggest features. Include enough detail to reproduce: PHP and Laravel versions, your `plenum` config, and the smallest example that exhibits the problem.

## Pull requests

PRs are welcome but not guaranteed to be merged. Plenum is pre-1.0 and the API is still settling, so changes that don't fit the current direction may be declined or deferred — please open an issue first to discuss anything non-trivial.

If you do send a PR:

- Keep it focused. One concern per PR.
- Match the existing code style. `composer test` should pass, and CI runs PHPStan and PHP-CS-Fixer.
- Add or update tests for behaviour changes.
- Write a clear description of what changed and why.

## Dashboard assets

The dashboard's CSS is built from `resources/css/plenum.css` using the Tailwind v4 standalone CLI. The compiled artifact lives at `dist/plenum.css` and is committed to the repository — end users get the prebuilt file, no `npm install` required.

If you change anything in `resources/views/` or `resources/css/`, rebuild before opening a PR:

```bash
npm install      # one-time
npm run build    # produces dist/plenum.css
```

`npm run watch` rebuilds on save during development.

## Security

Do **not** open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the disclosure process.
