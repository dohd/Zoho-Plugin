<?php

namespace App\Http\Controllers\config;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class ConfigController extends Controller
{
    /**
     * Clear Cache
     */
    public function clear_cache() 
    {   
        try {
            Artisan::call('cache:clear');
            Artisan::call('route:cache');
            Artisan::call('config:cache');
            return "Application cache cleared";
        } catch (\Throwable $th) {
            return "Something went wrong! " . $th->getMessage();
        }
    }

    /**
     * Maintenance Mode
     */
    public function site_down() 
    {
        Artisan::call('down');
        return redirect()->back();
    }
}
