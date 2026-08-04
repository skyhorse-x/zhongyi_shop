<?php

use Illuminate\Support\Facades\Route;

// SPA 入口 - 所有前端路由都返回 Vue.js 应用
Route::get('/{any?}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api|admin|storage|assets|css|js|images|uploads|favicon|robots|manifest|storage).*');
