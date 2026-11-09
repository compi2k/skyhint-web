<?php use PlanetAlerts\App; $base = App::url('/api/v1'); ?>
<section class="prose wide">
  <h1><?= e(t('apidocs.title')) ?></h1>
  <p class="lead"><?= e(t('apidocs.lead')) ?></p>
  <p><?= e(t('apidocs.key')) ?> <a href="/dashboard#api"><?= e(t('nav.dashboard')) ?></a></p>

  <h2><?= e(t('apidocs.basics')) ?></h2>
  <ul>
    <li><?= e(t('apidocs.base')) ?> <code><?= e($base) ?></code></li>
    <li><?= e(t('apidocs.auth')) ?> <code>Authorization: Bearer pa_…</code></li>
    <li><?= e(t('apidocs.limits')) ?></li>
    <li><?= e(t('apidocs.format')) ?></li>
  </ul>
  <pre><code>curl -H "Authorization: Bearer $KEY" "<?= e($base) ?>/cities?q=tuttlingen"</code></pre>

  <h2><?= e(t('apidocs.endpoints')) ?></h2>
  <table class="plain">
    <thead><tr><th><?= e(t('apidocs.col_call')) ?></th><th><?= e(t('apidocs.col_returns')) ?></th></tr></thead>
    <tbody>
      <tr><td><code>GET /cities?q=&amp;country=</code></td><td><?= e(t('apidocs.ep.cities')) ?></td></tr>
      <tr><td><code>GET /sky/tonight?city=</code></td><td><?= e(t('apidocs.ep.tonight')) ?></td></tr>
      <tr><td><code>GET /events?city=&amp;from=&amp;to=&amp;types=</code></td><td><?= e(t('apidocs.ep.events')) ?></td></tr>
      <tr><td><code>GET /events.ics?key=&amp;alert=&amp;lang=</code></td><td><?= e(t('apidocs.ep.ics')) ?></td></tr>
      <tr><td><code>GET /me</code></td><td><?= e(t('apidocs.ep.me')) ?></td></tr>
      <tr><td><code>PATCH /me</code></td><td><?= e(t('apidocs.ep.patch')) ?></td></tr>
      <tr><td><code>GET /alerts</code></td><td><?= e(t('apidocs.ep.alerts')) ?></td></tr>
      <tr><td><code>POST /alerts</code></td><td><?= e(t('apidocs.ep.alert_new')) ?></td></tr>
      <tr><td><code>PATCH /alerts/{id}</code></td><td><?= e(t('apidocs.ep.alert_patch')) ?></td></tr>
      <tr><td><code>DELETE /alerts/{id}</code></td><td><?= e(t('apidocs.ep.alert_delete')) ?></td></tr>
    </tbody>
  </table>

  <h2><?= e(t('apidocs.types')) ?></h2>
  <p><code>comeback</code>, <code>last_chance</code>, <code>mercury_window</code>, <code>opposition</code>,
     <code>greatest_elongation</code>, <code>planet_pair</code>, <code>moon_nearby</code>, <code>parade</code></p>

  <h2><?= e(t('apidocs.example')) ?></h2>
  <pre><code>curl -X PATCH -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"planets":["venus","jupiter"],"enabled":true}' "<?= e($base) ?>/alerts/12"</code></pre>

  <h2><?= e(t('apidocs.errors')) ?></h2>
  <pre><code>{"error": {"code": "rate_limited", "message": "Limit: 60 requests per minute."}}</code></pre>
  <p><?= e(t('apidocs.errors_text')) ?></p>
</section>
