<?php

namespace App\Http\Middleware;

use App\Models\GlobalConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class LoadConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Set dynamic config values
        $configs = GlobalConfig::pluck('value','key');
        foreach ($configs as $key => $value) {
            Config::set($key, $value);
        }

        // check validity of access token and auto-refresh
        $now = time();
        $future = strtotime(config('ZOHO_ACCESS_TOKEN_EXPIRY'));
        if ($now >= $future) {
            $service = new \App\Http\Services\InvoiceService;
            $resp = $service->refreshToken();
            if ($resp && $resp->access_token) {
                GlobalConfig::where('key', 'ZOHO_ACCESS_TOKEN_EXPIRES_IN')->update([
                    'value' => $resp->expires_in
                ]); 
                GlobalConfig::where('key', 'ZOHO_ACCESS_TOKEN_EXPIRY')->update([
                    'value' => date('Y-m-d H:i:s', time() + $resp->expires_in),
                ]); 
                GlobalConfig::where('key', 'ZOHO_ACCESS_TOKEN')->update([
                    'value' => $resp->access_token,
                ]); 
            }
        }

        return $next($request);
    }
}
