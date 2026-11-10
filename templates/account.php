<?php
use PlanetAlerts\App;
use PlanetAlerts\Format;
use PlanetAlerts\Prefs;
$verified = $u['email_verified_at'] !== null;
?>
<section class="dash">
  <h1><?= e(t('account.heading')) ?></h1>
  <p class="lead"><?= e(t('account.lead', ['email' => $u['email']])) ?></p>

  <h2 id="mails"><?= e(t('account.mails')) ?></h2>
  <form method="post" action="/account/settings" class="stack">
    <?= csrf_field() ?>
    <fieldset class="field">
      <legend><?= e(t('dash.style')) ?></legend>
      <?php foreach (Prefs::STYLES as $s): ?>
        <label class="choice choice-block"><input type="radio" name="style" value="<?= $s ?>"
          <?= $style === $s ? 'checked' : '' ?>>
          <span><strong><?= e(t("style.$s")) ?></strong><br><span class="hint"><?= e(t("style.$s.desc")) ?></span></span></label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="field">
      <legend><?= e(t('dash.slot')) ?></legend>
      <p class="hint"><?= e(t('dash.slot_hint')) ?></p>
      <?php foreach (array_keys(\PlanetAlerts\MailTime::SLOTS) as $sl): ?>
        <label class="choice"><input type="radio" name="mail_slot" value="<?= $sl ?>" <?= $slot === $sl ? 'checked' : '' ?>>
          <?= e(ucfirst(\PlanetAlerts\MailTime::label($sl))) ?></label>
      <?php endforeach; ?>
      <p class="hint"><?= e(t('slot.evening.note')) ?></p>
    </fieldset>

    <div class="field">
      <label for="mail_tz"><?= e(t('dash.tz')) ?></label>
      <select id="mail_tz" name="mail_tz">
        <?php if ($placeZones): ?>
          <optgroup label="<?= e(t('dash.tz_places')) ?>">
            <?php foreach ($placeZones as $z): ?>
              <option value="<?= e($z) ?>" <?= $tz === $z ? 'selected' : '' ?>><?= e(\PlanetAlerts\MailTime::zoneLabel($z)) ?> (<?= e($z) ?>)</option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
        <?php foreach (\PlanetAlerts\MailTime::zonesGrouped() as $region => $zones): ?>
          <optgroup label="<?= e($region) ?>">
            <?php foreach ($zones as $z => $name): ?>
              <option value="<?= e($z) ?>" <?= $tz === $z && !in_array($z, $placeZones, true) ? 'selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="lang"><?= e(t('field.lang')) ?></label>
      <select id="lang" name="lang">
        <?php foreach (\PlanetAlerts\I18n::languages() as $code => $name): ?>
          <option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $u['lang'] === $code ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <label class="choice"><input type="checkbox" name="mail_enabled" value="1" <?= $u['mail_enabled'] ? 'checked' : '' ?>>
      <?= e(t('dash.mail_enabled')) ?></label>

    <button type="submit" class="primary"><?= e(t('dash.save')) ?></button>
  </form>

  <h2 id="pause"><?= e(t('dash.pause_title')) ?></h2>
  <?php if ($paused): ?>
    <div class="notice" role="status">
      <p><?= e(t('dash.paused_until', ['date' => Format::date($u['paused_until'])])) ?></p>
      <form method="post" action="/account/pause"><?= csrf_field() ?>
        <input type="hidden" name="resume" value="1">
        <button type="submit"><?= e(t('dash.resume')) ?></button></form>
    </div>
  <?php endif; ?>
  <p><?= e(t('dash.pause_text')) ?></p>
  <form method="post" action="/account/pause" class="row">
    <?= csrf_field() ?>
    <label for="until" class="sr-only"><?= e(t('dash.pause_until')) ?></label>
    <input id="until" name="until" type="date" required min="<?= gmdate('Y-m-d') ?>"
           max="<?= gmdate('Y-m-d', strtotime('+1 year')) ?>">
    <button type="submit"><?= e(t('dash.pause_button')) ?></button>
  </form>
  <form method="post" action="/account/test-mail" class="row">
    <?= csrf_field() ?>
    <button type="submit"><?= e(t('dash.test_button')) ?></button>
  </form>

  <h2 id="api"><?= e(t('dash.api_title')) ?></h2>
  <p><?= e(t('dash.api_text')) ?> <a href="/api"><?= e(t('dash.api_docs')) ?></a></p>
  <?php if ($newKey): ?>
    <div class="notice notice-key" role="status">
      <p><?= e(t('dash.key_once')) ?></p>
      <p><code class="key"><?= e($newKey) ?></code></p>
      <p class="hint"><?= e(t('dash.ics_hint')) ?></p>
      <p><code class="key"><?= e(App::url('/api/v1/events.ics', ['key' => $newKey])) ?></code></p>
    </div>
  <?php endif; ?>
  <?php if ($keys): ?>
    <table class="keys">
      <thead><tr><th><?= e(t('dash.key_name')) ?></th><th><?= e(t('dash.key_start')) ?></th>
        <th><?= e(t('dash.key_used')) ?></th><th><span class="sr-only"><?= e(t('dash.key_revoke')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($keys as $k): ?>
        <tr>
          <td><?= e($k['name']) ?></td>
          <td><code><?= e($k['prefix']) ?>…</code></td>
          <td><?= $k['last_used_at'] ? e(Format::date(substr($k['last_used_at'], 0, 10))) : e(t('dash.key_never')) ?></td>
          <td><form method="post" action="/account/keys/<?= (int) $k['id'] ?>/revoke"
                    data-confirm="<?= e(t('dash.key_revoke_confirm')) ?>"><?= csrf_field() ?>
            <button type="submit" class="link danger"><?= e(t('dash.key_revoke')) ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <form method="post" action="/account/keys" class="row">
    <?= csrf_field() ?>
    <label for="key-name" class="sr-only"><?= e(t('dash.key_name')) ?></label>
    <input id="key-name" name="name" type="text" maxlength="80" placeholder="<?= e(t('dash.key_placeholder')) ?>">
    <button type="submit" <?= $verified ? '' : 'disabled' ?>><?= e(t('dash.key_create')) ?></button>
  </form>

  <h2 id="account"><?= e(t('dash.account')) ?></h2>
  <form method="post" action="/account/password" class="stack narrow">
    <?= csrf_field() ?>
    <div class="field">
      <label for="current"><?= e(t('field.current_password')) ?></label>
      <input id="current" name="current" type="password" autocomplete="current-password" required>
    </div>
    <div class="field">
      <label for="new-password"><?= e(t('field.new_password')) ?></label>
      <input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="10">
    </div>
    <button type="submit"><?= e(t('dash.password_button')) ?></button>
  </form>

  <form method="post" action="/account/delete" class="stack narrow danger-zone"
        data-confirm="<?= e(t('dash.delete_confirm')) ?>">
    <?= csrf_field() ?>
    <h3><?= e(t('dash.delete_title')) ?></h3>
    <p><?= e(t('dash.delete_text')) ?></p>
    <div class="field">
      <label for="delete-password"><?= e(t('field.password')) ?></label>
      <input id="delete-password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="danger"><?= e(t('dash.delete_button')) ?></button>
  </form>
</section>
