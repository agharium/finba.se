---
name: finba-development
description: >-
  Permanent Finba.se product-development rules for keeping roadmap, changelog,
  PROJECT_CONTEXT, and README synchronized after every completed feature.
  Use whenever implementing, finishing, or reviewing Finba.se product features
  (transactions, installments, receivables, onboarding, Filament pages, domain
  services, feature flags, or any user-facing flow). Also use when the user
  mentions roadmap, changelog, PROJECT_CONTEXT, README sync, definition of
  done, or documentation updates. Complements laravel-best-practices; does not
  replace it. Always read rules/workflow.md before closing an implementation.
---

# Finba.se Development

Finba.se is a long-lived product. Every completed feature must keep the project documentation synchronized.

This skill encodes permanent product-development rules so future implementations do not drift from the public roadmap, changelog, or project context.

It complements `laravel-best-practices`. Use both:

- `laravel-best-practices` → how to write Laravel/Filament code
- `finba-development` → how to finish product work and keep documentation truthful

## Mandatory closing workflow

Before any implementation is considered complete, read and follow:

1. [rules/workflow.md](rules/workflow.md) — Definition of Done
2. [rules/roadmap.md](rules/roadmap.md) — public product vision updates
3. [rules/changelog.md](rules/changelog.md) — product history updates
4. [rules/documentation.md](rules/documentation.md) — PROJECT_CONTEXT / README sync

If no documentation update is necessary, explicitly say why.

## End-of-feature checklist

Run this checklist after every feature implementation:

- [ ] Does this complete a roadmap item? → move to completed if necessary
- [ ] Does this start a roadmap item? → move to in_progress if necessary
- [ ] Does this deserve a changelog entry? → add one
- [ ] Did PROJECT_CONTEXT become outdated? → update it
- [ ] Did README become inaccurate? → update it

## Required report section

Every implementation report must include:

```markdown
Documentation updated:

☑ Roadmap
☑ Changelog
☐ README
☑ PROJECT_CONTEXT
```

Use checked/unchecked marks that reflect what actually changed. For any unchecked file, explain why it did not require updates.

## Source of truth

| Document | Path | Role |
|---|---|---|
| Roadmap | `resources/data/roadmap.php` | Public product direction |
| Changelog | `resources/data/changelog.php` | Concise product history |
| Project context | `PROJECT_CONTEXT.md` | Domain/architecture reference |
| README | `README.md` | Public GitHub summary only |

Do not invent roadmap items. Do not document speculative ideas. Prefer product language over implementation details.
