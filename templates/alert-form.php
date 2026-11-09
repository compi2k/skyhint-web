<?php
use PlanetAlerts\Cities;
use PlanetAlerts\Format;
use PlanetAlerts\Prefs;
$action = $alert ? '/alerts/' . (int) $alert['id'] : '/alerts';
?>
<section class="dash">
  <p class="back"><a href="/alerts"><?= e(t('alerts.back')) ?></a></p>
  <h1><?= e($title) ?></h1>
  <?php if (!$alert): ?><p class="lead"><?= e(t('alerts.new_lead')) ?></p><?php endif; ?>

  <form method="post" action="<?= e($action) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field" data-city-picker>
      <label for="city"><?= e(t('field.city')) ?></label>
      <input id="city" name="city" type="text" autocomplete="off" required minlength="2"
             value="<?= e($city ? Cities::label($city) : '') ?>"
             aria-autocomplete="list" aria-controls="city-list" role="combobox" aria-expanded="false"
             aria-describedby="city-hint">
      <input type="hidden" name="city_id" value="<?= $city ? (int) $city['id'] : '' ?>">
      <ul id="city-list" role="listbox" hidden></ul>
      <p class="hint" id="city-hint"><?= e(t('signup.city_hint')) ?></p>
    </div>

    <div class="field">
      <label for="name"><?= e(t('alerts.name')) ?> <span class="hint">(<?= e(t('alerts.optional')) ?>)</span></label>
      <input id="name" name="name" type="text" maxlength="80" value="<?= e($name) ?>"
             placeholder="<?= e(t('alerts.name_placeholder')) ?>">
    </div>

    <fieldset class="field">
      <legend><?= e(t('dash.planets')) ?></legend>
      <div class="choices">
        <?php foreach (Prefs::PLANETS as $p): ?>
          <label class="choice"><input type="checkbox" name="planets[]" value="<?= $p ?>"
            <?= in_array($p, $prefs['planets'], true) ? 'checked' : '' ?>>
            <span class="dot planet-<?= $p ?>" aria-hidden="true"></span><?= e(Format::planet($p)) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="field">
      <legend><?= e(t('dash.events')) ?></legend>
      <?php foreach (array_keys(Prefs::EVENTS) as $type): ?>
        <label class="choice choice-block"><input type="checkbox" name="events[]" value="<?= $type ?>"
          <?= in_array($type, $prefs['events'], true) ? 'checked' : '' ?>>
          <span><strong><?= e(t("type.$type")) ?></strong><br><span class="hint"><?= e(t("type.$type.desc")) ?></span></span></label>
      <?php endforeach; ?>
    </fieldset>

    <div class="row">
      <button type="submit" class="primary"><?= e(t($alert ? 'alerts.save' : 'alerts.create')) ?></button>
      <a href="/alerts"><?= e(t('alerts.cancel')) ?></a>
    </div>
  </form>
</section>
