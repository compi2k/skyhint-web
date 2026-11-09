<section class="form-page">
  <h1><?= e(t('login.title')) ?></h1>
  <form method="post" action="/login" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= e(t('field.email')) ?></label>
      <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password"><?= e(t('field.password')) ?></label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="primary"><?= e(t('login.button')) ?></button>
  </form>
  <p class="quiet"><a href="/forgot"><?= e(t('login.forgot')) ?></a></p>
  <p class="quiet"><?= e(t('login.no_account')) ?> <a href="/signup"><?= e(t('nav.signup')) ?></a></p>
</section>
