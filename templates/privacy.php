<?php use PlanetAlerts\App; ?>
<section class="prose">
  <h1><?= e(t('privacy.title')) ?></h1>
  <p class="lead"><?= e(t('privacy.lead')) ?></p>

  <h2><?= e(t('privacy.stored_title')) ?></h2>
  <table class="plain">
    <thead><tr><th><?= e(t('privacy.col_what')) ?></th><th><?= e(t('privacy.col_why')) ?></th></tr></thead>
    <tbody>
      <?php foreach (['email', 'password', 'city', 'prefs', 'sent', 'keys', 'login', 'ip'] as $k): ?>
        <tr><td><?= e(t("privacy.row.$k")) ?></td><td><?= e(t("privacy.row.$k.why")) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2><?= e(t('privacy.not_title')) ?></h2>
  <p><?= e(t('privacy.not_text')) ?></p>

  <h2><?= e(t('privacy.cookies_title')) ?></h2>
  <p><?= e(t('privacy.cookies_text')) ?></p>

  <h2><?= e(t('privacy.servers_title')) ?></h2>
  <p><?= e(t('privacy.servers_text')) ?></p>

  <h2><?= e(t('privacy.delete_title')) ?></h2>
  <p><?= e(t('privacy.delete_text')) ?></p>

  <h2><?= e(t('privacy.rights_title')) ?></h2>
  <p><?= e(t('privacy.rights_text', ['email' => App::config('imprint.email')])) ?></p>
  <p class="quiet"><?= e(t('privacy.controller')) ?> <a href="/imprint"><?= e(t('imprint.title')) ?></a></p>
</section>
