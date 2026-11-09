<?php
use PlanetAlerts\Cities;
use PlanetAlerts\I18n;
$cityLabel = $city ? Cities::label($city) : ($old['city'] ?? '');
$cityId = $city['id'] ?? ($old['city_id'] ?? '');
$lang = $old['lang'] ?? I18n::lang();
?>
<section class="form-page">
  <h1><?= e(t('signup.title')) ?></h1>
  <p class="lead"><?= e(t('signup.lead')) ?></p>
  <form method="post" action="/signup" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= e(t('field.email')) ?></label>
      <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password"><?= e(t('field.password')) ?></label>
      <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10"
             aria-describedby="pw-hint">
      <p class="hint" id="pw-hint"><?= e(t('auth.password_short', ['n' => 10])) ?></p>
    </div>
    <div class="field" data-city-picker>
      <label for="city"><?= e(t('field.city')) ?></label>
      <input id="city" name="city" type="text" autocomplete="off" required minlength="2" value="<?= e($cityLabel) ?>"
             aria-autocomplete="list" aria-controls="city-list" role="combobox" aria-expanded="false"
             aria-describedby="city-hint">
      <input type="hidden" name="city_id" value="<?= e((string) $cityId) ?>">
      <ul id="city-list" role="listbox" hidden></ul>
      <p class="hint" id="city-hint"><?= e(t('signup.city_hint')) ?></p>
    </div>
    <div class="field">
      <label for="lang"><?= e(t('field.lang')) ?></label>
      <select id="lang" name="lang">
        <?php foreach (\PlanetAlerts\I18n::languages() as $code => $name): ?>
          <option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $lang === $code ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="choice">
      <input type="checkbox" name="privacy" value="1" required>
      <span><?= t('signup.privacy', ['link' => '<a href="/privacy" target="_blank">' . e(t('nav.privacy')) . '</a>']) ?></span>
    </label>
    <button type="submit" class="primary"><?= e(t('signup.button')) ?></button>
  </form>
  <p class="quiet"><?= e(t('signup.have_account')) ?> <a href="/login"><?= e(t('nav.login')) ?></a></p>
</section>
