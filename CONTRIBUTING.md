# Contributing

Thanks for helping improve Riwaq. This guide explains how to set up the project, the conventions to follow, and how to submit changes.

## Setup

Follow [README.md](README.md#getting-started). For tests, use a separate database so your working data is never touched:

```bash
DB_DATABASE=riwaq_test php artisan test --compact
```

## Workflow

1. Create a branch from `main`: `git checkout -b feature/short-description`.
2. Make focused changes. Keep one concern per pull request.
3. Add or update tests for behavior changes.
4. Format and test before pushing:

   ```bash
   vendor/bin/pint --dirty
   DB_DATABASE=riwaq_test php artisan test --compact
   ```

5. Open a pull request that explains what changed and why, and how you verified it.

## Conventions

**PHP / Laravel**
- Follow the existing structure and naming. Check neighbouring files before adding new ones.
- Use curly braces for all control structures, explicit return types, and constructor property promotion.
- Prefer named routes (`route('...')`) over hard-coded URLs.
- Use Eloquent factories and `php artisan make:` commands for new files.
- Do not add dependencies without discussion.

**User interface**
- The interface is Arabic and right-to-left. Write user-facing text in Arabic and keep layouts RTL-safe (use logical properties such as `inset-inline-start`).
- Reuse the existing components and classes in `public/css/riwaq-ui.css` before writing new styles.
- Legacy rules in `public/css/riwaq.css` still apply globally. If a new element picks up unexpected styles, scope your selector or reset the property.
- Do not ship fake or demo data to real users. Empty states should be honest.

**JavaScript**
- Vanilla JavaScript, no build step required for the main UI. Keep scripts small and feature-focused (`public/js/`).
- Wrap `localStorage` access in `try/catch`.

**Security**
- Never commit `.env`, API keys, database dumps, archives, or log files.
- Authorize every state-changing action server-side. Do not rely on hidden buttons.
- Anything meant only for development must be guarded by `app()->environment(['local', 'testing'])`.

## Tests

- Feature tests live in `tests/Feature/`. Most tests use `DatabaseTransactions`, so each test cleans up after itself.
- Mock outbound HTTP with `Http::fake()`. Tests must never call real external services.
- Test the behavior you changed and its important failure cases.

## Commit messages

Use short, imperative subject lines (for example, `Add ISBN lookup to the add-book form`), with details in the body when needed.

## Reporting bugs and ideas

Open a GitHub issue with steps to reproduce, expected and actual behavior, and screenshots where helpful. For security issues, see [SECURITY.md](SECURITY.md) instead.
