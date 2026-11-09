<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Client for the calc service, with a database cache.
 * Results are deterministic (same city + dates = same answer), so they can be
 * cached for days; the cache only exists to spare the calc server.
 */
final class Calc
{
    private const TTL_SKY = 7 * 86400;
    private const TTL_CITY = 30 * 86400;

    public function __construct(private array $cfg, private Db $db) {}

    public function searchCities(string $q, ?string $country = null, int $limit = 10): array
    {
        $q = trim($q);
        $key = 'search:' . mb_strtolower($q) . ':' . ($country ?? '') . ':' . $limit;
        return $this->cached($key, self::TTL_CITY, '/cities/search',
            array_filter(['q' => $q, 'country' => $country, 'limit' => $limit]))['results'];
    }

    public function city(int $id): array
    {
        return $this->cached("city:$id", self::TTL_CITY, "/cities/$id");
    }

    /** Nights from..to inclusive (Y-m-d). */
    public function nights(int $cityId, string $from, string $to): array
    {
        return $this->cached("nights:$cityId:$from:$to", self::TTL_SKY, '/nights',
            ['city' => $cityId, 'from' => $from, 'to' => $to])['nights'];
    }

    public function events(int $cityId, string $from, string $to): array
    {
        return $this->cached("events:$cityId:$from:$to", self::TTL_SKY, '/events',
            ['city' => $cityId, 'from' => $from, 'to' => $to])['events'];
    }

    /** Positions every 10 minutes from sunset to sunrise, for the time slider. */
    public function track(int $cityId, string $night): array
    {
        return $this->cached("track:$cityId:$night", self::TTL_SKY, '/track',
            ['city' => $cityId, 'date' => $night]);
    }

    /** The track if it is already cached; never asks the calc server (for the start page). */
    public function peekTrack(int $cityId, string $night): ?array
    {
        $row = $this->db->one(
            'SELECT payload FROM calc_cache WHERE cache_key = ? AND expires_at > UTC_TIMESTAMP()', ["track:$cityId:$night"]);
        return $row ? json_decode($row['payload'], true) : null;
    }

    public function health(): array
    {
        return $this->request('/health', []);
    }

    /** Oppositions, elongations, planet pairs worldwide (cached for a day). */
    public function globalEvents(string $from, string $to): array
    {
        return $this->cached("global:$from:$to", 86400, '/global-events', ['from' => $from, 'to' => $to])['events'];
    }

    private function cached(string $key, int $ttl, string $path, array $query = []): array
    {
        $key = substr($key, 0, 190);
        $row = $this->db->one(
            'SELECT payload FROM calc_cache WHERE cache_key = ? AND expires_at > UTC_TIMESTAMP()', [$key]);
        if ($row) {
            Stats::bump('cache_hits');
            return json_decode($row['payload'], true);
        }
        Stats::bump('cache_misses');
        $data = $this->request($path, $query);
        $this->db->run(
            'REPLACE INTO calc_cache (cache_key, payload, expires_at) VALUES (?, ?, ?)',
            [$key, json_encode($data), gmdate('Y-m-d H:i:s', time() + $ttl)]);
        return $data;
    }

    private function request(string $path, array $query): array
    {
        $url = rtrim($this->cfg['url'], '/') . $path . ($query ? '?' . http_build_query($query) : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->cfg['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['X-Calc-Secret: ' . $this->cfg['secret'], 'Accept: application/json'],
        ]);
        $t0 = microtime(true);
        $body = curl_exec($ch);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        Stats::bump('calc_requests');
        Stats::bump('calc_ms_total', $ms);
        Stats::max('calc_ms_max', $ms);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new CalcException("calc service unreachable: $error");
        }
        $data = json_decode((string) $body, true);
        if ($status === 404) {
            throw new CalcException('not found', 404);
        }
        if ($status !== 200 || !is_array($data)) {
            throw new CalcException("calc service HTTP $status on $path", $status ?: 502);
        }
        return $data;
    }
}
