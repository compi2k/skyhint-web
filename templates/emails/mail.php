<?php
use PlanetAlerts\App;
use PlanetAlerts\I18n;
/** @var array $m subject, heading, paragraphs, button, blocks, after, unsubscribe_url, settings_url */
$ink = '#1D2333'; $quiet = '#5B6478'; $dusk = '#23305C'; $gold = '#E3A92B';
?>
<!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($m['subject']) ?></title></head>
<body style="margin:0;padding:0;background:#EEF1F7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF1F7;">
<tr><td align="center" style="padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:6px;">
    <tr><td style="background:<?= $dusk ?>;border-radius:6px 6px 0 0;padding:18px 28px 14px;">
      <span style="font-family:Charter,'Bitstream Charter','Sitka Text',Cambria,Georgia,serif;font-size:17px;color:#ffffff;">
        <span style="color:<?= $gold ?>;">&#9679;</span>&nbsp;<?= e(App::name()) ?></span>
    </td></tr>
    <tr><td style="padding:28px 28px 8px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.55;color:<?= $ink ?>;">
      <h1 style="font-family:Charter,'Bitstream Charter','Sitka Text',Cambria,Georgia,serif;font-weight:normal;font-size:24px;line-height:1.25;margin:0 0 16px;color:<?= $ink ?>;"><?= e($m['heading']) ?></h1>
      <?php foreach ($m['paragraphs'] ?? [] as $p): ?>
        <p style="margin:0 0 14px;"><?= e($p) ?></p>
      <?php endforeach; ?>
      <?php if (!empty($m['button'])): ?>
        <p style="margin:22px 0;"><a href="<?= e($m['button']['url']) ?>"
          style="display:inline-block;background:<?= $gold ?>;color:<?= $ink ?>;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:4px;"><?= e($m['button']['label']) ?></a></p>
        <p style="margin:0 0 14px;font-size:13px;color:<?= $quiet ?>;word-break:break-all;"><?= e($m['button']['url']) ?></p>
      <?php endif; ?>
      <?php foreach ($m['sections'] ?? [] as $sec): ?>
        <?php if ($sec['title'] !== ''): ?>
          <h2 style="font-family:Charter,'Bitstream Charter','Sitka Text',Cambria,Georgia,serif;font-weight:normal;font-size:19px;margin:26px 0 12px;padding-bottom:6px;border-bottom:1px solid #E2E6EE;color:<?= $ink ?>;"><?= e($sec['title']) ?></h2>
        <?php endif; ?>
        <?php foreach ($sec['blocks'] as $b): ?>
          <div style="border-left:3px solid <?= $gold ?>;padding:2px 0 2px 14px;margin:0 0 20px;">
            <p style="margin:0 0 6px;font-weight:600;"><?= e($b['title']) ?></p>
            <?php foreach ($b['lines'] as $l): ?>
              <p style="margin:0 0 6px;color:<?= $ink ?>;"><?= e($l) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <?php foreach ($sec['after'] ?? [] as $p): ?>
          <p style="margin:0 0 12px;color:<?= $quiet ?>;font-size:14px;"><?= e($p) ?></p>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php foreach ($m['after'] ?? [] as $p): ?>
        <p style="margin:0 0 12px;color:<?= $quiet ?>;font-size:14px;"><?= e($p) ?></p>
      <?php endforeach; ?>
    </td></tr>
    <tr><td style="padding:16px 28px 24px;border-top:1px solid #E2E6EE;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:<?= $quiet ?>;">
      <a href="<?= e(App::url('/')) ?>" style="color:<?= $quiet ?>;"><?= e(App::name()) ?></a>
      <?php if (!empty($m['unsubscribe_url'])): ?>
        &nbsp;&nbsp;<a href="<?= e($m['settings_url']) ?>" style="color:<?= $quiet ?>;"><?= e(t('mail.footer.settings')) ?></a>
        &nbsp;&nbsp;<a href="<?= e($m['unsubscribe_url']) ?>" style="color:<?= $quiet ?>;"><?= e(t('mail.footer.unsubscribe')) ?></a>
      <?php endif; ?>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
