<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     * Env TRUSTED_PROXIES: * or comma-separated IPs. Required behind Nginx/LB.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $proxies = config('app.trusted_proxies');
        if ($proxies === null || $proxies === '') {
            $this->proxies = null;

            return;
        }

        $this->proxies = $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies))));
    }
}
