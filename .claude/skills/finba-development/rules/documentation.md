# Documentation synchronization

Whenever implementation changes documentation-relevant product knowledge, keep project docs synchronized.

## PROJECT_CONTEXT.md

Review `PROJECT_CONTEXT.md` whenever implementation changes:

- domain rules
- architecture
- services
- models
- important business rules
- onboarding
- feature flags

Update it only when the information became outdated.

Do not rewrite the whole file for every feature.
Change only the sections that are now wrong or incomplete.

Typical update candidates:

- new domain entities and relationships
- changed payment / installment / receivable rules
- onboarding and preference behavior
- advanced-mode and feature-flag gates
- service ownership of business rules

## README.md

`README.md` should only be updated when:

- installation changes
- architecture changes
- public capabilities change
- roadmap changes
- project status changes

README must NOT be updated for every feature.

Keep README in English unless the project explicitly decides otherwise.

Prefer concise public summaries. Do not copy every roadmap bullet into README.

When roadmap high-level themes change, update the README roadmap summary and keep the note that the detailed roadmap lives inside the Finba.se application.

## Synchronization priority

1. Keep `resources/data/roadmap.php` truthful to product state.
2. Keep `resources/data/changelog.php` current for meaningful completed work.
3. Keep `PROJECT_CONTEXT.md` accurate for domain and architecture.
4. Touch `README.md` only when the public GitHub story would otherwise be wrong.

## Report requirement

In the final implementation report, always include:

```markdown
Documentation updated:

☑ Roadmap
☑ Changelog
☐ README
☑ PROJECT_CONTEXT
```

Unchecked items need a one-line reason.

Example:

```markdown
Documentation updated:

☑ Roadmap — Parcelamentos moved to completed
☑ Changelog — added 2026-07-14 installment entry
☐ README — public summary still accurate
☑ PROJECT_CONTEXT — installment generation rules documented
```
