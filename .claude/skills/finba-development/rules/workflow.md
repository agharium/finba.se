# Definition of Done

Before any Finba.se implementation is considered complete, review documentation synchronization.

An implementation is only complete after this review.

## Mandatory review order

1. Roadmap → `resources/data/roadmap.php` and [roadmap.md](roadmap.md)
2. Changelog → `resources/data/changelog.php` and [changelog.md](changelog.md)
3. Project context → `PROJECT_CONTEXT.md` and [documentation.md](documentation.md)
4. README → `README.md` only when necessary (see [documentation.md](documentation.md))

## Closing procedure

After code and tests are done:

1. Decide whether the work starts, advances, or completes a roadmap item.
2. Decide whether the work deserves a public changelog entry.
3. Decide whether PROJECT_CONTEXT now contains outdated domain or architecture information.
4. Decide whether README public capabilities, status, or roadmap summary became inaccurate.
5. Apply only the necessary updates.
6. Include the documentation report section in the final response.

## Mental checklist

At the end of EVERY feature implementation, execute:

- [ ] Does this complete a roadmap item?
      → move checkbox/status if necessary.
- [ ] Does this start a roadmap item?
      → move checkbox/status if necessary.
- [ ] Does this deserve a changelog entry?
      → add one.
- [ ] Did PROJECT_CONTEXT become outdated?
      → update it.
- [ ] Did README become inaccurate?
      → update it.

## Explicit no-update rule

If no documentation update is necessary, explicitly mention why.

Examples:

- Backend-only prep did not finish a user flow → roadmap stays in_progress; no changelog yet.
- Internal refactor with no product behavior change → no changelog, no roadmap move.
- README public summary remains accurate → leave README untouched.

## Incomplete work is not done

Do not close a feature task while documentation is outdated, even if code and tests already pass.

Documentation sync is part of the Definition of Done, not an optional follow-up.
