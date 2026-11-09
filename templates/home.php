<?php
use PlanetAlerts\Cities;
use PlanetAlerts\View;
?>
<section class="hero hero-home">
  <div class="wrap hero-grid<?= empty($sky) ? ' no-sky' : '' ?>">
    <div class="hero-text">
      <h1><?= e(t('home.title')) ?></h1>
      <p class="lead"><?= e(t('home.lead')) ?></p>
      <form class="city-search" method="get" action="/find" role="search">
        <label for="city"><?= e(t('home.city_label')) ?></label>
        <div class="search-row" data-city-picker>
          <input id="city" name="city" type="text" autocomplete="off" required minlength="2"
                 placeholder="<?= e(t('home.city_placeholder')) ?>" value="<?= e($notFound ?? '') ?>"
                 aria-autocomplete="list" aria-controls="city-list" role="combobox" aria-expanded="false">
          <input type="hidden" name="city_id" value="">
          <ul id="city-list" role="listbox" hidden></ul>
          <button type="submit" class="primary"><?= e(t('home.city_button')) ?></button>
        </div>
        <?php if (!empty($notFound)): ?>
          <p class="field-error" role="alert"><?= e(t('home.not_found', ['q' => $notFound])) ?></p>
        <?php endif; ?>
      </form>
      <ul class="facts">
        <?php for ($n = 1; $n <= 3; $n++): ?>
          <li><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8.5l3 3 7-7"/></svg><?= e(t("home.fact_$n")) ?></li>
        <?php endfor; ?>
      </ul>
    </div>

    <?php if (!empty($sky)): ?>
      <div class="home-night">
        <?= View::partial('partials/nightpanel', [
            'sky'     => $sky,
            'heading' => t('home.week_title', ['city' => $week['name']]),
            'lead'    => Cities::label($week) . '. ' . t('home.week_lead'),
            'more'    => '<p class="week-link"><a href="/city/' . (int) $week['id'] . '">'
                         . e(t('home.week_link', ['city' => $week['name']])) . ' →</a></p>',
        ]) ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="wrap how">
  <h2><?= e(t('home.how_title')) ?></h2>
  <ol class="steps">
    <?php for ($n = 1; $n <= 3; $n++): ?>
      <li>
        <span class="step-n" aria-hidden="true"><?= $n ?></span>
        <p><?= e(t("home.how_$n")) ?></p>
      </li>
    <?php endfor; ?>
  </ol>

  <div class="how-bottom">
    <figure class="mail-preview">
      <figcaption><?= e(t('home.preview_label')) ?></figcaption>
      <div class="mail-card">
        <p class="mail-from"><?= e(\PlanetAlerts\App::name()) ?></p>
        <p class="mail-subject"><?= e(t('home.preview_subject')) ?></p>
        <p class="mail-body"><?= e(t('home.preview_text')) ?></p>
      </div>
    </figure>
    <p class="how-cta"><a class="button primary" href="/signup"><?= e(t('home.signup')) ?></a></p>
  </div>
</section>
