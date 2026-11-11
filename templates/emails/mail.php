<?php
use PlanetAlerts\App;
use PlanetAlerts\I18n;
/** @var array $m subject, heading, paragraphs, button, blocks, after, unsubscribe_url, settings_url */
$ink = '#1C2033'; $quiet = '#4A5068'; $night = '#0B1130'; $amber = '#F2B33D'; $amberInk = '#1C1300'; $line = '#E1E4EE';
$sans = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"; $mono = "ui-monospace,'SF Mono',Menlo,Consolas,monospace";
?>
<!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($m['subject']) ?></title></head>
<body style="margin:0;padding:0;background:#F4F5FA;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F5FA;">
<tr><td align="center" style="padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid <?= $line ?>;">
    <tr><td style="background:<?= $night ?>;border-radius:16px 16px 0 0;padding:16px 28px;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td style="padding-right:10px;line-height:0;"><img src="<?= e(App::url('/assets/mail-logo.png')) ?>" width="28" height="28" alt="" style="display:block;border:0;"></td>
        <td style="font-family:<?= $sans ?>;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-0.01em;"><?= e(App::name()) ?></td>
      </tr></table>
    </td></tr>
    <tr><td style="padding:28px 28px 8px;font-family:<?= $sans ?>;font-size:16px;line-height:1.55;color:<?= $ink ?>;">
      <h1 style="font-family:<?= $sans ?>;font-weight:700;font-size:23px;line-height:1.25;letter-spacing:-0.01em;margin:0 0 16px;color:<?= $ink ?>;"><?= e($m['heading']) ?></h1>
      <?php foreach ($m['paragraphs'] ?? [] as $p): ?>
        <p style="margin:0 0 14px;"><?= e($p) ?></p>
      <?php endforeach; ?>
      <?php if (!empty($m['button'])): ?>
        <p style="margin:22px 0;"><a href="<?= e($m['button']['url']) ?>"
          style="display:inline-block;background:<?= $amber ?>;color:<?= $amberInk ?>;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;"><?= e($m['button']['label']) ?></a></p>
        <p style="margin:0 0 14px;font-size:13px;color:<?= $quiet ?>;word-break:break-all;"><?= e($m['button']['url']) ?></p>
      <?php endif; ?>
      <?php foreach ($m['sections'] ?? [] as $sec): ?>
        <?php if ($sec['title'] !== ''): ?>
          <h2 style="font-family:<?= $sans ?>;font-weight:700;font-size:18px;margin:26px 0 12px;padding-bottom:6px;border-bottom:1px solid <?= $line ?>;color:<?= $ink ?>;"><?= e($sec['title']) ?></h2>
        <?php endif; ?>
        <?php foreach ($sec['blocks'] as $b): ?>
          <div style="background:#FFF8E8;border:1px solid #F2DDA0;border-radius:12px;padding:14px 16px;margin:0 0 14px;">
            <p style="margin:0 0 6px;font-weight:700;"><?= e($b['title']) ?></p>
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
    <tr><td style="padding:16px 28px 24px;border-top:1px solid <?= $line ?>;font-family:<?= $sans ?>;font-size:13px;line-height:1.5;color:<?= $quiet ?>;">
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
