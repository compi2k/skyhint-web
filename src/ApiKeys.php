<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** API keys: shown once on creation, stored only as a hash. */
final class ApiKeys
{
    public const MAX_PER_USER = 5;

    /** Returns the full key; the caller must show it to the user once. */
    public static function create(int $userId, string $name): string
    {
        $key = 'pa_' . bin2hex(random_bytes(20));
        App::db()->run('INSERT INTO api_keys (user_id, name, prefix, key_hash, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, mb_substr($name, 0, 80), substr($key, 0, 12), hash('sha256', $key), Db::now()]);
        return $key;
    }

    public static function forUser(int $userId): array
    {
        return App::db()->all('SELECT id, name, prefix, created_at, last_used_at FROM api_keys
                               WHERE user_id = ? AND revoked_at IS NULL ORDER BY created_at DESC', [$userId]);
    }

    public static function countActive(int $userId): int
    {
        return (int) App::db()->value('SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]);
    }

    public static function revoke(int $userId, int $keyId): void
    {
        App::db()->run('UPDATE api_keys SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [Db::now(), $keyId, $userId]);
    }

    /** Returns ['key_id' => .., 'user' => user row] or null. Only verified accounts. */
    public static function authenticate(string $key): ?array
    {
        if (!preg_match('/^pa_[a-f0-9]{40}$/', $key)) {
            return null;
        }
        $db = App::db();
        $row = $db->one('SELECT id, user_id FROM api_keys WHERE key_hash = ? AND revoked_at IS NULL',
            [hash('sha256', $key)]);
        if (!$row) {
            return null;
        }
        $user = Auth::find((int) $row['user_id']);
        if (!$user || $user['email_verified_at'] === null) {
            return null;
        }
        $db->run('UPDATE api_keys SET last_used_at = ? WHERE id = ?', [Db::now(), $row['id']]);
        return ['key_id' => (int) $row['id'], 'user' => $user];
    }
}
