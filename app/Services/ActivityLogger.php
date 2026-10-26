<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Records user activity with device, browser, platform and approximate
 * location. Never stores passwords — "credentials" activity means sign-in
 * successes/failures and password changes, with the email that was used.
 */
class ActivityLogger
{
    public static function log(Request $request, string $event, ?string $description = null, ?User $user = null, array $extra = []): ?UserActivity
    {
        try {
            $user ??= $request->user();
            $ip = $request->ip();
            $ua = (string) $request->userAgent();
            // Look up the location only for sign-in events; other events reuse the cached result.
            $geo = self::location($ip, in_array($event, ['login', 'login_failed'], true));

            return UserActivity::create([
                'tenant_id' => app(CurrentTenant::class)->id(),
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'email' => $extra['email'] ?? $user?->email,
                'event' => $event,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'method' => $request->method(),
                'url' => mb_substr(self::safeUrl($request), 0, 500),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $ip,
                'user_agent' => mb_substr($ua, 0, 500),
                ...self::parseAgent($ua),
                ...$geo,
                'is_suspicious' => $extra['is_suspicious'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Activity log failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The request URL without its query string, with secret path segments
     * masked: password-reset tokens (/reset-password/{token}) and email
     * verification hashes (/email/verify/{id}/{hash}). Query strings can hold
     * signatures and tokens, so they are never stored.
     */
    public static function safeUrl(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $path = preg_replace('#^/reset-password/[^/]+#', '/reset-password/[redacted]', $path) ?? $path;
        $path = preg_replace('#^/email/verify/([^/]+)/[^/]+#', '/email/verify/$1/[redacted]', $path) ?? $path;

        return rtrim($request->root(), '/').($path === '/' ? '/' : $path);
    }

    /** @return array{device: string, browser: string, platform: string} */
    public static function parseAgent(string $ua): array
    {
        $device = match (true) {
            $ua === '' => 'Unknown',
            (bool) preg_match('/bot|crawl|spider|slurp|curl|wget|python|httpclient|postman/i', $ua) => 'Bot',
            (bool) preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $ua) => 'Tablet',
            (bool) preg_match('/mobi|iphone|ipod|android.*mobile|windows phone/i', $ua) => 'Mobile',
            default => 'Desktop',
        };

        $browser = match (true) {
            (bool) preg_match('/Edg\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox\//', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\//', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => 'Other',
        };

        $platform = match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Other',
        };

        return compact('device', 'browser', 'platform');
    }

    /** @return array{country: ?string, region: ?string, city: ?string} */
    public static function location(?string $ip, bool $lookup = false): array
    {
        $empty = ['country' => null, 'region' => null, 'city' => null];
        if (! $ip) {
            return $empty;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country' => 'Local network', 'region' => null, 'city' => null];
        }

        $key = "geoip:{$ip}";
        if (Cache::has($key) || ! $lookup || ! config('marketplace.activity.geoip_enabled')) {
            return Cache::get($key, $empty);
        }

        // Only over HTTPS, and only to an endpoint configured on purpose.
        $endpoint = (string) config('marketplace.activity.geoip_endpoint');
        if (! str_starts_with($endpoint, 'https://') || ! str_contains($endpoint, '{ip}')) {
            return $empty;
        }

        try {
            $res = Http::timeout(2)->get(str_replace('{ip}', $ip, $endpoint));
            $data = $res->ok() && $res->json('status') === 'success'
                ? ['country' => $res->json('country'), 'region' => $res->json('regionName'), 'city' => $res->json('city')]
                : $empty;
        } catch (\Throwable) {
            $data = $empty;
        }
        Cache::put($key, $data, now()->addDays(7));

        return $data;
    }
}
