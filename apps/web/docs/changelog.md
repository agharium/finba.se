# Changelog

Finba maintains a single changelog source of truth and one canonical presentation.

## Source of truth

Entries live in:

```text
apps/web/resources/data/changelog.php
```

The public page reads this file through `App\Services\ChangelogService`. Do not duplicate entries into a second array or markdown document.

## Canonical URL

| Surface | URL | Auth |
|---------|-----|------|
| Changelog | https://app.finba.se/changelog (`route('changelog')`) | None required |

Authenticated users still reach the same URL from the Filament “Changelog” navigation item (and from the release banner / About links). That nav entry does **not** register a separate Filament route — it points at the public page.

Signed-in visitors receive the full feed (including `authenticated` / `internal` visibility). Guests receive only `public` entries.

## Featured milestones

Optional entry metadata:

```php
'featured' => true,
'featured_label' => 'Marco arquitetural',
'featured_summary' => 'Short lede shown under the title.',
```

Featured days receive the `finba-changelog-day--featured` treatment and a `data-featured="true"` marker. The Geo platform extraction day (`2026-07-19`) is the current featured milestone.

## Visibility

Optional metadata on an **entry** or **item**:

```php
'visibility' => 'public',        // default when omitted
'visibility' => 'authenticated', // signed-in visitors only
'visibility' => 'internal',      // signed-in visitors only
```

Enum: `App\Enums\ChangelogVisibility`.

Rules:

- Guests: only `public` entries/items.
- Authenticated visitors on `/changelog`: all audiences.
- Item `type` (`added`, `changed`, `fixed`, `removed`, `decision`, `internal`) is independent of visibility. A `type => internal` item may still be `visibility => public` when the text is safe to publish.

## Maintaining entries

1. Append a new day block at the top of `changelog.php` (newest first; the service also sorts by date).
2. Prefer grouped themes over long flat bullet lists.
3. Mark architectural milestones with `featured` metadata rather than hardcoding styles in the data file.
4. Set `visibility` explicitly when an item must stay off the public guest feed.
