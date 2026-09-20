# Architecture

Finba.se is a personal finance application built with Laravel and Filament. It is online-first, mobile-friendly, and installable as a Progressive Web App.

In the monorepo this application lives at **`apps/web`**.

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

Related operational docs: [deployment.md](deployment.md), [storage.md](storage.md), [pwa.md](pwa.md), [geo.md](geo.md).

## External Geo catalog

The Go Geo service (`apps/geo`) is the **sole geographic source of truth**. Laravel consumes it via `App\Support\Geo` (`Geo` facade).

- Domain tables store only `geo_city_id` as an external integer (users, transactions, people)
- There is no local `cities` table, geographic FK, or Sushi country/region catalog
- Forms use `GeoFields` (`geo_country_code` / `geo_region_id` form state; persist `geo_city_id`)
- Labels use cached `Geo::city()` / `GeoPresenter` — never hidden model accessors that trigger HTTP

See [geo.md](geo.md) for contract, cache TTLs, availability rules, and timezone boundary (`users.timezone`).


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

Borrowed or lent money (`LENT`, `BORROWED`) and accounts receivable (`RECEIVABLE`).

Domain invariant: a `Transaction` only exists when money actually moved.

- Originating a `LENT` loan creates an `EXPENSE` transaction.
- Originating a `BORROWED` loan creates an `INCOME` transaction.
- `RECEIVABLE` sales create a loan without an origin transaction; payments create `INCOME` transactions later.
- Remaining loan balance is always derived from actual repayment transactions — never from expectations alone.

### Commitments

A `Commitment` is an expected financial event (mutable). It replaced the earlier `Reminder` model.

Typical loan flow:

`Loan` → `Commitment` (expected) ↔ allocation ↔ `Transaction` (actual)

- Loan repayment schedules create Commitments, not Transactions.
- Commitments and Transactions are linked through `commitment_transaction` allocations (many-to-many with an allocation amount).
- Partial payments, overpayments, and multiple transactions per commitment are supported.
- Birthday / anniversary notifications are **not** Commitments; they belong on `Person` (e.g. `birth_date`) as follow-up work.

`recurring_transaction_id` remains on Commitments for the planned recurring confirm-pay flow.

### Recurring transactions

Standing expectations such as salary, rent, or utilities.

- Amount modes: `FIXED`, `VARIABLE`
- Variable suggestions should use the average of the last three payments.

Planned payment flow:

`RecurringTransaction` → `Commitment` → confirm pay action → create `Transaction` → update `next_occurrence_at` and commitment state.

The pay control must open a confirmation flow; it must not create a transaction directly.

### Installment purchases

Credit-card style purchases (`InstallmentGroup`) are a separate domain from loan repayment schedules. Installment groups create Transactions for each installment because those purchases are treated as recorded obligations in the existing product flow — do not merge them with loan Commitments.

## Production concerns

### Authentication and URLs

- Google OAuth routes (`/auth/google/*`) are excluded from Filament SPA navigation so the browser performs a full redirect.
- Production must set `APP_URL=https://app.finba.se` and an absolute `GOOGLE_REDIRECT_URL`.
- Leave `ASSET_URL` unset unless assets are intentionally served from another origin.
- Panel logos use relative paths so they follow the public host.
- Laravel trusts forwarded proxy headers so scheme/host/port resolve to the public URL.

### Currency map

`resources/data/country-currencies.php` maps ISO country codes to currencies for `MoneyFormatter`. It is not a geographic catalog.

### Feedback and transparency

Authenticated feedback lives under the Projeto navigation group together with Changelog, Roadmap, and About. Changelog navigation points at the canonical public page [`/changelog`](https://app.finba.se/changelog); see [changelog.md](changelog.md).

- Feedback records store optional private attachment object paths, never public URLs.
- Build metadata comes from `config/finba.php` / `App\Support\ApplicationBuild`.
- Feedback email delivery uses `FINBA_FEEDBACK_EMAIL` when configured.
- Automatic exception monitoring (Sentry) remains deferred.

## Current product focus

1. Beta stabilization (bugs, UX, performance, feedback)
2. Loans and debts (Commitment allocation UX / “did they pay?” flows)
3. Recurring transactions
4. Commitment notifications
5. Person birthday notifications (from `Person.birth_date`, not Commitments)
6. Automatic error monitoring
