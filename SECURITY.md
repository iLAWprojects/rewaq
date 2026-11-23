# Security Policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report privately through GitHub: open the repository's **Security** tab and choose **Report a vulnerability** (private security advisories). Include:

- A description of the issue and its impact
- Steps to reproduce (URLs, requests, accounts involved)
- Any suggested fix

You will get an acknowledgement as soon as possible. Please give us reasonable time to fix the issue before disclosing it publicly.

## Supported versions

Only the latest commit on the `main` branch is supported with security fixes.

## Security-relevant design notes

- **Demo access is development-only.** Demo accounts, automatic demo-account creation on login, the quick-login routes (`/auth/quick-login/*`, `/auth/switch/*`), and the unauthenticated API fallback only work when `APP_ENV` is `local` or `testing`. In every other environment the quick-login routes return 404 and the API requires authentication.
- **Authorization** is enforced with policies for libraries, communities, meetings, and users, and with ownership checks on challenges and library settings.
- **Administrators** can enable multi-factor authentication. Sensitive administrative actions are written to an audit log.
- **Reading integrity:** reading sessions with an implausible pace are stored as unverified and ignored by challenges, points, streaks, and goals.
- **External cover images** from ISBN lookups are only accepted from an allow-list of trusted hosts.
- **Rate limiting** protects the ISBN lookup endpoint (30 requests per minute).

## Hardening checklist for operators

See the pre-launch checklist in [DEPLOYMENT.md](DEPLOYMENT.md#7-pre-launch-security-checklist). The essentials:

1. Serve only the `public/` folder.
2. `APP_DEBUG=false` and `APP_ENV=production`.
3. Keep `.env`, archives, SQL dumps, and logs out of the web root and out of Git.
4. Use unique secrets per environment, and rotate any secret that was exposed.
5. Use HTTPS.
