# finba.se

A flexible personal finance platform built for people who outgrew rigid finance apps.

## Status

🧪 Beta

The first beta version of Finba is now available.

Current development focuses on:

- stability
- UX refinement
- performance
- community feedback

---

## Why?

I’ve tried many finance apps over the years, but they always felt limiting.

Some were too rigid.
Some didn’t let me organize data the way I wanted.
Others completely ignored an important dimension: people.

I wanted to track:

- Categories and subcategories
- Who owes me
- Who I owe
- Purchases linked to people
- Shared household finances
- Recurring and installment transactions
- Financial organization that adapts to *my* workflow

So I built my own.

fun fact: the first version of this idea was built in Xamarin back in 2019 — and I actually used it for years before deciding to rebuild it properly.

---

## Current stack

Backend:
- PHP 8+
- Laravel
- Filament PHP

Database:
- PostgreSQL (Supabase)

File storage:
- Local disk in development (`FINBA_STORAGE_DISK=local`)
- Private Supabase Storage via S3-compatible disk `finba` in production (see `docs/supabase-storage.md`)

Production hosting:
- Google Cloud Run + FrankenPHP container
- Supabase PostgreSQL + Supabase Storage
- Resend email, Cloudflare DNS
- See `docs/deployment-gcp-cloud-run.md`

Mobile strategy:
- Web-first installable PWA
- Native packaging remains a future possibility

---

## Core principles

finba.se is built around a few ideas:

- Flexibility over rigid workflows
- Simple UX, powerful organization
- Productive, pragmatic engineering
- Build in public
- Open source by default

---

## Roadmap

**Completed highlights**
- Core financial organization: categories, people, transactions, monthly dashboard, accounts receivable, and installment purchases
- Personal features: tithes and first fruits, onboarding, and locale/location preferences
- Product foundations: responsive UI, installable PWA, public changelog, in-app feedback channel, About page, and email/Google login
- First public beta in production

**In progress**
- Beta stabilization based on user feedback
- Loans and debts, and recurring transactions

**Planned next**
- Transfers, budgeting, reminders and notifications, and a public landing page
- Shared finances for organizing money with other people

Created by **José Paulo Oliveira Filho**.

The detailed and continuously updated roadmap is available inside the Finba.se application.

---

## Why PHP?

Because building matters more than hype.

finba is intentionally built with a stack that optimizes delivery speed, maintainability, and developer experience.

---

## Open source

Licensed under the GNU AGPL v3.

This means:
- you can use it
- modify it
- self-host it

But if you modify it and offer it as a networked service, your changes must remain open as well.

---

## Build in public

I’m documenting the journey publicly on LinkedIn.

Follow along:
https://www.linkedin.com/in/jose-paulo-oliveira-filho/
