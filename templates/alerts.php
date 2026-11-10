<?php
use PlanetAlerts\Alerts;
use PlanetAlerts\Cities;
use PlanetAlerts\Format;
use PlanetAlerts\Prefs;
$verified = $u['email_verified_at'] !== null;
$full = count($alerts) >= $max;
$multi = count($alerts) > 1;
$anyOn = (bool) array_filter($alerts, static fn ($a) => $a['enabled']);
?>
<section class="dash dash-wide">
  <div class="title-row">
    <h1><?= e(t('alerts.heading')) ?></h1>
    <?php if (!$full): ?>
      <a class="button primary" href="/alerts/new"><?= e(t('alerts.new')) ?></a>
    <?php endif; ?>
  </div>

  <?php if (!$verified): ?>
    <div class="notice" role="status">
      <p><?= e(t('dash.unverified', ['email' => $u['email']])) ?></p>
      <form method="post" action="/verify/resend"><?= csrf_field() ?>
        <button type="submit"><?= e(t('dash.resend')) ?></button></form>
    </div>
  <?php elseif (!$u['mail_enabled']): ?>
    <div class="notice" role="status"><p><?= e(t('dash.mail_off')) ?> <a href="/account"><?= e(t('nav.account')) ?></a></p></div>
  <?php elseif ($paused): ?>
    <div class="notice" role="status">
      <p><?= e(t('dash.paused_until', ['date' => Format::date($u['paused_until'])])) ?></p>
      <form method="post" action="/account/pause"><?= csrf_field() ?>
        <input type="hidden" name="resume" value="1">
        <button type="submit"><?= e(t('dash.resume')) ?></button></form>
    </div>
  <?php endif; ?>

  <div class="alerts-grid">
  <div class="alerts-col">
  <?php if (!$alerts): ?>
    <p class="lead"><?= e(t('alerts.empty')) ?></p>
  <?php else: ?>
    <p class="lead"><?php if ($anyOn): ?>
      <?= e(t('alerts.lead', ['n' => count($alerts), 'max' => $max,
          'slot' => \PlanetAlerts\MailTime::label($mailSlot), 'tz' => \PlanetAlerts\MailTime::zoneLabel($mailTz)])) ?>
      <a href="/account#mails"><?= e(t('alerts.lead_change')) ?></a>
    <?php else: ?>
      <?= e(t('alerts.lead_all_off', ['n' => count($alerts), 'max' => $max])) ?>
    <?php endif; ?></p>

    <ul class="alert-list">
      <?php foreach ($alerts as $a): $label = Alerts::label($a); ?>
        <li id="alert-<?= (int) $a['id'] ?>" class="<?= $a['enabled'] ? '' : 'is-off' ?>">
          <div class="alert-main">
            <h2 class="alert-name"><a href="/city/<?= (int) $a['city_id'] ?>" title="<?= e(t('alerts.city_link', ['city' => $a['city_name']])) ?>"><?= e($label) ?></a></h2>
            <p class="quiet"><?= e(Cities::label(Alerts::city($a))) ?></p>
            <p class="alert-planets">
              <?php foreach ($a['prefs']['planets'] as $p): ?>
                <span class="tag"><span class="dot planet-<?= e($p) ?>" aria-hidden="true"></span><?= e(Format::planet($p)) ?></span>
              <?php endforeach; ?>
            </p>
            <p class="hint"><?= e(t('alerts.types_count', ['n' => count($a['prefs']['events']),
                'total' => count(Prefs::EVENTS)])) ?></p>
          </div>
          <div class="alert-actions">
            <form method="post" action="/alerts/<?= (int) $a['id'] ?>/toggle"><?= csrf_field() ?>
              <button type="submit" class="switch" role="switch" aria-checked="<?= $a['enabled'] ? 'true' : 'false' ?>"
                      aria-label="<?= e(t('alerts.switch_label', ['place' => $label])) ?>">
                <span class="switch-track" aria-hidden="true"></span>
                <span class="switch-text"><?= e(t($a['enabled'] ? 'alerts.on' : 'alerts.off')) ?></span>
              </button>
            </form>
            <a href="/alerts/<?= (int) $a['id'] ?>/edit"><?= e(t('alerts.edit')) ?><span class="sr-only">: <?= e($label) ?></span></a>
            <form method="post" action="/alerts/<?= (int) $a['id'] ?>/delete"
                  data-confirm="<?= e(t('alerts.delete_confirm', ['place' => $label])) ?>"><?= csrf_field() ?>
              <button type="submit" class="link danger"><?= e(t('alerts.delete')) ?><span class="sr-only">: <?= e($label) ?></span></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($full): ?><p class="quiet"><?= e(t('alerts.max', ['n' => $max])) ?></p><?php endif; ?>
  <?php endif; ?>
  </div>

  <div class="alerts-col">
  <h2 id="upcoming"><?= e(t('dash.upcoming', ['n' => $days])) ?></h2>
  <?php if ($calcDown): ?>
    <p class="quiet"><?= e(t('error.calc_text')) ?></p>
  <?php endif; ?>
  <?php if (!$upcoming && !$calcDown): ?>
    <p class="quiet"><?= e(t('dash.no_upcoming')) ?></p>
  <?php elseif ($upcoming): ?>
    <ul class="events">
      <?php foreach ($upcoming as $row): $ev = $row['event']; ?>
        <li><time datetime="<?= e($ev['date']) ?>"><?= e(Format::date($ev['date'])) ?></time>
          <span><?php if ($multi): ?><a class="place" href="/city/<?= (int) $row['alert']['city_id'] ?>"><?= e(Alerts::label($row['alert'])) ?></a><?php endif; ?>
            <?= e(Format::eventTitle($ev)) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  </div>
  </div>
</section>
