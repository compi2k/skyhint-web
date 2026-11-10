<?php
use PlanetAlerts\App;
use PlanetAlerts\I18n;
use PlanetAlerts\Matomo;

$imprint = App::config('imprint', []);
$cfg = App::config('privacy', []);
$email = (string) ($imprint['email'] ?? '');
$stats = Matomo::enabled();
// localized config value: one string, or one per language
$loc = static fn ($v) => is_array($v) ? (string) ($v[I18n::lang()] ?? $v['en'] ?? reset($v) ?: '') : (string) $v;

// what we store: [key, legal basis b|f]
$rows = [
    ['email', 'b'], ['password', 'b'], ['city', 'b'], ['prefs', 'b'], ['tokens', 'b'], ['keys', 'b'],
    ['sent', 'f'], ['runs', 'f'], ['login', 'f'], ['ip', 'f'],
];
$webLog = $cfg['web_log'] ?? ['ip' => 'full', 'days' => 7];
$webMode = in_array($webLog['ip'] ?? '', ['full', 'shortened_daily', 'shortened', 'none'], true) ? $webLog['ip'] : 'full';
$processors = array_filter((array) ($cfg['processors'] ?? []), static fn ($p) => !empty($p['name']));
$authority = $cfg['authority'] ?? [];
?>
<section class="prose privacy">
  <h1><?= e(t('privacy.title')) ?></h1>
  <p class="lead"><?= e(t('privacy.lead')) ?></p>
  <p class="quiet"><?= e(t('privacy.updated')) ?></p>

  <h2 id="controller"><?= e(t('privacy.controller_title')) ?></h2>
  <p><?= e(t('privacy.controller_text')) ?></p>
  <p><?= e($imprint['name'] ?? '') ?><br><?= nl2br(e($imprint['address'] ?? '')) ?><br>
    <?= e(t('field.email')) ?>: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>

  <h2 id="data"><?= e(t('privacy.stored_title')) ?></h2>
  <table class="plain privacy-table">
    <thead><tr>
      <th scope="col"><?= e(t('privacy.col_what')) ?></th>
      <th scope="col"><?= e(t('privacy.col_why')) ?></th>
      <th scope="col"><?= e(t('privacy.col_basis')) ?></th>
      <th scope="col"><?= e(t('privacy.col_keep')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as [$k, $basis]): ?>
        <tr>
          <th scope="row" data-label="<?= e(t('privacy.col_what')) ?>"><?= e(t("privacy.row.$k")) ?></th>
          <td data-label="<?= e(t('privacy.col_why')) ?>"><?= e(t("privacy.row.$k.why")) ?></td>
          <td data-label="<?= e(t('privacy.col_basis')) ?>"><?= e(t("privacy.basis.$basis")) ?></td>
          <td data-label="<?= e(t('privacy.col_keep')) ?>"><?= e(t("privacy.row.$k.keep")) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2 id="required"><?= e(t('privacy.required_title')) ?></h2>
  <p><?= e(t('privacy.required_text')) ?></p>

  <h2 id="logs"><?= e(t('privacy.logs_title')) ?></h2>
  <p><?= e(t("privacy.logs_web.$webMode", ['days' => (int) ($webLog['days'] ?? 7)])) ?></p>
  <p><?= e(t('privacy.logs_mail', ['days' => (int) ($cfg['mail_log_days'] ?? 7)])) ?></p>

  <?php if ($stats): ?>
    <h2 id="stats"><?= e(t('privacy.stats_title')) ?></h2>
    <p><?= e(t('privacy.stats_text', ['host' => parse_url(Matomo::url(), PHP_URL_HOST), 'days' => Matomo::retentionDays()])) ?></p>
    <p><?= e(t('privacy.stats_basis')) ?></p>
    <div class="stats-optout" data-stats-optout hidden>
      <label class="choice"><input type="checkbox" checked> <?= e(t('privacy.stats_switch')) ?></label>
      <p class="quiet" data-stats-state data-on="<?= e(t('privacy.stats_on')) ?>" data-off="<?= e(t('privacy.stats_off')) ?>" aria-live="polite"></p>
    </div>
  <?php endif; ?>

  <h2 id="cookies"><?= e(t('privacy.cookies_title')) ?></h2>
  <ul>
    <li><?= e(t('privacy.cookies_session')) ?></li>
    <li><?= e(t('privacy.cookies_lang')) ?></li>
    <?php if ($stats): ?><li><?= e(t('privacy.cookies_optout')) ?></li><?php endif; ?>
  </ul>
  <p><?= e(t('privacy.cookies_end')) ?></p>

  <h2 id="recipients"><?= e(t('privacy.recipients_title')) ?></h2>
  <?php if ($processors): ?>
    <p><?= e(t('privacy.recipients_lead')) ?></p>
    <ul>
      <?php foreach ($processors as $p): ?>
        <li><strong><?= e($p['name']) ?></strong><?= !empty($p['address']) ? ', ' . e($p['address']) : '' ?><?php
            $service = $loc($p['service'] ?? ''); if ($service !== ''): ?>: <?= e($service) ?><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p><?= e(t('privacy.recipients_none')) ?></p>
  <?php endif; ?>
  <p><?= e(t('privacy.recipients_calc')) ?></p>
  <?php if ($cfg['eu_only'] ?? true): ?><p><?= e(t('privacy.recipients_eu')) ?></p><?php endif; ?>

  <h2 id="not"><?= e(t('privacy.not_title')) ?></h2>
  <p><?= e(t($stats ? 'privacy.not_text_stats' : 'privacy.not_text')) ?></p>

  <h2 id="delete"><?= e(t('privacy.delete_title')) ?></h2>
  <p><?= e(t($stats ? 'privacy.delete_text_stats' : 'privacy.delete_text')) ?></p>

  <h2 id="rights"><?= e(t('privacy.rights_title')) ?></h2>
  <p><?= e(t('privacy.rights_lead')) ?></p>
  <ul>
    <?php foreach (['access', 'rectify', 'erase', 'restrict', 'portability', 'object', 'complain'] as $r): ?>
      <li><?= e(t("privacy.right.$r")) ?></li>
    <?php endforeach; ?>
  </ul>
  <p><?= e(t('privacy.rights_contact', ['email' => $email])) ?></p>
  <?php if (!empty($authority['name'])): ?>
    <p><?= e(t('privacy.authority')) ?><br><?= e($authority['name']) ?><?php if (!empty($authority['address'])): ?><br><?= nl2br(e($authority['address'])) ?><?php endif; ?>
      <?php if (!empty($authority['url']) && str_starts_with($authority['url'], 'https://')): ?><br><a href="<?= e($authority['url']) ?>"><?= e($authority['url']) ?></a><?php endif; ?></p>
  <?php endif; ?>

  <div class="callout objection" id="objection">
    <h2><?= e(t('privacy.object_title')) ?></h2>
    <p><strong><?= e(t('privacy.object_text', ['email' => $email])) ?></strong></p>
    <?php if ($stats): ?><p><?= e(t('privacy.object_stats')) ?></p><?php endif; ?>
  </div>
</section>
