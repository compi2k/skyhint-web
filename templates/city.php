<?php
use PlanetAlerts\Cities;
use PlanetAlerts\Format;
use PlanetAlerts\View;

$visible = array_filter($night['planets'], static fn ($p) => $p['visible'] && !empty($p['best']));
// cards in order of the best time, so the evening planets come first
uasort($visible, static fn ($a, $b) => strcmp(
    (new DateTimeImmutable($a['best']['time']))->format('c'), (new DateTimeImmutable($b['best']['time']))->format('c')));
$south = (float) $city['latitude'] < 0;
?>
<section class="hero hero-city">
  <div class="hero-text wrap">
    <h1><?= e(t('city.heading', ['city' => $city['name']])) ?></h1>
    <p class="lead">
      <?= e(Cities::label($city)) ?><br>
      <?php if ($night['dark']): ?>
        <?= e(t('city.dark', ['date' => Format::date($night['date']),
                               'start' => Format::time($night['dark'][0]['start']),
                               'end' => Format::time($night['dark'][count($night['dark']) - 1]['end'])])) ?>
      <?php else: ?>
        <?= e(t('city.no_dark', ['date' => Format::date($night['date'])])) ?>
      <?php endif; ?>
    </p>
  </div>
</section>

<div class="city-layout wrap">
  <section class="city-cards">
    <h2><?= e($visible ? t('find.title', ['n' => count($visible)]) : t('city.none_title')) ?></h2>
    <?php if ($visible): ?>
      <div class="findcards">
        <?php foreach ($visible as $name => $p): ?>
          <?= View::partial('partials/findcard', ['planet' => $name, 'vis' => $p, 'south' => $south]) ?>
        <?php endforeach; ?>
      </div>
      <p class="quiet"><?= e(t('find.twinkle')) ?></p>
    <?php else: ?>
      <p><?= e(t('city.none_text')) ?></p>
    <?php endif; ?>
    <?php $hidden = array_diff($planets, array_keys($visible)); if ($hidden && $visible): ?>
      <p class="quiet"><?= e(t('city.not_tonight', ['planets' => Format::planets(array_values($hidden))])) ?></p>
    <?php endif; ?>
    <p class="quiet"><?= e(Format::moon($night['moon'])) ?></p>
  </section>

  <div class="city-night">
    <?= View::partial('partials/nightpanel', ['sky' => $sky, 'heading' => t('night.title'), 'lead' => t('night.lead')]) ?>
  </div>

  <section class="city-events">
    <h2><?= e(t('city.events_title', ['n' => $days])) ?></h2>
    <?php if ($events): ?>
      <ul class="events">
        <?php foreach ($events as $ev): ?>
          <li>
            <time datetime="<?= e($ev['date']) ?>"><?= e(Format::date($ev['date'])) ?></time>
            <span><?= e(Format::eventTitle($ev)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p><?= e(t('city.no_events')) ?></p>
    <?php endif; ?>

    <div class="callout">
      <p><?= e(t('city.cta_text', ['city' => $city['name']])) ?></p>
      <?php if (\PlanetAlerts\Auth::current()): ?>
        <a class="button primary" href="/alerts/new?city=<?= (int) $city['id'] ?>"><?= e(t('city.cta_add')) ?></a>
      <?php else: ?>
        <a class="button primary" href="/signup?city=<?= (int) $city['id'] ?>"><?= e(t('city.cta_button')) ?></a>
      <?php endif; ?>
    </div>
  </section>
</div>
