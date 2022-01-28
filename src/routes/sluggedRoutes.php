<?php

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Support\Facades\Route;

Route::group(
  [
    'prefix' => '/{slug}', /* 'as' => 'profile', */ /* 'middleware' => ['can:view,user'], */
    'where' => [
      'slug' => join(
        '',
        [
          '(',
          join('|', Utils::getMappingSlugs()),
          ')',
        ]
      ),
    ],
  ],
  function () {
    Route::group(
      [
        'prefix' => '/{id}',
        'where' => ['id' => config('andale-inbox.routing.id_patern', '[0-9]+')],
      ],
      function () {
        // Route::get('/', 'InboxController@getSluggedThreads');
        Route::name('threads.')->prefix('threads')->group(__DIR__ . '/./parts/threads.php');
      }
    );
  }
);
