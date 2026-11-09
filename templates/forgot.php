<section class="form-page">
  <h1><?= e(t('forgot.title')) ?></h1>
  <p class="lead"><?= e(t('forgot.lead')) ?></p>
  <form method="post" action="/forgot" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= e(t('field.email')) ?></label>
      <input id="email" name="email" type="email" autocomplete="email" required>
    </div>
    <button type="submit" class="primary"><?= e(t('forgot.button')) ?></button>
  </form>
</section>
