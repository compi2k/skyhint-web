<?php
/**
 * "Go outside" card for one planet tonight.
 * @var string $planet
 * @var array  $vis    the planet's entry from the night record (best, windows, magnitude)
 * @var bool   $south  southern hemisphere: the noon Sun stands in the north
 * @var bool   $open   shown opened
 */
use PlanetAlerts\Format;

$best = $vis['best'];
$az = (float) $best['azimuth'];
$alt = (float) $best['altitude'];
$dir = $best['direction'];
$fists = max(0, (int) round($alt / 10));
$windows = $vis['windows'];
$from = Format::time($windows[0]['start']);
$to = Format::time($windows[count($windows) - 1]['end']);
$name = Format::planet($planet);
$id = 'card-' . $planet;

// top view: compass rose, arrow towards the planet (north up)
$cx = 64; $cy = 64; $R = 46;
$rad = deg2rad($az);
$tx = $cx + sin($rad) * ($R - 6);
$ty = $cy - cos($rad) * ($R - 6);
$px = $cx + sin($rad) * ($R + 2);
$py = $cy - cos($rad) * ($R + 2);

// side view: person at the left, line of sight up at the planet's height
$gx = 34; $gy = 104; $eye = $gy - 30; $len = 112;
$a = deg2rad(min(88, max(2, $alt)));
$sx = $gx + 6 + cos($a) * $len;
$sy = $eye - sin($a) * $len;
$arcR = 34;
$ax = $gx + 6 + cos($a) * $arcR;
$ay = $eye - sin($a) * $arcR;
?>
<details class="findcard" data-planet="<?= e($planet) ?>"<?= !empty($open) ? ' open' : '' ?>>
  <summary>
    <span class="fc-head">
      <span class="glyph planet-<?= e($planet) ?>" aria-hidden="true"></span>
      <span class="fc-name" id="<?= $id ?>"><?= e($name) ?></span>
      <span class="fc-best mono"><?= e(Format::time($best['time'])) ?></span>
    </span>
    <span class="fc-meta mono"><?= e(t('find.meta', ['dir' => t("dir.short.$dir"), 'alt' => round($alt), 'height' => Format::height($alt)])) ?>
      · <?= e(t('find.span', ['from' => $from, 'to' => $to])) ?></span>
  </summary>

  <div class="fc-body">
  <div class="findcard-figures">
    <figure>
      <svg viewBox="0 0 128 128" role="img" aria-label="<?= e(t('find.turn_label', ['dir' => t("dir.$dir")])) ?>">
        <circle class="fc-ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R ?>"/>
        <?php foreach (['N' => [0, -1], 'E' => [1, 0], 'S' => [0, 1], 'W' => [-1, 0]] as $d => [$dx, $dy]): ?>
          <text class="fc-dir<?= $d === $dir ? ' is-target' : '' ?>" x="<?= $cx + $dx * ($R + 11) ?>" y="<?= $cy + $dy * ($R + 11) + 4 ?>"
                text-anchor="middle"><?= e(t("dir.short.$d")) ?></text>
        <?php endforeach; ?>
        <circle class="fc-me" cx="<?= $cx ?>" cy="<?= $cy ?>" r="6"/>
        <line class="fc-arrow" x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= round($tx, 1) ?>" y2="<?= round($ty, 1) ?>"/>
        <circle class="fc-planet planet-<?= e($planet) ?>" cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" r="6"/>
      </svg>
      <figcaption><?= e(t('find.turn', ['dir' => t("dir.$dir")])) ?></figcaption>
    </figure>
    <figure>
      <svg viewBox="0 0 170 128" role="img" aria-label="<?= e(t('find.look_label', ['n' => $fists, 'deg' => round($alt)])) ?>">
        <line class="fc-ground" x1="6" x2="164" y1="<?= $gy ?>" y2="<?= $gy ?>"/>
        <!-- person -->
        <circle class="fc-person" cx="<?= $gx ?>" cy="<?= $gy - 34 ?>" r="5"/>
        <line class="fc-person-line" x1="<?= $gx ?>" y1="<?= $gy - 29 ?>" x2="<?= $gx ?>" y2="<?= $gy - 12 ?>"/>
        <line class="fc-person-line" x1="<?= $gx ?>" y1="<?= $gy - 12 ?>" x2="<?= $gx - 5 ?>" y2="<?= $gy ?>"/>
        <line class="fc-person-line" x1="<?= $gx ?>" y1="<?= $gy - 12 ?>" x2="<?= $gx + 5 ?>" y2="<?= $gy ?>"/>
        <!-- horizontal and line of sight -->
        <line class="fc-level" x1="<?= $gx + 6 ?>" y1="<?= $eye ?>" x2="164" y2="<?= $eye ?>"/>
        <path class="fc-arc" d="M<?= $gx + 6 + $arcR ?> <?= $eye ?> A<?= $arcR ?> <?= $arcR ?> 0 0 0 <?= round($ax, 1) ?> <?= round($ay, 1) ?>"/>
        <line class="fc-sight" x1="<?= $gx + 6 ?>" y1="<?= $eye ?>" x2="<?= round($sx, 1) ?>" y2="<?= round(max(8, $sy), 1) ?>"/>
        <circle class="fc-planet planet-<?= e($planet) ?>" cx="<?= round($sx, 1) ?>" cy="<?= round(max(8, $sy), 1) ?>" r="6"/>
      </svg>
      <figcaption><?= e(Format::height($alt)) ?></figcaption>
    </figure>
  </div>

  <p><?= e(t('find.sentence', [
      'time'   => Format::time($best['time']),
      'dir'    => t("dir.$dir"),
      'height' => Format::height($alt),
      'planet' => $name,
      'look'   => t("look.$planet"),
  ])) ?></p>
  <p class="hint"><?= e(t(($south ? 'dirhint.s.' : 'dirhint.') . $dir)) ?></p>
  <button type="button" class="show-in-sky" data-show="<?= e($planet) ?>" hidden>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 18h18"/><circle cx="15" cy="9" r="2.5"/><path d="M15 3v2M15 13v2M9 9h2M19 9h2"/></svg>
    <?= e(t('find.show')) ?></button>
  </div>
</details>
