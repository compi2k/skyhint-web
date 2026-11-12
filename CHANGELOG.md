# Changelog

What changed in each version of Skyhint.app (web). The calc server has its own
changelog in [skyhint-calc](https://github.com/compi2k/skyhint-calc).

Version numbers: the last digit goes up for fixes, the middle one for new
features, the first one for changes that need manual work when updating.
After every update, run `php bin/setup.php`.

## 1.2.3 (2026-11-12)

- Version number in the footer, on the status page, in `status.json` and in
  the output of `setup.php`. Settings `app.show_version` and
  `app.version_label` in config.php.
- Fix: a new account, or one that moved its mail time to an earlier slot, was
  reported as "skipped" on its first day and turned the status page yellow.
  Only days with a failed send attempt count as skipped now.

## 1.2.2 (2026-11-11)

- Sky view: an "Auto" button shows whether the view follows the planets and
  turns following back on after you turned by hand. It also flips less
  often when one planet sets as another rises.
- On phones "Facing" sits small above the direction, so the row keeps its
  height.
- `storage/logs` is created automatically if it is missing; setup explains
  why it is not writable.

## 1.2.1 (2026-11-11)

- Night panel: no more jumping while sliding through the night, smaller sky
  view and a more compact layout.

## 1.2.0 (2026-11-11)

- Redesign "night instrument": dark look with a light variant, new logo,
  self-hosted fonts, at-a-glance summary of the night, expandable planet
  cards.
- Sky chart: the whole sky as a round map, besides the horizon view.
- Red night-vision mode, switchable in the header.
- New mail layout.

## 1.1.1 (2026-11-10)

- Privacy page: legal basis and retention for each item, all rights,
  objection notice, hosting and mail logs, processors and supervisory
  authority from config.php.
- config.php saved in another encoding than UTF-8 no longer breaks umlauts;
  setup warns about it.
- Real logout after 24 hours without activity.

## 1.1.0 (2026-11-10)

- Choose when mails arrive: morning, midday, afternoon or evening, in your
  own time zone.
- Mails announce the next night that has not started yet, so places in other
  time zones are no longer reported too late. Morning events say "tomorrow
  morning".
- Optional visitor statistics with Matomo (cookieless, opt-out on the
  privacy page).
- Optional banner at the top of every page, set in config.php.
- Legal notice: optional phone number and free-text field.

## 1.0.0 (2026-11-09)

- First release.
