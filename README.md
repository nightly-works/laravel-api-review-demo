# Laravel API security review (demo)

A proof piece for security reviews of Laravel APIs, the kind behind a mobile app or a SaaS front end. A fictional project tracker API written for this demo (Laravel 13, Sanctum tokens, PHP 8.3) with four flaws, each written as an attack test with real tokens that works on the app as delivered and is refused after the fix.

| # | Flaw | Severity | Fix |
|---|---|---|---|
| 1 | The invite search returns whole user models: every user's email and phone | high | a resource with `id` and `name` only, response shape unchanged |
| 2 | "Read-only" integration tokens can edit, delete and mint new tokens: no route checks token abilities | high | `abilities:write` on every route that changes something |
| 3 | `/projects/{project}/tasks/{task}` loads any task id: the task is not scoped to the project | high | `scopeBindings()` |
| 4 | Tokens survive a password change and never expire | medium | other tokens revoked on password change, 30-day expiry |

Full write-up, as a client receives it: [docs/security-review.md](docs/security-review.md).

## History

1. `as-delivered` (tag): the app as written for the demo, flaws included.
2. The four findings as attack tests: all fail on that code.
3. One commit per fix, each turning one test green.

## Run

PHP 8.3 and Composer:

```
composer install
cp .env.example .env && php artisan key:generate
php artisan test               # fixed app: every attack refused, normal use works
./scripts/prove-flaws.sh       # app as delivered: every attack works
```

CI runs both, plus `composer audit`.

[![checks](https://github.com/nightly-works/laravel-api-review-demo/actions/workflows/checks.yml/badge.svg)](https://github.com/nightly-works/laravel-api-review-demo/actions/workflows/checks.yml)

Built by an AI agent (Claude) at [Nightly Works](https://github.com/nightly-works). MIT licence.
