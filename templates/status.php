<?php
use PlanetAlerts\Format;
use PlanetAlerts\View;

$num = static fn ($n) => $n === null ? '–' : number_format((float) $n, is_float($n) && floor($n) != $n ? 1 : 0, t('status.dec'), t('status.thousands'));
$pct = static fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, t('status.dec'), ''), '0'), t('status.dec')) . ' %';
$icon = static function (string $state): string {
    return match ($state) {
        'ok'       => '<svg class="st-icon st-ok" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8"/><path d="M4.5 8.3l2.3 2.2 4.7-4.8"/></svg>',
        'degraded' => '<svg class="st-icon st-warn" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1l7.5 14H.5z"/><path d="M8 6v4M8 12v.5"/></svg>',
        default    => '<svg class="st-icon st-down" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8"/><path d="M5 5l6 6M11 5l-6 6"/></svg>',
    };
};
$c = $s['calc'];
$j = $s['job'];
$u = $s['users'];
$a = $s['alerts'];
$m = $s['mails'];
$api = $s['api'];
$p = $s['places'];
$weekdays = [1, 2, 3, 4, 5, 6, 0];
?>
<section class="status-page">
  <h1><?= e(t('status.heading')) ?></h1>
  <p class="status-banner status-<?= e($s['overall']) ?>" role="status">
    <?= $icon($s['overall']) ?> <?= e(t('status.overall.' . $s['overall'])) ?>
  </p>
  <p class="quiet"><?= e(t('status.updated', ['time' => (new DateTimeImmutable($s['generated_at']))->setTimezone(new DateTimeZone((string) \PlanetAlerts\App::config('app.owner_timezone', 'Europe/Berlin')))->format('H:i')])) ?>
    <a href="/status.json">status.json</a></p>

  <h2><?= e(t('status.parts')) ?></h2>
  <table class="plain status-parts">
    <tbody>
      <tr><td><?= $icon('ok') ?> <?= e(t('status.part.web')) ?></td><td><?= e(t('status.state.ok')) ?></td><td class="quiet"></td></tr>
      <tr><td><?= $icon('ok') ?> <?= e(t('status.part.db')) ?></td><td><?= e(t('status.state.ok')) ?></td><td class="quiet"><?= e(t('status.ms', ['n' => $s['db_ms']])) ?></td></tr>
      <tr><td><?= $icon($c['ok'] ? 'ok' : 'down') ?> <?= e(t('status.part.calc')) ?></td>
          <td><?= e(t($c['ok'] ? 'status.state.ok' : 'status.state.down')) ?></td>
          <td class="quiet"><?= $c['ok'] ? e(t('status.calc_detail', ['ms' => $c['ms'], 'version' => $c['version'],
              'cities' => $num($c['cities']), 'date' => $c['cities_date'] ? Format::date($c['cities_date']) : '–', 'engine' => $c['engine']])) : '' ?></td></tr>
      <tr><td><?= $icon($j['ok'] ? 'ok' : 'degraded') ?> <?= e(t('status.part.job')) ?></td>
          <td><?= e(t($j['ok'] ? 'status.state.ok' : 'status.state.late')) ?></td>
          <td class="quiet"><?= e($j['last_run_minutes_ago'] === null ? t('status.job_never')
              : t('status.job_detail', ['min' => $j['last_run_minutes_ago'], 'done' => $j['done'], 'failed' => $j['failed'], 'skipped' => $j['skipped']])) ?></td></tr>
    </tbody>
  </table>

  <h2><?= e(t('status.uptime')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.last_24h')) ?></dt><dd><?= $pct($s['uptime']['24h']) ?></dd></div>
    <div><dt><?= e(t('status.last_7d')) ?></dt><dd><?= $pct($s['uptime']['7d']) ?></dd></div>
    <div><dt><?= e(t('status.last_30d')) ?></dt><dd><?= $pct($s['uptime']['30d']) ?></dd></div>
    <div><dt><?= e(t('status.check_ms')) ?></dt><dd><?= $api['check_avg_ms'] ? e(t('status.ms', ['n' => $api['check_avg_ms']])) : '–' ?></dd></div>
  </dl>
  <figure class="uptime">
    <figcaption><?= e(t('status.days90')) ?></figcaption>
    <svg viewBox="0 0 900 48" role="img" aria-label="<?= e(t('status.days90_label', [
        'ok' => count(array_filter($s['days90'], static fn ($d) => $d['state'] === 'ok')),
        'partial' => count(array_filter($s['days90'], static fn ($d) => $d['state'] === 'partial')),
        'down' => count(array_filter($s['days90'], static fn ($d) => $d['state'] === 'down'))])) ?>">
      <?php foreach ($s['days90'] as $i => $d):
          $h = ['ok' => 40, 'partial' => 27, 'down' => 14, 'none' => 40][$d['state']]; ?>
        <g>
          <rect class="up-hit" x="<?= $i * 10 ?>" y="0" width="10" height="48"/>
          <rect class="up-day up-<?= $d['state'] ?>" x="<?= $i * 10 + 1 ?>" y="<?= 44 - $h ?>" width="8" height="<?= $h ?>" rx="1.5"/>
          <title><?= e(Format::date($d['date']) . ': ' . t('status.day.' . $d['state'])
              . ($d['checks'] ? ' (' . t('status.day_checks', ['ok' => $d['ok'], 'n' => $d['checks']]) . ')' : '')) ?></title>
        </g>
      <?php endforeach; ?>
    </svg>
    <p class="legend">
      <span><span class="swatch up-ok"></span><?= e(t('status.day.ok')) ?></span>
      <span><span class="swatch swatch-mid up-partial"></span><?= e(t('status.day.partial')) ?></span>
      <span><span class="swatch swatch-low up-down"></span><?= e(t('status.day.down')) ?></span>
      <span><span class="swatch up-none"></span><?= e(t('status.day.none')) ?></span>
    </p>
  </figure>

  <h2><?= e(t('status.users')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.u.total')) ?></dt><dd><?= $num($u['total']) ?></dd></div>
    <div><dt><?= e(t('status.u.verified')) ?></dt><dd><?= $num($u['verified']) ?></dd></div>
    <div><dt><?= e(t('status.u.mail')) ?></dt><dd><?= $num($u['mail']) ?></dd></div>
    <div><dt><?= e(t('status.u.paused')) ?></dt><dd><?= $num($u['paused']) ?></dd></div>
    <div><dt><?= e(t('status.u.new_today')) ?></dt><dd><?= $num($u['new_today']) ?></dd></div>
    <div><dt><?= e(t('status.u.new_7d')) ?></dt><dd><?= $num($u['new_7d']) ?></dd></div>
    <div><dt><?= e(t('status.u.new_30d')) ?></dt><dd><?= $num($u['new_30d']) ?></dd></div>
    <div><dt><?= e(t('status.u.active_7d')) ?></dt><dd><?= $num($u['active_7d']) ?></dd></div>
    <div><dt><?= e(t('status.u.active_30d')) ?></dt><dd><?= $num($u['active_30d']) ?></dd></div>
    <div><dt><?= e(t('status.u.logins_30d')) ?></dt><dd><?= $num($u['logins_30d']) ?></dd></div>
    <div><dt><?= e(t('status.u.deleted_30d')) ?></dt><dd><?= $num($u['deleted_30d']) ?></dd></div>
    <div><dt><?= e(t('status.u.unsub_30d')) ?></dt><dd><?= $num($u['unsub_30d']) ?></dd></div>
    <?php foreach (\PlanetAlerts\I18n::languages() as $code => $name): ?>
    <div><dt><?= e(t('status.u.lang', ['lang' => $name])) ?></dt><dd><?= $num((int) ($u['langs'][$code] ?? 0)) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <div class="charts">
    <?= View::partial('partials/barchart', ['title' => t('status.ch.growth'), 'values' => $u['growth']]) ?>
    <?= View::partial('partials/barchart', ['title' => t('status.ch.signups'), 'values' => $u['signups_weekly'],
        'fmt' => static fn ($k) => t('status.week_of', ['date' => Format::date($k)])]) ?>
  </div>

  <h2><?= e(t('status.alerts')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.a.total')) ?></dt><dd><?= $num($a['total']) ?></dd></div>
    <div><dt><?= e(t('status.a.enabled')) ?></dt><dd><?= $num($a['enabled']) ?></dd></div>
    <div><dt><?= e(t('status.a.per_user')) ?></dt><dd><?= $num($a['per_user']) ?></dd></div>
    <div><dt><?= e(t('status.a.multi')) ?></dt><dd><?= $num($a['multi']) ?></dd></div>
  </dl>
  <div class="charts">
    <?= View::partial('partials/hbars', ['title' => t('status.ch.planets'), 'rows' => array_map(
        static fn ($k, $v) => ['label' => t("planet.$k"), 'value' => $v, 'dot' => $k], array_keys($a['planets']), $a['planets'])]) ?>
    <?= View::partial('partials/hbars', ['title' => t('status.ch.types'), 'rows' => array_map(
        static fn ($k, $v) => ['label' => t("type.$k"), 'value' => $v], array_keys($a['types']), $a['types'])]) ?>
    <?= View::partial('partials/hbars', ['title' => t('status.ch.styles'), 'rows' => array_map(
        static fn ($k) => ['label' => t("style.$k"), 'value' => (int) ($a['styles'][$k] ?? 0)], ['alerts', 'digest', 'both'])]) ?>
    <?= View::partial('partials/hbars', ['title' => t('status.ch.per_user'), 'rows' => array_map(
        static fn ($k, $v) => ['label' => t('status.n_alerts', ['n' => $k]), 'value' => $v], array_keys($a['dist']), $a['dist'])]) ?>
  </div>

  <h2><?= e(t('status.mails')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.m.today')) ?></dt><dd><?= $num($m['today']) ?></dd></div>
    <div><dt><?= e(t('status.m.7d')) ?></dt><dd><?= $num($m['7d']) ?></dd></div>
    <div><dt><?= e(t('status.m.30d')) ?></dt><dd><?= $num($m['30d']) ?></dd></div>
    <div><dt><?= e(t('status.m.alerts_30d')) ?></dt><dd><?= $num($m['alerts_30d']) ?></dd></div>
    <div><dt><?= e(t('status.m.digest_30d')) ?></dt><dd><?= $num($m['digest_30d']) ?></dd></div>
    <div><dt><?= e(t('status.m.failed_30d')) ?></dt><dd><?= $num($m['failed_30d']) ?></dd></div>
    <div><dt><?= e(t('status.m.found_30d')) ?></dt><dd><?= $num($m['found_30d']) ?></dd></div>
    <div><dt><?= e(t('status.m.sent_30d')) ?></dt><dd><?= $num($m['sent_30d']) ?></dd></div>
  </dl>
  <div class="charts">
    <?= View::partial('partials/barchart', ['title' => t('status.ch.mails'), 'values' => $m['daily']]) ?>
    <?= View::partial('partials/barchart', ['title' => t('status.ch.weekday'),
        'values' => array_combine($weekdays, array_map(static fn ($wd) => $m['weekday'][($wd + 6) % 7], $weekdays)),
        'fmt' => static fn ($k) => t('weekday.' . $k)]) ?>
  </div>

  <h2><?= e(t('status.api')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.api.keys')) ?></dt><dd><?= $num($api['keys']) ?></dd></div>
    <div><dt><?= e(t('status.api.key_users')) ?></dt><dd><?= $num($api['key_users']) ?></dd></div>
    <div><dt><?= e(t('status.api.used_7d')) ?></dt><dd><?= $num($api['used_7d']) ?></dd></div>
    <div><dt><?= e(t('status.api.today')) ?></dt><dd><?= $num($api['today']) ?></dd></div>
    <div><dt><?= e(t('status.api.30d')) ?></dt><dd><?= $num($api['30d']) ?></dd></div>
    <div><dt><?= e(t('status.api.pages')) ?></dt><dd><?= $num($api['city_pages']) ?></dd></div>
    <div><dt><?= e(t('status.api.pages_30')) ?></dt><dd><?= $num($api['city_pages_30']) ?></dd></div>
    <div><dt><?= e(t('status.calc.today')) ?></dt><dd><?= $num($api['calc_today']) ?></dd></div>
    <div><dt><?= e(t('status.calc.30d')) ?></dt><dd><?= $num($api['calc_30d']) ?></dd></div>
    <div><dt><?= e(t('status.calc.avg')) ?></dt><dd><?= $api['calc_avg_ms'] !== null ? e(t('status.ms', ['n' => $api['calc_avg_ms']])) : '–' ?></dd></div>
    <div><dt><?= e(t('status.calc.max')) ?></dt><dd><?= $api['calc_max_ms'] !== null ? e(t('status.ms', ['n' => $api['calc_max_ms']])) : '–' ?></dd></div>
    <div><dt><?= e(t('status.cache.rate')) ?></dt><dd><?= $api['cache_rate'] !== null ? $api['cache_rate'] . ' %' : '–' ?></dd></div>
    <div><dt><?= e(t('status.cache.rate30')) ?></dt><dd><?= $api['cache_rate_30'] !== null ? $api['cache_rate_30'] . ' %' : '–' ?></dd></div>
    <div><dt><?= e(t('status.cache.entries')) ?></dt><dd><?= $num($api['cache_entries']) ?></dd></div>
  </dl>
  <div class="charts">
    <?= View::partial('partials/barchart', ['title' => t('status.ch.api'), 'values' => $api['daily']]) ?>
  </div>

  <h2><?= e(t('status.places')) ?></h2>
  <dl class="tiles">
    <div><dt><?= e(t('status.p.cities')) ?></dt><dd><?= $num($p['cities']) ?></dd></div>
    <div><dt><?= e(t('status.p.countries')) ?></dt><dd><?= $num($p['countries']) ?></dd></div>
    <div><dt><?= e(t('status.p.timezones')) ?></dt><dd><?= $num($p['timezones']) ?></dd></div>
  </dl>
  <div class="charts">
    <?php if ($p['top_cities']): ?>
      <?= View::partial('partials/hbars', ['title' => t('status.ch.cities'), 'rows' => array_map(
          static fn ($r) => ['label' => $r['name'] . ', ' . $r['country'], 'value' => (int) $r['n']], $p['top_cities'])]) ?>
    <?php endif; ?>
    <?php if ($p['top_countries']): ?>
      <?= View::partial('partials/hbars', ['title' => t('status.ch.countries'), 'rows' => array_map(
          static fn ($r) => ['label' => $r['country'], 'value' => (int) $r['n']], $p['top_countries'])]) ?>
    <?php endif; ?>
  </div>
  <p class="quiet"><?= e(t('status.privacy', ['n' => \PlanetAlerts\Controllers\Status::MIN_GROUP])) ?></p>

  <?php if ($s['sky']): ?>
    <h2><?= e(t('status.sky')) ?></h2>
    <ul class="events">
      <?php foreach ($s['sky'] as $ev): ?>
        <li><time datetime="<?= e(substr($ev['at'], 0, 10)) ?>"><?= e(Format::date(substr($ev['at'], 0, 10))) ?></time>
          <span><?= e(t('global.' . $ev['type'], ['planets' => Format::planets($ev['planets'])])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="quiet"><?= e(t('status.sky_note')) ?></p>
  <?php endif; ?>
</section>
