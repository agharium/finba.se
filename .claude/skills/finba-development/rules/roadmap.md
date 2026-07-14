# Roadmap rules

The roadmap is the public vision of the product.

Source of truth: `resources/data/roadmap.php`

Status values:

- `completed`
- `in_progress`
- `planned`

## What may appear in the roadmap

- Never add speculative ideas.
- Never add brainstorming discussions.
- Never add "maybe" features.
- Only add features explicitly approved by the user.

## When a feature is completed

- Features only become COMPLETED when the entire user flow is functional.
- Backend/domain preparation alone does not complete a feature.
- UI alone does not complete a feature.
- Database alone does not complete a feature.

If the user can open the app and complete the flow end-to-end, mark it `completed`.

## When a feature is in progress

- If implementation has started but the feature is not usable end-to-end, mark it `in_progress`.
- Whenever a feature reaches end-to-end usability, automatically move its checkbox from In Progress to Completed.
- Whenever a new approved feature starts, automatically move it from Planned to In Progress.

## Keep states truthful

- Never leave roadmap states outdated.
- When finishing related product work, update the matching roadmap item in the same delivery.
- Prefer concise user-facing titles and short descriptions.
- Do not invent replacement features to fill category limits.
- Do not expand categories with speculative planned items.

## Editing guidance

Update statuses in `resources/data/roadmap.php`.

If README contains a high-level roadmap summary, update that summary only when the public story changed. See [documentation.md](documentation.md).

After changing roadmap counts or labels, update related tests when they assert those values.
