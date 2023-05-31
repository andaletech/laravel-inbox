<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'InboxController@getSluggedMessages')->name('index');
Route::group(
  ['prefix' => '/{inboxMessageId}', 'as' => 'message.'],
  function () {
    Route::get('/participants', 'InboxController@getSluggedMessageParticipants')->name('single_message');
  }
);
