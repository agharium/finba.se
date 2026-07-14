# Changelog rules

Whenever a meaningful feature is completed, automatically update the changelog.

Source of truth: `resources/data/changelog.php`

## When to write an entry

Add a changelog entry when the user-visible product changed in a meaningful way.

Do:

- document completed product evolution
- keep chronological order
- group by date and domain

Do not:

- document trivial CSS tweaks
- document internal refactors as product news
- document service names, helpers, migrations, or queries
- create multiple changelog entries for the same feature across related commits

If multiple commits belong to the same feature, generate only one changelog entry.

## Writing style

- Keep chronological order.
- Use concise Portuguese.
- Use impersonal language.

Prefer expressions like:

- Foi adicionado...
- Foi implementado...
- Foi corrigido...
- Foi removido...
- Passou a...
- Agora...
- Também...
- Foi simplificado...
- Foi tomada a decisão de...

Vary the language naturally. Avoid repeating the same verb in every bullet.

## Content quality

The changelog should describe product evolution, not code refactors.

Avoid implementation details unless they are important architectural decisions.

Prefer:

- what the user can now do
- what behavior changed
- what decision was made for the product

Avoid:

- class names
- service names
- query rewrites
- “refactored X to Y”

## Editorial limits

Keep entries readable:

- at most 5 groups per date
- at most 4 bullets per group
- each bullet one or two lines

Preserve historical dates. Do not remove dates when compressing detail.

## Structure reminder

Entries live in `resources/data/changelog.php` as dated groups with typed items:

- `added`
- `changed`
- `fixed`
- `removed`
- `decision`
- `internal` sparingly; prefer product-facing types
