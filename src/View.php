<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Renders templates/*.php inside templates/layout.php. */
final class View
{
    public static function render(string $template, array $vars = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $content = self::partial($template, $vars);
        echo self::partial('layout', $vars + [
            'content' => $content,
            'flash'   => Session::takeFlash(),
            'user'    => Auth::current(),
        ]);
        exit;
    }

    public static function partial(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require APP_ROOT . "/templates/$template.php";
        return (string) ob_get_clean();
    }

    public static function notFound(): never
    {
        self::render('message', [
            'title' => I18n::t('error.not_found_title'),
            'text'  => I18n::t('error.not_found_text'),
        ], 404);
    }
}
