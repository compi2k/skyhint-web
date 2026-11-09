<?php
/**
 * The night panel: sky view, turn buttons, planner, time slider.
 * Used on the city page (sticky beside the cards) and on the start page.
 * @var array  $sky     output of SkyTrack::prepare
 * @var string $heading heading text
 * @var string $lead    text under the heading (optional)
 * @var string $more    HTML after the legend (optional)
 */
use PlanetAlerts\View;
?>
<section class="night-panel" aria-labelledby="night-title">
  <h2 id="night-title"><?= e($heading) ?></h2>
  <?php if (!empty($lead)): ?><p class="night-lead"><?= e($lead) ?></p><?php endif; ?>

  <div class="skyview" data-sky hidden>
    <div class="skyview-stage" aria-hidden="true"></div>
    <div class="skyview-turn">
      <button type="button" class="turn" data-turn="-45" aria-label="<?= e(t('night.turn_left')) ?>">‹</button>
      <p class="facing" aria-live="polite"></p>
      <button type="button" class="turn" data-turn="45" aria-label="<?= e(t('night.turn_right')) ?>">›</button>
    </div>
  </div>

  <?= View::partial('partials/planner', ['sky' => $sky]) ?>

  <div class="planner-controls" data-sky-controls hidden>
    <input id="sky-range" type="range" min="0" max="<?= count($sky['times']) - 1 ?>" step="1" value="<?= (int) $sky['start'] ?>"
           aria-label="<?= e(t('sky.slider')) ?>">
    <div class="planner-row">
      <p class="sky-time-wrap"><span class="sky-time-label"><?= e(t('sky.slider')) ?></span> <output class="sky-time" for="sky-range"></output></p>
      <button type="button" class="sky-play" aria-pressed="false"><?= e(t('sky.live.play')) ?></button>
    </div>
    <p class="sky-now"></p>
  </div>
  <p class="night-legend"><?= e(t('planner.legend')) ?></p>
  <?= $more ?? '' ?>
  <script type="application/json" id="sky-data"><?= json_encode($sky['data'],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <script src="<?= e(asset('sky.js')) ?>" defer></script>
</section>
