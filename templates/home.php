<?php
use PlanetAlerts\Cities;
use PlanetAlerts\Format;
use PlanetAlerts\View;

$n = $glance ? count($glance['visible']) : null;
?>
<section class="hero hero-home">
  <div class="wrap hero-grid<?= empty($sky) ? ' no-sky' : '' ?>">
    <div class="hero-text">
      <?php if (!empty($week)): ?>
        <p class="eyebrow mono"><span class="eyebrow-dot" aria-hidden="true"></span><?= e(t('home.eyebrow', [
            'city' => $week['name'], 'coords' => Format::coords((float) $week['latitude'], (float) $week['longitude'])])) ?></p>
        <h1><?= e($n === 0 ? t('home.h1.none') : ($n === 1 ? t('home.h1.one') : t('home.h1.many', ['n' => $n]))) ?><br>
          <span class="accent-line"><?= e($n === 0 ? t('home.h1.sub_none') : t('home.h1.sub')) ?></span></h1>
      <?php else: ?>
        <h1><?= e(t('home.title')) ?></h1>
      <?php endif; ?>
      <p class="lead"><?= e(t('home.lead2')) ?></p>
      <form class="city-search" method="get" action="/find" role="search">
        <label for="city"><?= e(t('home.city_label')) ?></label>
        <div class="search-row" data-city-picker>
          <input id="city" name="city" type="text" autocomplete="off" required minlength="2"
                 placeholder="<?= e(t('home.city_placeholder')) ?>" value="<?= e($notFound ?? '') ?>"
                 aria-autocomplete="list" aria-controls="city-list" role="combobox" aria-expanded="false">
          <input type="hidden" name="city_id" value="">
          <ul id="city-list" role="listbox" hidden></ul>
          <button type="submit" class="primary compute"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.2"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3"/></svg><?= e(t('home.city_button')) ?></button>
        </div>
        <?php if (!empty($notFound)): ?>
          <p class="field-error" role="alert"><?= e(t('home.not_found', ['q' => $notFound])) ?></p>
        <?php endif; ?>
      </form>
      <ul class="facts mono">
        <?php for ($i = 1; $i <= 3; $i++): ?><li><?= e(t("home.fact_$i")) ?></li><?php endfor; ?>
      </ul>
    </div>

    <?php if (!empty($sky)): ?>
      <div class="home-night">
        <?= View::partial('partials/nightpanel', [
            'sky'     => $sky,
            'heading' => t('home.week_title', ['city' => $week['name']]),
            'lead'    => Cities::label($week),
            'more'    => '<p class="week-link"><a href="/city/' . (int) $week['id'] . '">'
                         . e(t('home.week_link', ['city' => $week['name']])) . ' →</a></p>',
        ]) ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($glance): ?>
  <div class="wrap">
    <?= View::partial('partials/glance', $glance + ['next' => null]) ?>
  </div>
<?php endif; ?>

<section class="wrap how">
  <h2><?= e(t('home.how_title')) ?></h2>
  <ol class="steps">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <li>
        <p class="step-n mono">0<?= $i ?> / <?= e(t("home.step_$i")) ?></p>
        <p><?= e(t("home.how_$i")) ?></p>
      </li>
    <?php endfor; ?>
  </ol>
  <div class="how-bottom">
    <figure class="mail-preview">
      <figcaption class="mono"><?= e(t('home.preview_label')) ?></figcaption>
      <div class="mail-card">
        <p class="mail-from"><?= e(\PlanetAlerts\App::name()) ?></p>
        <p class="mail-subject"><?= e(t('home.preview_subject')) ?></p>
        <p class="mail-body"><?= e(t('home.preview_text')) ?></p>
      </div>
    </figure>
    <p class="how-cta"><a class="button primary" href="/signup"><?= e(t('home.signup')) ?></a></p>
  </div>
</section>
