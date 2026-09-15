<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertDebugDisabledInProduction();
        $this->allowDevelopmentOrigins();
    }

    /**
     * Debug mode leaks stack traces, configuration and SQL. Refuse to serve a
     * production deployment that still has it switched on.
     */
    private function assertDebugDisabledInProduction(): void
    {
        if ($this->app->environment('production') && config('app.debug')) {
            throw new RuntimeException(
                'APP_DEBUG must be false when APP_ENV=production. Refusing to boot with debug mode enabled.'
            );
        }
    }

    /**
     * Local-only convenience: let a LAN IP or a share tunnel talk to the API
     * with credentials, which is how scanning is tested from a phone. Gated
     * behind ALLOW_DEV_ORIGINS so that merely having APP_ENV=local on a
     * reachable host is not enough to widen CORS.
     */
    private function allowDevelopmentOrigins(): void
    {
        if ($this->app->runningInConsole() || ! $this->app->environment('local')) {
            return;
        }

        if (! filter_var(env('ALLOW_DEV_ORIGINS', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        config([
            'sanctum.stateful' => array_values(array_unique(array_merge(
                config('sanctum.stateful', []),
                [
                    '*.trycloudflare.com',
                    '*.ngrok-free.app',
                    '*.ngrok-free.dev',
                    '*.ngrok.io',
                    '*.loca.lt',
                ],
            ))),
        ]);

        $origin = request()->headers->get('Origin') ?: request()->headers->get('Referer');
        if (! is_string($origin) || $origin === '') {
            return;
        }

        $parts = parse_url($origin);
        $host = $parts['host'] ?? '';
        $isPrivateIpv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $isShareTunnel = str_ends_with($host, '.trycloudflare.com')
            || str_ends_with($host, '.ngrok-free.app')
            || str_ends_with($host, '.ngrok-free.dev')
            || str_ends_with($host, '.ngrok.io')
            || str_ends_with($host, '.loca.lt');
        if (! $isPrivateIpv4 && ! $isShareTunnel) {
            return;
        }

        $port = $parts['port'] ?? null;
        $stateful = $port ? [$host, "{$host}:{$port}"] : [$host];
        config([
            'sanctum.stateful' => array_values(array_unique(array_merge(config('sanctum.stateful', []), $stateful))),
            'cors.allowed_origins' => array_values(array_unique(array_merge(
                config('cors.allowed_origins', []),
                [($parts['scheme'] ?? 'http').'://'.$host.($port ? ':'.$port : '')],
            ))),
        ]);
    }
}
