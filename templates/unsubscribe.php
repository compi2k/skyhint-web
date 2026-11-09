<section class="form-page">
  <h1><?= e(t('unsub.title')) ?></h1>
  <?php if ($done): ?>
    <p class="lead"><?= e(t('unsub.done')) ?></p>
    <p><a href="/login"><?= e(t('unsub.login')) ?></a></p>
  <?php else: ?>
    <p class="lead"><?= e(t('unsub.lead')) ?></p>
    <form method="post" action="/unsubscribe?u=<?= (int) $u ?>&amp;t=<?= e($t) ?>">
      <button type="submit" class="primary"><?= e(t('unsub.button')) ?></button>
    </form>
  <?php endif; ?>
</section>
