<?php
/**
 * Ranked horizontal bars with the number written next to each bar.
 * @var string $title
 * @var array  $rows   list of ['label' => string, 'value' => int, 'dot' => optional planet name]
 */
$max = max(1, max(array_column($rows, 'value') ?: [0]));
?>
<figure class="hbars">
  <figcaption><?= e($title) ?></figcaption>
  <ul>
    <?php foreach ($rows as $r): ?>
      <li>
        <span class="hb-label"><?php if (!empty($r['dot'])): ?><span class="dot planet-<?= e($r['dot']) ?>" aria-hidden="true"></span><?php endif; ?><?= e($r['label']) ?></span>
        <span class="hb-track" aria-hidden="true"><span class="hb-bar hb-w<?= (int) round($r['value'] / $max * 20) ?>"></span></span>
        <span class="hb-value"><?= (int) $r['value'] ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</figure>
