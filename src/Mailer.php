<?php
declare(strict_types=1);

namespace PlanetAlerts;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/** Sends mail through the configured SMTP server (or PHP mail()). */
final class Mailer
{
    /**
     * @param array $unsubscribe ['url' => one-click URL] adds List-Unsubscribe headers
     */
    public static function send(string $to, string $subject, string $html, string $text,
                                array $unsubscribe = []): bool
    {
        $cfg = App::config('mail');
        $m = new PHPMailer(true);
        try {
            if (($cfg['transport'] ?? 'smtp') === 'smtp') {
                $m->isSMTP();
                $m->Host = $cfg['host'];
                $m->Port = (int) $cfg['port'];
                $m->SMTPAutoTLS = ($cfg['secure'] ?? '') !== '';
                if (!empty($cfg['secure'])) {
                    $m->SMTPSecure = $cfg['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                }
                if (!empty($cfg['user'])) {
                    $m->SMTPAuth = true;
                    $m->Username = $cfg['user'];
                    $m->Password = $cfg['password'];
                }
                $m->Timeout = 20;
            } else {
                $m->isMail();
            }
            $m->CharSet = PHPMailer::CHARSET_UTF8;
            $m->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $m->setFrom($cfg['from'], $cfg['from_name'] ?? App::name());
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body = $html;
            $m->AltBody = $text;
            $m->XMailer = ' ';
            if (!empty($unsubscribe['url'])) {
                $m->addCustomHeader('List-Unsubscribe', '<' . $unsubscribe['url'] . '>');
                $m->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
            $m->send();
            Stats::bump('mails_total');
            return true;
        } catch (MailException $e) {
            App::log('mail to ' . $to . ' failed: ' . $m->ErrorInfo);
            Stats::bump('mails_failed');
            return false;
        }
    }
}
