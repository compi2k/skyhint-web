<?php
// Legal notice (Impressum) from the 'imprint' section of config.php.
// Optional keys: 'telephone' (or 'phone') and 'extra' (free text, e.g. VAT ID or register entry).
$phone = trim((string) ($imprint['telephone'] ?? $imprint['phone'] ?? ''));
$extra = trim((string) ($imprint['extra'] ?? ''));
?>
<section class="prose">
  <h1><?= e(t('imprint.title')) ?></h1>
  <p><?= e(t('imprint.responsible')) ?></p>
  <p><?= e($imprint['name'] ?? '') ?><br><?= nl2br(e($imprint['address'] ?? '')) ?></p>
  <p>
    <?= e(t('field.email')) ?>: <a href="mailto:<?= e($imprint['email'] ?? '') ?>"><?= e($imprint['email'] ?? '') ?></a>
    <?php if ($phone !== ''): ?>
      <br><?= e(t('field.phone')) ?>: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
    <?php endif; ?>
  </p>
  <?php if ($extra !== ''): ?><p><?= nl2br(e($extra)) ?></p><?php endif; ?>
</section>
