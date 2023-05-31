<?php

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Support\Facades\Route;

Route::group(
  [
    'prefix' => '/{slug}/{id}',
    'where' => [
      'slug' => join(
        '',
        [
          '(',
          join('|', Utils::getMappingSlugs()),
          ')',
        ]
      ),
      'id' => config('andale-inbox.routing.id_pattern', '[0-9]+'),
    ],
  ],
  function () {
    Route::name('threads.')->prefix('threads')->group(__DIR__ . '/./parts/threads.php');
    Route::name('messages.')->prefix('messages')->group(__DIR__ . '/./parts/messages.php');
  }
);
