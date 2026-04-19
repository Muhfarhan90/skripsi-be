<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/api/docs', 'api-docs')->name('api.docs.ui');

Route::get('/api/openapi.yaml', function () {
    $path = base_path('docs/openapi.yaml');

    abort_unless(File::exists($path), 404, 'OpenAPI spec file not found.');

    return response(File::get($path), 200, [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]);
})->name('api.docs.spec');
