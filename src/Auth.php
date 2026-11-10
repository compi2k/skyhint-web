<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Accounts: registration, login, tokens, passwords, deletion. */
final class Auth
{
    public const MIN_PASSWORD = 10;
    private static array|false|null $current = null;

    public static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function current(): ?array
    {
        if (self::$current === null) {
            $id = Session::userId();
            self::$current = $id ? (self::find($id) ?? false) : false;
            if ($id && self::$current === false) {
                Session::logout();
            }
        }
        return self::$current ?: null;
    }

    public static function find(int $id): ?array
    {
        return App::db()->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        $row = App::db()->one('SELECT id FROM users WHERE email = ?', [self::normalizeEmail($email)]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function validEmail(string $email): bool
    {
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Creates an unverified account with its first alert. Returns the new user id. */
    public static function register(string $email, string $password, int $cityId, string $lang): int
    {
        $db = App::db();
        $tz = $db->value('SELECT timezone FROM cities WHERE id = ?', [$cityId]);
        $db->run('INSERT INTO users (email, password_hash, lang, mail_style, mail_slot, mail_tz, unsubscribe_token, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            self::normalizeEmail($email),
            password_hash($password, self::algo()),
            $lang,
            'alerts',
            MailTime::DEFAULT_SLOT,
            MailTime::validTimezone($tz) ? $tz : null,
            bin2hex(random_bytes(16)),
            Db::now(),
        ]);
        $userId = $db->lastId();
        Alerts::create($userId, $cityId, Prefs::defaults());
        Stats::bump('signups');
        return $userId;
    }

    public static function attempt(string $email, string $password): ?array
    {
        $row = App::db()->one('SELECT id, password_hash FROM users WHERE email = ?',
            [self::normalizeEmail($email)]);
        if (!$row) {
            // spend comparable time so response timing doesn't reveal registered addresses
            password_verify($password, defined('PASSWORD_ARGON2ID')
                ? '$argon2id$v=19$m=65536,t=4,p=1$R3liSzVGcHJpd2RJV0tveA$Ey5Q3S2auHrO0nvzVFCqJ1tPsaOldLfcF64zyErIRwI'
                : '$2y$10$x9HIiDnA.XQqgOA4GLswd.gZFJkoo7uaEp7/22qD9NRFM8AaK3BZa');
            return null;
        }
        if (!password_verify($password, $row['password_hash'])) {
            return null;
        }
        if (password_needs_rehash($row['password_hash'], self::algo())) {
            self::setPassword((int) $row['id'], $password);
        }
        App::db()->run('UPDATE users SET last_login_at = ? WHERE id = ?', [Db::now(), $row['id']]);
        Stats::bump('logins');
        return self::find((int) $row['id']);
    }

    public static function setPassword(int $userId, string $password): void
    {
        App::db()->run('UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($password, self::algo()), $userId]);
    }

    /** Creates a one-time token and returns it in plain form (only the hash is stored). */
    public static function createToken(int $userId, string $type, int $ttlSeconds): string
    {
        $raw = bin2hex(random_bytes(32));
        $db = App::db();
        $db->run('DELETE FROM auth_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
        $db->run('INSERT INTO auth_tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, hash('sha256', $raw), gmdate('Y-m-d H:i:s', time() + $ttlSeconds), Db::now()]);
        return $raw;
    }

    /** Checks and deletes a token. Returns the user id or null. */
    public static function consumeToken(string $raw, string $type): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        $db = App::db();
        $row = $db->one('SELECT id, user_id FROM auth_tokens
                         WHERE token_hash = ? AND type = ? AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $raw), $type]);
        if (!$row) {
            return null;
        }
        $db->run('DELETE FROM auth_tokens WHERE id = ?', [$row['id']]);
        return (int) $row['user_id'];
    }

    public static function markVerified(int $userId): void
    {
        App::db()->run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
            [Db::now(), $userId]);
    }

    /** Deletes the account and everything attached (foreign keys cascade). */
    public static function delete(int $userId): void
    {
        App::db()->run('DELETE FROM users WHERE id = ?', [$userId]);
        Stats::bump('deletions');
    }

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return I18n::t('auth.password_short', ['n' => self::MIN_PASSWORD]);
        }
        if (strlen($password) > 1000) {
            return I18n::t('auth.password_long');
        }
        return null;
    }
}
