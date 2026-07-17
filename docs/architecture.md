# Architecture

Finba.se is a personal finance application built with Laravel and Filament. It is online-first, mobile-friendly, and installable as a Progressive Web App.

| Item | Value |
| --- | --- |
| Current version | `0.1.0-beta` |
| Stage | Beta |
| Production host | `https://app.finba.se` |
| Primary locales | `pt-BR`, `en` |

## Stack

- PHP 8.4, Laravel 13, Filament 5
- PostgreSQL (Supabase)
- Google Cloud Run (`southamerica-east1`) with FrankenPHP
- Cloudflare DNS / proxy in front of the public host
- Resend for email
- Private file storage via Laravel disk `finba` (S3-compatible Supabase Storage) in production; `local` in development

Cloud Run containers are ephemeral. Sessions and cache use the `database` driver. Production queues use `sync` for the beta. Migrations run through the Cloud Run Job `finba-migrate`, never on web instance boot. Application logs go to stderr. Health check: `GET /up`.

Related operational docs: [deployment.md](deployment.md), [storage.md](storage.md), [pwa.md](pwa.md).

## Design principles

- Prefer flexible organization over rigid finance workflows.
- Keep advanced features behind user preference flags (`is_advanced` and related settings).
- Keep business rules in models and services, not in Filament resources.
- Prefer enums over ad-hoc strings.
- Prefer reusable UI building blocks (for example `MoneyInput`).
- Domain decisions come before interface convenience.

## Domain model

### Categories

Organize income and expense activity.

- Parent/child hierarchy
- Types: `INCOME`, `EXPENSE` (a category may allow both)
- Optional purpose: `TITHE`, `OFFERING`
- Purpose requires the category to allow `EXPENSE`; removing expense clears purpose

### People

People represent companies, individuals, institutions, churches, banks, and clients.

- Name
- Types (`INCOME` / `EXPENSE`)
- Optional links to categories

### Category ↔ person

Many-to-many through `category_person` (`user_id`, `category_id`, `person_id`).

Only parent categories are linked directly to people. Subcategories inherit the parent relationship.

### Transactions

Real money movements.

Important fields include amount, type, status, category, person, loan, installment group, installment number, and recurring transaction.

- Types: `INCOME`, `EXPENSE`
- Status: `PENDING`, `PAID`
- Purpose on a transaction means an actual delivery (for example an expense with `purpose = TITHE` records a tithe payment)

### Installment groups

An installment plan is owned by `InstallmentGroup`. Each installment is a real `Transaction` linked by `installment_group_id`, with numbering such as `3/12`, monthly due dates, and cent-accurate amount distribution.

Deferred for later: bulk edit of all installments, cancel future installments, early payoff, interest, and credit-card statement flows.

### Tithe calculations

`tithe_calculations` stores period windows and calculated amounts for tithes, offerings, and first fruits.

- Tithes and offerings are calculated from eligible income.
- First fruits use `days_in_year / 12` (365 or 366).

### Loans

Borrowed or lent money (`LENT`, `BORROWED`). Product work for a full loans/debts experience continues after the beta launch.

### Recurring transactions

Standing commitments such as salary, rent, or utilities.

- Amount modes: `FIXED`, `VARIABLE`
- Variable suggestions should use the average of the last three payments.

Planned payment flow:

`RecurringTransaction` → `Reminder` → confirm pay action → create `Transaction` → update `next_occurrence_at` and reminder state.

The pay control must open a confirmation flow; it must not create a transaction directly.

### Reminders

Reminder types include anniversary, loan, commitment, and custom events. Delivery channels and offset schedules are part of the longer-term product plan.

## Production concerns

### Authentication and URLs

- Google OAuth routes (`/auth/google/*`) are excluded from Filament SPA navigation so the browser performs a full redirect.
- Production must set `APP_URL=https://app.finba.se` and an absolute `GOOGLE_REDIRECT_URL`.
- Leave `ASSET_URL` unset unless assets are intentionally served from another origin.
- Panel logos use relative paths so they follow the public host.
- Laravel trusts forwarded proxy headers so scheme/host/port resolve to the public URL.

### Country catalog

`App\Models\Country` is a Sushi-backed catalog loaded from `resources/data/country-region-data.json`, with currency overrides in `resources/data/country-currencies.php`.

- There is no PostgreSQL `countries` table.
- File-based Sushi caching is disabled; the model uses in-memory SQLite per process.
- Diagnostic command: `php artisan finba:country-catalog-check`

### Feedback and transparency

Authenticated feedback lives under the Projeto navigation group together with Changelog, Roadmap, and About.

- Feedback records store optional private attachment object paths, never public URLs.
- Build metadata comes from `config/finba.php` / `App\Support\ApplicationBuild`.
- Feedback email delivery uses `FINBA_FEEDBACK_EMAIL` when configured.
- Automatic exception monitoring (Sentry) remains deferred.

## Current product focus

1. Beta stabilization (bugs, UX, performance, feedback)
2. Loans and debts
3. Recurring transactions
4. Reminders and notifications
5. Automatic error monitoring
