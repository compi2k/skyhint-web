<section class="form-page">
  <h1><?= e(t('reset.title')) ?></h1>
  <form method="post" action="/reset" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field">
      <label for="password"><?= e(t('field.new_password')) ?></label>
      <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10"
             aria-describedby="pw-hint">
      <p class="hint" id="pw-hint"><?= e(t('auth.password_short', ['n' => 10])) ?></p>
    </div>
    <button type="submit" class="primary"><?= e(t('reset.button')) ?></button>
  </form>
</section>
