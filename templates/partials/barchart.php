<?php
/**
 * Small single-series column chart, drawn on the server.
 * One colour (the series), recessive grid, value on hover, a table for screen readers.
 * @var string $title   what is counted, e.g. "Mails per day"
 * @var array  $values  label => number, oldest first
 * @var callable|null $fmt  label formatter for the x axis and the table
 */
use PlanetAlerts\Format;

$fmt ??= static fn ($k) => Format::date($k);
$W = 640; $H = 170; $left = 34; $right = 8; $top = 12; $bottom = 26;
$vals = array_values($values);
$keys = array_keys($values);
$n = max(1, count($vals));
$max = max(1, max($vals ?: [0]));
// a round top value for the scale
$mag = 10 ** floor(log10($max));
$nice = ceil($max / $mag) * $mag;
if ($nice / $mag > 5 && $nice < $max * 1.2) {
    $nice = $max;
}
$plotW = $W - $left - $right; $plotH = $H - $top - $bottom;
$slot = $plotW / $n;
$bw = max(2, $slot - 2);                      // 2 px gap between columns
$Y = static fn ($v) => $top + $plotH - $v / $nice * $plotH;
$total = array_sum($vals);
?>
<figure class="chart">
  <figcaption><?= e($title) ?></figcaption>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e($title . ': ' . t('status.chart_total', ['n' => $total])) ?>">
    <?php foreach ([0, $nice / 2, $nice] as $g): ?>
      <line class="ch-grid" x1="<?= $left ?>" x2="<?= $W - $right ?>" y1="<?= round($Y($g), 1) ?>" y2="<?= round($Y($g), 1) ?>"/>
      <text class="ch-axis" x="<?= $left - 6 ?>" y="<?= round($Y($g), 1) + 4 ?>" text-anchor="end"><?= e(rtrim(rtrim(number_format($g, 1, ',', '.'), '0'), ',')) ?></text>
    <?php endforeach; ?>
    <?php foreach ($vals as $i => $val):
        $x = $left + $i * $slot + 1;
        $h = $val > 0 ? max(2, $plotH - ($Y($val) - $top)) : 0; ?>
      <g class="ch-col">
        <rect class="ch-hit" x="<?= round($x, 1) ?>" y="<?= $top ?>" width="<?= round($slot, 1) ?>" height="<?= $plotH ?>"/>
        <?php if ($h > 0): ?>
          <path class="ch-bar" d="M<?= round($x, 1) ?> <?= $top + $plotH ?> V<?= round($top + $plotH - $h + 2, 1) ?> Q<?= round($x, 1) ?> <?= round($top + $plotH - $h, 1) ?> <?= round($x + 2, 1) ?> <?= round($top + $plotH - $h, 1) ?> H<?= round($x + $bw - 2, 1) ?> Q<?= round($x + $bw, 1) ?> <?= round($top + $plotH - $h, 1) ?> <?= round($x + $bw, 1) ?> <?= round($top + $plotH - $h + 2, 1) ?> V<?= $top + $plotH ?> Z"/>
        <?php endif; ?>
        <title><?= e($fmt($keys[$i]) . ': ' . $val) ?></title>
      </g>
    <?php endforeach; ?>
    <line class="ch-base" x1="<?= $left ?>" x2="<?= $W - $right ?>" y1="<?= $top + $plotH ?>" y2="<?= $top + $plotH ?>"/>
    <?php foreach ([0, intdiv($n - 1, 2), $n - 1] as $k => $i): if (!isset($keys[$i])) continue; ?>
      <text class="ch-axis" x="<?= round($left + $i * $slot + $slot / 2, 1) ?>" y="<?= $H - 6 ?>"
            text-anchor="<?= $k === 0 ? 'start' : ($k === 2 ? 'end' : 'middle') ?>"><?= e($fmt($keys[$i])) ?></text>
    <?php endforeach; ?>
  </svg>
  <details class="ch-table">
    <summary><?= e(t('status.as_table')) ?></summary>
    <table class="plain">
      <tbody>
        <?php foreach ($values as $k => $val): ?>
          <tr><td><?= e($fmt($k)) ?></td><td class="num"><?= (int) $val ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </details>
</figure>
