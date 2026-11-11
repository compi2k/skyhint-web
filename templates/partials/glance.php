<?php
/**
 * "Tonight at a glance" tiles.
 * @var ?array  $dark    [start, end] ISO times, null if it never gets dark
 * @var ?int    $moon    illumination in percent
 * @var bool    $waxing
 * @var list<string> $visible planet names
 * @var ?array  $best    ['name', 'time'] the best moment
 * @var ?array  $next    ['date', 'title'] the next special night (optional)
 */
use PlanetAlerts\Format;

$n = count($visible);
// Moon icon: lit part on the right while waxing (northern view), simple two-path shape
$k = max(0, min(100, (int) ($moon ?? 0))) / 100;
$rx = abs(1 - 2 * $k) * 15;
$sweep = $k > .5 ? 1 : 0;
$lit = $waxing
    ? "M20 5 A15 15 0 0 1 20 35 A$rx 15 0 0 $sweep 20 5Z"
    : "M20 5 A15 15 0 0 0 20 35 A$rx 15 0 0 " . (1 - $sweep) . " 20 5Z";
?>
<section class="glance" aria-label="<?= e(t('glance.label')) ?>">
  <div class="glance-tile">
    <p class="glance-k"><?= e(t('glance.dark')) ?></p>
    <p class="glance-v mono"><?= $dark ? e(Format::time($dark[0]) . '–' . Format::time($dark[1])) : e(t('glance.no_dark')) ?></p>
  </div>
  <div class="glance-tile glance-moon">
    <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="15" class="moon-dark"/><?php if ($k > .01): ?><path d="<?= $lit ?>" class="moon-lit"/><?php endif; ?></svg>
    <div><p class="glance-k"><?= e(t('glance.moon')) ?></p><p class="glance-v mono"><?= $moon === null ? '–' : (int) $moon . ' %' ?></p></div>
  </div>
  <div class="glance-tile">
    <p class="glance-k"><?= e(t('glance.visible')) ?></p>
    <p class="glance-v mono"><?= e($n === 0 ? t('glance.visible_none') : ($n === 1 ? t('glance.visible_one') : t('glance.visible_n', ['n' => $n]))) ?>
      <span class="glance-dots"><?php foreach ($visible as $p): ?><span class="dot planet-<?= e($p) ?>" title="<?= e(Format::planet($p)) ?>"></span><?php endforeach; ?></span></p>
  </div>
  <?php if (!empty($next)): ?>
    <div class="glance-tile glance-hi">
      <p class="glance-k"><?= e(t('glance.next')) ?> · <?= e(Format::date($next['date'])) ?></p>
      <p class="glance-v glance-text"><?= e($next['title']) ?></p>
    </div>
  <?php elseif ($best): ?>
    <div class="glance-tile glance-hi">
      <p class="glance-k"><?= e(t('glance.best')) ?></p>
      <p class="glance-v"><span class="glance-text"><?= e(Format::planet($best['name'])) ?></span> <span class="mono"><?= e(Format::time($best['time'])) ?></span></p>
    </div>
  <?php endif; ?>
</section>
