<?php

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Support\Facades\Route;

Route::group(
  [
    'prefix' => '/media',
    'where' => [],
  ],
  function () {
    $contentMediaRouteSlug = Utils::getContentMediaRouteSlug();
    $contentMediaRouteParamName = Utils::getContentMediaRouteParamName();
    $contentMediaRouteParamPattern = Utils::getContentMediaRouteParamPattern();
    Route::group(
      [
        'prefix' => '/content-media',
        'as' => Utils::getContentMediaRouteName() . '.',
        // 'where' => ['id' => config('andale-inbox.routing.id_pattern', '[0-9]+')],
      ],
      function () use ($contentMediaRouteSlug, $contentMediaRouteParamName, $contentMediaRouteParamPattern) {
        Route::get("/{$contentMediaRouteSlug}", 'MediaController@getContentMedia')->name('getSingleContentMedia')->where([$contentMediaRouteParamName => $contentMediaRouteParamPattern]) ;
      }
    );

    #region attachments
    $attachmentRouteSlug = Utils::getAttachmentRouteSlug();
    $attachmentParamName = Utils::getAttachmentsRouteParamName();
    $attachmentRouteParamPattern = Utils::getAttachmentRouteParamPattern();
    Route::group(
      [
        'prefix' => '/attachments',
        'as' => Utils::getAttachmentsRouteName() . '.',
        // 'where' => ['id' => config('andale-inbox.routing.id_pattern', '[0-9]+')],
      ],
      function () use ($attachmentRouteSlug, $attachmentParamName, $attachmentRouteParamPattern) {
        Route::get($attachmentRouteSlug, 'MediaController@getAttachment')->name('getSingleAttachment')->where([$attachmentParamName => $attachmentRouteParamPattern]) ;;
      }
    );

    #endregion attachments
  }
);
