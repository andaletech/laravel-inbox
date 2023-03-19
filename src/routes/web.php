<?php

use Illuminate\Support\Facades\Route;

Route::name('slugged.')->group(__DIR__ . '/./sluggedRoutes.php');
Route::name('media.')->group(__DIR__ . '/parts/mediaRoutes.php');
