# Riwaq Al-Ma'rifa (رِواق المعرفة)

An Arabic-first social reading and library platform. Readers keep personal and shared libraries, log their reading, join reading clubs and challenges, schedule meetups, and lend physical books to each other. The interface is right-to-left and written in Arabic.

> **Repository:** https://github.com/iLAWprojects/riwaq

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Demo accounts](#demo-accounts)
- [Running the tests](#running-the-tests)
- [Scheduled commands](#scheduled-commands)
- [Project structure](#project-structure)
- [Further documentation](#further-documentation)
- [License](#license)

## Features

**Libraries and lending**
- Personal library (always private) plus any number of shared, club, family, or public libraries.
- Each book can have several physical copies (barcode, condition, shelf location, passport notes).
- Lending with requests, approvals, extensions, due-date notifications, condition reports, and waitlists.
- Library settings: rename, privacy (private/public), loan duration, renewals, member roles, ownership transfer, and permanent deletion.

**Discovering books**
- Public catalog with live (AJAX) filtering by title, author, publisher, and category.
- Book pages with reviews, reflections, and availability.
- Fast book picker that searches the whole catalog as you type.
- **ISBN lookup** that fills in book details automatically (Open Library, Google Books, WorldCat, Amazon). See [ISBN_LOOKUP.md](ISBN_LOOKUP.md).

**Reading log**
- Reading sessions with a floating timer, current-book switching, and shelves (reading, want to read, completed, on hold, did not finish).
- Streaks, annual goals, points and levels, badges, and a Knowledge Diversity Index (KDI) computed from the books you actually read.
- **Anti-gaming pace rule:** a session is only counted when the time is plausible for the pages read (about two minutes per page, with a 25% tolerance). Implausible sessions are saved but marked as unverified and do not affect challenges, points, streaks, or goals.
- Book filter that applies across every tab of the reading log.

**Reading clubs and challenges**
- Reading clubs with members and roles, discussions, polls, book of the month, announcements, and invitations.
- Challenges for individuals or teams, public or club-only, for any book or one specific book. Creators join automatically, can finish a challenge, and are notified when readers join or complete it.
- Followers are notified about new public challenges; club members are notified about club challenges.

**Calendar and meetups**
- Meetings (in person, remote, hybrid, and more) with RSVP, waitlists, check-in, minutes, ratings, ICS export, and Google Calendar links.
- The calendar only shows meetings from clubs you belong to plus public meetings.

**Social**
- Follow readers, send friend requests, profiles with badges.
- Smart notification panel, global search, and in-app reporting of books, discussions, comments, reviews, reflections, and users.

**Administration**
- Admin dashboard with moderation of reports, user management, system settings, audit log, and optional MFA for administrators.

**Interface**
- Public landing page with an animated book hero, a dashboard shell with a sidebar for signed-in users, and a mobile bottom navigation bar.
- Right-to-left layout throughout.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Database | MySQL (SQLite is the default in `.env.example`) |
| Frontend | Blade templates, vanilla JavaScript, custom CSS (`public/css/riwaq-ui.css`), Lucide icons |
| Build tooling | Vite and Tailwind are installed; the main UI uses the static assets in `public/` |
| Testing | PHPUnit 12 |
| Code style | Laravel Pint |

## Getting started

### Requirements

- PHP 8.3 or newer with the usual Laravel extensions (`mbstring`, `openssl`, `pdo`, `pdo_mysql`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`)
- Composer 2
- MySQL 8 (or MariaDB 10.6+)
- Node.js 20+ and npm (only if you want to rebuild Vite assets)

### Installation

```bash
git clone https://github.com/iLAWprojects/riwaq.git
cd riwaq

composer install
cp .env.example .env
php artisan key:generate
```

Create a database, then set the connection in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=riwaq
DB_USERNAME=root
DB_PASSWORD=
```

Run the migrations and the seeders, then start the server:

```bash
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000.

To rebuild front-end assets (optional):

```bash
npm install
npm run build
```

A convenience script is also available: `composer run setup`.

## Configuration

Copy `.env.example` to `.env` and adjust. The most important settings:

| Variable | Purpose |
|---|---|
| `APP_ENV` | `local` for development, `production` on the live server |
| `APP_DEBUG` | `true` locally, **always `false` in production** |
| `APP_URL` | Public URL of the site |
| `DB_*` | Database connection |
| `ISBN_LOOKUP_PROVIDERS` | Order of ISBN providers (default `openlibrary,googlebooks,worldcat,amazon`) |
| `GOOGLE_BOOKS_API_KEY` | Optional but strongly recommended for Google Books |
| `WORLDCAT_CLIENT_ID`, `WORLDCAT_CLIENT_SECRET` | WorldCat Search API credentials (optional) |
| `AMAZON_PAAPI_*` | Amazon Product Advertising API credentials (optional) |

Never commit your `.env` file. It is already listed in `.gitignore`.

## Demo accounts

Demo accounts and the quick-login routes exist **only when `APP_ENV` is `local` or `testing`**. In any other environment they are disabled and return 404.

| Role | Email | Password |
|---|---|---|
| Reader | `demo@riwaq.test` | `password` |
| Administrator | `admin@riwaq.test` | `password` |

In local development the login page also shows quick-login buttons for both accounts.

## Running the tests

```bash
php artisan test --compact
```

Important notes:

- The suite uses `DatabaseTransactions` and expects the demo seed data to exist. **Run it against a dedicated test database**, not your working data:

  ```bash
  DB_DATABASE=riwaq_test php artisan test --compact
  ```

  Create and migrate/seed that database first.
- Some older view tests assert specific seeded content (for example particular challenge or community names). If you start from different seed data, a few of them may need adjusting.
- Format code before committing:

  ```bash
  vendor/bin/pint --dirty
  ```

## Scheduled commands

The scheduler runs `riwaq:check-due-loans` daily to send reminders before and after loan due dates. Add the standard Laravel cron entry on the server:

```cron
* * * * * cd /path/to/riwaq && php artisan schedule:run >> /dev/null 2>&1
```

Other maintenance command:

- `php artisan riwaq:deduplicate-books` merges duplicate catalog entries and relinks copies and reading records.

## Project structure

```text
app/
  Console/Commands/        Scheduled and maintenance commands
  Http/Controllers/        Web controllers (Admin/ and Api/ sub-folders)
  Models/                  Eloquent models
  Policies/                Authorization policies (libraries, communities, meetings, users)
  Services/                Anti-gaming, MFA, notifications, QR codes
  Services/BookLookup/     ISBN lookup providers and service
database/
  migrations/              Schema
  seeders/                 Demo and gamification data
public/
  css/riwaq-ui.css         Main UI styles
  js/                      riwaq-ui.js, riwaq-reading.js, riwaq-hero.js, riwaq-bookpicker.js
resources/views/
  layouts/app.blade.php    Public and dashboard shells
  pages/                   Page templates and partials
routes/
  web.php                  Web routes
  api.php                  Read-only API (v1)
tests/Feature/             Feature tests
```

## Further documentation

- [DEPLOYMENT.md](DEPLOYMENT.md): deploying to a production server, including shared hosting.
- [ISBN_LOOKUP.md](ISBN_LOOKUP.md): how ISBN lookup works and how to get API keys.
- [SECURITY.md](SECURITY.md): reporting vulnerabilities and hardening checklist.
- [CONTRIBUTING.md](CONTRIBUTING.md): development workflow and conventions.

## License

MIT, as declared in `composer.json`.
