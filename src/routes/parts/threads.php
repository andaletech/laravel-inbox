<?php

use Illuminate\Support\Facades\Route;

// Route::group(
//   [
//     'prefix' => '/threads', /* 'as' => 'profile', */ /* 'middleware' => ['can:view,user'], */
//   ],
//   function () {

//   }
// );

Route::get('/', 'InboxController@getSluggedThreads')->name('index');
Route::post('/', 'InboxController@createThread')->name('create');
Route::group(
  ['prefix' => '/{threadId}', 'as' => 'thread.'],
  function () {
    Route::get('/messages', 'InboxController@getSluggedThreadMessages')->name('index');
    Route::group(
      ['prefix' => '/status', 'as' => 'status.'],
      function () {
        Route::post('/read', 'InboxController@markSluggedThreadAsRead')->name('index');
      }
    );
  }
);
