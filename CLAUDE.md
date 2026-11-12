# Developer notes for Skyhint.app (web)

Start here when you pick up work on this repository, whether you are a
person or an AI assistant. `README.md` is for people installing Skyhint;
this file is for people changing the code. It covers how things fit together,
the decisions behind them and how to test.

## The project in one paragraph

Skyhint.app sends free e-mail alerts when planets are worth looking at from
a place you choose. It has two parts, in two repositories, both AGPL-3.0:

- **skyhint-web** (this repo): plain PHP 8.3+, MariaDB, PHPMailer. Pages,
  accounts, places, mail job, public API, status page.
- **skyhint-calc**: a small Python/FastAPI service that does all the
  astronomy (Astronomy Engine) and the city search (GeoNames). The web app
  talks to it over HTTPS with a shared secret, and caches its answers in the
  database (`calc_cache`).

## How the owner works

- The owner is not a full-time developer. Explain changes in plain words,
  without jargon. Answer in the language the owner writes in (German or
  English).
- **Deploying:** the owner gets a zip of this repo, copies it over the GitHub
  Desktop clone and pushes, then uploads the same files to the web server,
  as the site user, never as root. Then they run `php bin/setup.php` as the
  site user.
- **The zip must never contain** `config/config.php` (it holds the owner's
  personal legal-notice data), `.git/`, `storage/logs/*.log` or
  `storage/*.lock`. It must contain the empty `storage/logs/.gitkeep`.
- `config/config.php` on the server is never overwritten. New settings
  therefore need a sensible default in the code (`App::config('x', default)`),
  an entry in `config/config.sample.php` and a note to the owner.
- Run `bin/setup.php` after every update. It migrates the database and
  checks the installation; a new requirement belongs in there as a check.

## Versions

- The number is in `VERSION` (it shows in the footer, on the status page, in
  `status.json` and in setup output). It is never set in config.php.
- Last digit for fixes, middle digit for new features, first digit only for
  changes that need manual work when updating.
- Every release gets an entry in `CHANGELOG.md`, in plain words a site owner
  understands.
- Changes to docs or tests only do not need a new version.

## Rules that hold everywhere

- **No build step, no Composer, no framework.** PHPMailer is vendored in
  `vendor/phpmailer`. Fonts are self-hosted in `public/assets/fonts`. Keep it
  this way: fewer moving parts is the point.
- **Strict CSP:** `script-src 'self'`, no inline scripts, no inline styles,
  no `style=""` attributes. The only exception is the Matomo origin, and only
  when Matomo is enabled. Data for scripts goes into
  `<script type="application/json">` blocks.
- **Two languages, always both.** Every text lives in `lang/de.php` and
  `lang/en.php` with the same keys and the same `{placeholders}`;
  `php bin/check-lang.php` checks that. German texts use real umlauts and
  typographic quotes („…“).
- **Privacy:** store only what the privacy page lists. If you store or send
  anything new, update `templates/privacy.php` and its texts at the same
  time. No cookies besides the session and language; small preferences
  (night-red mode, stats opt-out) go into localStorage and are listed on the
  privacy page.
- **Escape all output** with `e()`. Only the language strings that
  deliberately contain links are printed raw.
- `sql/schema.sql` is split on `;` by setup.php, so **never write a `;`
  inside an SQL comment**. Schema changes also need a migration step in
  `bin/setup.php`, because existing installations are upgraded there.

## How the main parts work

### Mail job (`bin/send-alerts.php` → `src/AlertJob.php`)

- Cron runs it every 15 minutes. `bin/cleanup.php` runs once a day.
- Each account picks a mail slot (`MailTime::SLOTS`: morning, midday,
  afternoon, evening) in its own time zone (`users.mail_tz`, otherwise that
  of its first place). The exact minute inside the slot is spread per user
  (`MailTime::dueMinute`, crc32 of the user id), so mails don't all go out at
  once.
- One row in `mail_runs` per user, local day and kind (alerts or digest):
  `done`, `failed` (retried on the next run) or `skipped` (still failing
  after `MailTime::RETRY_HOURS`). Only a real failure becomes `skipped`; a
  day whose slot had already passed before the first attempt (new account,
  slot moved earlier) is recorded as `done` with nothing sent.
- **Which night is announced:** `Alerts::nightsToAnnounce()` picks the next
  night whose darkness has not started yet in that place's time zone. If
  the previous night was missed (`alerts.last_night` is older), it is added
  too. `sent_log` prevents announcing the same event twice. When an event
  happens in the morning sky, the mail says "tomorrow morning" and shows the
  next day's date.
- The status mail to the owner and the city of the week
  (`WeekCity::prepare`) are also done by this job. The start page only reads
  the city of the week from the cache, so a slow calc server never slows
  the start page.

### Status page (`src/Controllers/Status.php`)

- Cached for one minute. Yellow ("degraded") when the job is late or a day
  was skipped in the last 24 hours. Red when calc is down.
- `status.json` is meant for monitoring tools; don't rename its fields.

### Pages and design

- Look "night instrument": dark by default, light variant via
  `prefers-color-scheme`, the night panel always dark. Colours are tokens at
  the top of `public/assets/style.css`.
- Red night-vision mode: `public/assets/night.js` (loaded without `defer`,
  so the page never flashes white) sets `html[data-night-red]`.
- `public/assets/sky.js` draws the sky view. It has two modes, horizon and
  chart (polar map with south at the bottom, flipped south of the equator).
  In horizon mode the view follows the planets ("Auto") until the user turns
  by hand.
- The night panel must not change height while sliding through the night.
  The owner noticed and disliked that, so test it with long texts and on a
  320 px wide phone.

### Optional features driven by config.php

These are `banner` (text at the top of each page), `matomo` (cookieless
statistics with opt-out), `privacy.*` (processors, log retention,
supervisory authority), `imprint.*` (legal notice, optional phone and extra
text), and `app.show_version` / `app.version_label`. Config values may arrive
as Windows-1252; `App::utf8()` fixes that, and setup warns.

## Local test setup

The setup uses MariaDB, the calc server from the sibling repo with its test
cities, PHP's built-in server and a tiny mail catcher.

```bash
# database (once)
sudo service mariadb start
sudo mysql -e "CREATE DATABASE planet_alerts CHARACTER SET utf8mb4;
  CREATE USER 'pa'@'localhost' IDENTIFIED BY 'pa-test';
  GRANT ALL ON planet_alerts.* TO 'pa'@'localhost';"

# calc server, from a checkout of skyhint-calc
CALC_SECRET=s3cret CALC_CITIES_FILE=tests/fixtures/cities_sample.txt \
  CALC_ADMIN1_FILE=tests/fixtures/admin1_sample.txt \
  python3 -m uvicorn app.main:app --port 8100 &

# web app, from this repo
cp tests/config.test.php config/config.php
php bin/setup.php            # two FAILs are expected locally: http instead of https, and possibly the calc check
php -S localhost:8080 -t public bin/dev-router.php &

# mail catcher
pip install aiosmtpd requests
mkdir -p /tmp/mail && python3 tests/smtp_capture.py /tmp/mail &
```

These background processes stop when the machine sleeps or restarts; start
them again as above. If a port is "already in use", an old copy is still
running: stop it with `pkill -f smtp_capture` (or `uvicorn`, `php -S`).

The test cities have fixed ids: 100001 Tuttlingen, 100002 Berlin,
100003 München, 100004 Tromsø (white nights), 100005 Sydney (southern
hemisphere), 100006 Berlin (New Hampshire, same name, other country).

**Before handing over a change:**

1. `php bin/check-lang.php`
2. `php -l` on changed PHP files
3. The end-to-end test, on a **fresh** database:
   ```bash
   sudo mysql -e "DROP DATABASE planet_alerts; CREATE DATABASE planet_alerts CHARACTER SET utf8mb4;"
   php bin/setup.php > /dev/null
   python3 tests/e2e.py /tmp/mail      # must end with FAILURES: 0
   ```
4. For the mail job, run it at a pretend time without waiting for cron:
   `php bin/send-alerts.php --now="2026-11-15 15:05" [--dry-run]` (UTC).
5. For anything visible, check screenshots with Playwright: desktop
   (1280 px), phone (375 and 320 px), dark and light, red mode, German and
   English.

## Where things are

| Part | Where |
| --- | --- |
| Routes | `public/index.php` |
| Controllers | `src/Controllers/` |
| Places per account | `src/Alerts.php` |
| Mail job, mail times | `src/AlertJob.php`, `src/MailTime.php`, `bin/send-alerts.php` |
| Mail content | `src/Emails.php`, `templates/emails/` |
| Calc client and cache | `src/Calc.php` |
| Sky data for the night panel | `src/SkyTrack.php`, `public/assets/sky.js` |
| Sentences, dates, numbers | `src/Format.php`, `lang/*.php` |
| Event types and defaults | `src/Prefs.php` |
| Session, idle logout | `src/Session.php` |
| Security headers, CSP | `src/Http.php` |
| Schema and migrations | `sql/schema.sql`, `bin/setup.php` |
| Templates, styles, scripts | `templates/`, `public/assets/` |
| Logs | `storage/logs/app.log`, `storage/logs/php-error.log` |

## Ideas that were discussed but not built

- A manual dark/light switch in the header (now it follows the system).
- German names for regions (e.g. "Bayern" instead of "Bavaria"; GeoNames
  admin1 names are English).
- GitHub Actions that test against new PHP and Python versions, plus
  Dependabot for PHPMailer and the Python packages.
- An optional `docker-compose.yml` for self-hosters and for trying new PHP
  versions locally. It is not meant for the owner's own servers, which get
  automatic security updates without Docker.
