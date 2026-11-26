<?php

use Illuminate\Support\Facades\Route;

Route::get('{any?}', function () {
    // منع كاش المتصفح لصفحة SPA حتى يجلب دائماً أحدث أصول البناء (vite hashes)
    return response()
        ->view('application')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
})->where('any', '.*');
