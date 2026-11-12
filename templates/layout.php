<?php
use PlanetAlerts\App;
use PlanetAlerts\I18n;
?>
<!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title === App::name() ? $title : $title . ' – ' . App::name()) ?></title>
  <meta name="description" content="<?= e(t('meta.description')) ?>">
  <meta name="color-scheme" content="dark light">
  <meta name="theme-color" content="#0B1130" media="(prefers-color-scheme: dark)">
  <meta name="theme-color" content="#F4F5FA" media="(prefers-color-scheme: light)">
  <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
  <link rel="preload" href="<?= e(asset('fonts/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= e(asset('fonts/space-grotesk-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
  <script src="<?= e(asset('night.js')) ?>"></script>
  <script src="<?= e(asset('app.js')) ?>" defer></script>
  <?php if (\PlanetAlerts\Matomo::enabled()): ?>
  <script src="<?= e(asset('stats.js')) ?>" data-url="<?= e(\PlanetAlerts\Matomo::url()) ?>" data-site="<?= \PlanetAlerts\Matomo::siteId() ?>" defer></script>
  <?php endif; ?>
</head>
<body>
  <a class="skip" href="#main"><?= e(t('nav.skip')) ?></a>
  <?php if ($banner = \PlanetAlerts\Banner::current()): ?>
    <div class="site-banner banner-<?= e($banner['style']) ?>" role="note">
      <p><?= e($banner['text']) ?><?php if ($banner['url']): ?>
        <a href="<?= e($banner['url']) ?>"><?= e($banner['link']) ?></a><?php endif; ?></p>
    </div>
  <?php endif; ?>
  <header class="site-header">
    <a class="brand" href="/"><svg viewBox="0 0 64 64" aria-hidden="true"><circle class="logo-ring" cx="32" cy="32" r="22"/><path class="logo-ring" d="M32 3v8M32 53v8M3 32h8M53 32h8"/><circle class="logo-planet" cx="32" cy="32" r="10"/><ellipse class="logo-orbit" cx="32" cy="32" rx="17" ry="5" transform="rotate(-20 32 32)"/></svg><?= e(App::name()) ?></a>
    <nav aria-label="<?= e(t('nav.label')) ?>">
      <?php if ($user): ?>
        <a href="/alerts"><?= e(t('nav.dashboard')) ?></a>
        <a href="/account"><?= e(t('nav.account')) ?></a>
        <form method="post" action="/logout" class="inline"><?= csrf_field() ?><button type="submit" class="link"><?= e(t('nav.logout')) ?></button></form>
      <?php else: ?>
        <a href="/login"><?= e(t('nav.login')) ?></a>
        <a class="nav-cta" href="/signup"><?= e(t('nav.signup')) ?></a>
      <?php endif; ?>
      <form method="get" class="lang-switch" data-lang-switch>
        <?php foreach ($_GET as $k => $v): if ($k !== 'lang' && is_string($v)): ?><input type="hidden" name="<?= e((string) $k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
        <label for="lang-switch"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg><span class="sr-only"><?= e(t('lang.label')) ?></span></label>
        <select id="lang-switch" name="lang">
          <?php foreach (I18n::languages() as $code => $name): ?>
            <option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= I18n::lang() === $code ? 'selected' : '' ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="small" data-lang-apply><?= e(t('lang.apply')) ?></button>
      </form>
      <button type="button" class="night-toggle" data-night-toggle aria-pressed="false" aria-label="<?= e(t('nav.night')) ?>" title="<?= e(t('nav.night')) ?>" hidden>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg></button>
    </nav>
  </header>

  <main id="main">
    <?php foreach ($flash as $f): ?>
      <p class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></p>
    <?php endforeach; ?>
    <?= $content ?>
  </main>

  <footer class="site-footer">
    <p class="footer-links">
      <a href="/privacy"><?= e(t('nav.privacy')) ?></a>
      <a href="<?= e(t('nav.imprint_url')) ?>"><?= e(t('nav.imprint')) ?></a>
      <a href="/api"><?= e(t('nav.api')) ?></a>
      <a href="/status"><?= e(t('nav.status')) ?></a>
      <a href="<?= e((string) App::config('app.source_url', 'https://github.com/compi2k/skyhint-web')) ?>"><?= e(t('nav.source')) ?></a>
    </p>
    <p class="credits"><?= t('footer.credits') ?></p>
    <?php if (App::config('app.show_version', true)): ?>
      <p class="version"><a href="<?= e(rtrim((string) App::config('app.source_url', 'https://github.com/compi2k/skyhint-web'), '/') . '/blob/main/CHANGELOG.md') ?>"
        title="<?= e(t('footer.changelog')) ?>">Skyhint.app <?= e(App::version()) ?></a></p>
    <?php endif; ?>
  </footer>
</body>
</html>
