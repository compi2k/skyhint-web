<section class="prose">
  <h1><?= e(t('imprint.title')) ?></h1>
  <p><?= e(t('imprint.responsible')) ?></p>
  <p><?= e($imprint['name'] ?? '') ?><br><?= nl2br(e($imprint['address'] ?? '')) ?></p>
  <p><?= e(t('field.email')) ?>: <?= e($imprint['email'] ?? '') ?></p>
</section>
