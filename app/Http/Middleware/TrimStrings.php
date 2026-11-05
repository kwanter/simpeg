<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    private bool $preservePkpDecimals = false;

    public function handle($request, \Closure $next)
    {
        $this->preservePkpDecimals = $request->isMethod('PUT') && $request->is('pkp/*/revisions/*');

        return parent::handle($request, $next);
    }

    protected function transform($key, $value)
    {
        if ($this->preservePkpDecimals && preg_match('/^indicators\..+\.activities\..+\.(target_credit|target_quantity|target_quality|realized_quantity)$/', $key) === 1) {
            return $value;
        }

        return parent::transform($key, $value);
    }

    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
