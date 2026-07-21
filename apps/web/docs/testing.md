# Testing

## Automated tests

From `apps/web`:

```bash
composer test
# or
php artisan test --compact
```

Run a focused file or filter when working on a specific area:

```bash
php artisan test --compact tests/Feature/ExampleTest.php
php artisan test --compact --filter=testName
```

Useful operational checks:

```bash
php artisan finba:storage-check
php artisan finba:country-catalog-check
```

## Beta smoke test

Run after a production or staging deploy before inviting testers.

Environment under test:

- URL: ____________________
- Git SHA / APP_BUILD: ____________________
- Tester: ____________________
- Date: ____________________

Mark each item only after manual verification.

### Access and auth

- [ ] `GET /up` returns HTTP 200 without auth
- [ ] Register a new account
- [ ] Email verification link works (`APP_URL` host, HTTPS)
- [ ] Login with email/password
- [ ] Logout
- [ ] Login with Google OAuth
- [ ] Password reset email + flow

### Onboarding and profile

- [ ] First-access onboarding completes
- [ ] Profile locale/location preferences save
- [ ] Navigation reflects preference refresh if applicable

### Core finance flows

- [ ] Create category and subcategory
- [ ] Create person
- [ ] Create normal income
- [ ] Create normal expense
- [ ] Filters by period/category/person/city work
- [ ] Dashboard monthly totals update
- [ ] Create installment transaction; parcels appear (`n/m`)
- [ ] Create receivable sale
- [ ] Partial receivable payment
- [ ] Full receivable payment / closure
- [ ] Tithe / first fruits delivery recorded

### Feedback and storage

- [ ] Submit feedback with screenshot
- [ ] Object appears privately under `feedback/{uuid}/...` in Supabase Storage
- [ ] Database stores object path only (no signed/public URL)
- [ ] Feedback notification email arrives with attachment when configured
- [ ] Soft-deleted feedback does not immediately delete the object (if tested)

### Product surfaces

- [ ] Changelog renders
- [ ] Roadmap renders
- [ ] About page renders with expected public info

### PWA

- [ ] Install affordance appears where expected
- [ ] App launches in standalone display mode after install
- [ ] Service worker updates without sticky stale caches
- [ ] Offline fallback (`/offline.html`) appears when offline
- [ ] Offline does not allow financial mutations

### Mobile and security

- [ ] Mobile layout usable on a phone viewport
- [ ] No mixed-content warnings
- [ ] `APP_DEBUG=false` (no stack traces for normal errors)
- [ ] Session survives requests landing on different Cloud Run instances

### Sign-off

- [ ] Safe to invite limited beta testers
- [ ] Known defects recorded: ____________________
