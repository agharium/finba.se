# Finba.se

A flexible personal finance platform for people who outgrew rigid finance apps.

## Status

**Beta** — version `0.1.0-beta` is live at [app.finba.se](https://app.finba.se).

Current development focuses on stability, UX refinement, performance, and community feedback.

## Why Finba.se?

Many finance apps feel limiting: too rigid, weak organization, or no concept of people. Finba.se is built to track categories and subcategories, who owes whom, purchases linked to people, shared household finances, recurring and installment transactions, and workflows that adapt to the user.

The first version of this idea was built in Xamarin in 2019 and used for years before the current rebuild.

## Stack

- PHP 8.4, Laravel 13, Filament 5
- PostgreSQL (Supabase)
- Private file storage: local disk in development; S3-compatible Supabase Storage in production
- Production hosting: Google Cloud Run + FrankenPHP, Resend email, Cloudflare DNS
- Web-first installable PWA (native packaging remains a future option)

## Local setup

Requirements: PHP 8.4+, Composer, Node.js, and PostgreSQL (or SQLite for quick local experiments).

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure database and other values in .env, then:
php artisan migrate

npm install
npm run build
# or npm run dev during frontend work

composer dev
# or: php artisan serve
```

Useful environment notes:

- `FINBA_STORAGE_DISK=local` for development
- Google OAuth and Resend are optional locally
- See `.env.example` for production reminders

## Testing

```bash
php artisan test --compact
```

Operational checks and the post-deploy smoke checklist live in [docs/testing.md](docs/testing.md).

## Documentation

| Document | Description |
| --- | --- |
| [docs/architecture.md](docs/architecture.md) | Domain model and system decisions |
| [docs/deployment.md](docs/deployment.md) | Cloud Run deployment and operations |
| [docs/storage.md](docs/storage.md) | Private file storage configuration |
| [docs/pwa.md](docs/pwa.md) | PWA assets, caching, and hosting |
| [docs/testing.md](docs/testing.md) | Automated tests and beta smoke checklist |

## Roadmap

**Completed highlights**

- Core financial organization: categories, people, transactions, monthly dashboard, accounts receivable, and installment purchases
- Personal features: tithes and first fruits, onboarding, and locale/location preferences
- Product foundations: responsive UI, installable PWA, public changelog, in-app feedback, About page, and email/Google login
- First public beta in production

**In progress**

- Beta stabilization based on user feedback
- Loans and debts, and recurring transactions

**Planned next**

- Transfers, budgeting, reminders and notifications, and a public landing page
- Shared finances for organizing money with other people

The detailed roadmap is also available inside the Finba.se application.

## Principles

- Flexibility over rigid workflows
- Simple UX with powerful organization
- Productive, pragmatic engineering
- Build in public
- Open source by default

## Why PHP?

Because shipping a maintainable product matters more than chasing hype. The stack prioritizes delivery speed, maintainability, and developer experience.

## License

Licensed under the GNU AGPL v3.

You may use, modify, and self-host Finba.se. If you modify it and offer it as a networked service, your changes must remain open as well.

## Author

Created by **José Paulo Oliveira Filho**.

Build-in-public updates: [LinkedIn](https://www.linkedin.com/in/jose-paulo-oliveira-filho/)
