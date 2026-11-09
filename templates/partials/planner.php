<?php
/**
 * Night planner: one row per planet (and the Moon) from sunset to sunrise.
 * Thin line = above the horizon, thick bar = good to see (high enough, sky dark), dot = best moment.
 * @var array $sky output of SkyTrack::prepare
 */
$rows = $sky['rows'];
$times = $sky['times'];
$sun = $sky['sun'];
$n = count($times);

// sky colour behind each 10-minute step, from twilight to deep night
$shade = static function (float $alt): string {
    $f = max(0.0, min(1.0, (-$alt) / 18));          // 0 at sunset, 1 in full darkness
    $c0 = [92, 112, 168]; $c1 = [16, 22, 52];
    $c = array_map(static fn ($a, $b) => (int) round($a + ($b - $a) * $f), $c0, $c1);
    return "rgb({$c[0]},{$c[1]},{$c[2]})";
};

$summary = [];
foreach ($rows as $r) {
    $parts = [];
    foreach ($r['good'] as [$a, $b]) {
        $parts[] = $times[$a] . '–' . $times[$b];
    }
    $summary[] = t('planet.' . $r['name']) . ': ' . ($parts ? t('planner.good', ['spans' => implode(', ', $parts)])
        : t('planner.not_good')) . ($r['best'] !== null ? ' ' . t('planner.best', ['time' => $times[$r['best']]]) : '');
}
$draw = static function (int $W, int $x0, int $hourEvery, string $class) use ($rows, $times, $sun, $n, $shade, $summary, $sky): string {
$x1 = $W - 16; $top = 30; $rowH = 34;
$H = $top + max(1, count($rows)) * $rowH + 10;
$X = static fn (float $i) => $x0 + ($n > 1 ? $i / ($n - 1) : 0) * ($x1 - $x0);
$step = ($x1 - $x0) / max(1, $n - 1);
ob_start();
?>
  <svg class="<?= $class ?>" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e(t('planner.label') . ' ' . implode('. ', $summary)) ?>">
    <?php for ($i = 0; $i < $n; $i++): ?>
      <rect x="<?= round($X($i) - $step / 2, 1) ?>" y="<?= $top - 6 ?>" width="<?= round($step + 0.6, 1) ?>" height="<?= $H - $top ?>" fill="<?= $shade((float) $sun[$i]) ?>"/>
    <?php endfor; ?>
    <?php // full-hour ticks
    for ($i = 0; $i < $n; $i++):
        if (substr($times[$i], 3, 2) > '09' || ($i > 0 && substr($times[$i - 1], 0, 2) === substr($times[$i], 0, 2))
            || ((int) substr($times[$i], 0, 2)) % $hourEvery !== 0) continue; ?>
      <line class="pl-tick" x1="<?= round($X($i), 1) ?>" x2="<?= round($X($i), 1) ?>" y1="<?= $top - 6 ?>" y2="<?= $H - 4 ?>"/>
      <text class="pl-hour" x="<?= round($X($i), 1) ?>" y="<?= $top - 12 ?>" text-anchor="middle"><?= e(substr($times[$i], 0, 2)) ?></text>
    <?php endfor; ?>
    <?php foreach ($rows as $k => $r): $y = $top + $k * $rowH + $rowH / 2; ?>
      <g class="pl-row planet-<?= e($r['name']) ?>">
        <text class="pl-name" x="<?= $x0 - 10 ?>" y="<?= $y + 5 ?>" text-anchor="end"><?= e(t('planet.' . $r['name'])) ?></text>
        <?php foreach ($r['up'] as [$a, $b]): ?>
          <line class="pl-up" x1="<?= round($X($a), 1) ?>" x2="<?= round($X($b), 1) ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
        <?php endforeach; ?>
        <?php foreach ($r['good'] as [$a, $b]): ?>
          <rect class="pl-good" x="<?= round($X($a), 1) ?>" y="<?= $y - 7 ?>" width="<?= round(max(3, $X($b) - $X($a)), 1) ?>" height="14" rx="7"/>
        <?php endforeach; ?>
        <?php if ($r['best'] !== null): ?>
          <circle class="pl-best" cx="<?= round($X($r['best']), 1) ?>" cy="<?= $y ?>" r="5.5"/>
        <?php endif; ?>
      </g>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <text class="pl-name" x="<?= ($x0 + $x1) / 2 ?>" y="<?= $top + 22 ?>" text-anchor="middle"><?= e(t('planner.empty')) ?></text>
    <?php endif; ?>
    <line class="pl-cursor" x1="<?= round($X($sky['start']), 1) ?>" x2="<?= round($X($sky['start']), 1) ?>" y1="<?= $top - 6 ?>" y2="<?= $H - 4 ?>" data-x0="<?= $x0 ?>" data-x1="<?= $x1 ?>" data-w="<?= $W ?>"/>
  </svg>
<?php
return (string) ob_get_clean();
};
?>
<figure class="planner">
  <?= $draw(640, 84, 1, 'planner-wide') ?>
  <?= $draw(400, 74, 2, 'planner-narrow') ?>
</figure>
