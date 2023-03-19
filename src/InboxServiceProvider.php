<?php

namespace Andaletech\Inbox;

use Hidehalo\Nanoid\Client;
use Andaletech\Inbox\Libs\Utils;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
  public function boot()
  {
    Route::bind(Utils::getContentMediaRouteParamName(), function ($value) {
      $model = Utils::getContentMediaModel();

      if ($model) {
        return $model::find($value);
      }

      return $value;
    });
    Route::bind(Utils::getAttachmentsRouteParamName(), function ($value) {
      $model = Utils::getAttachmentModel();

      if ($model) {
        return $model::find($value);
      }

      return $value;
    });
    $this->publishes([
      __DIR__ . '/config/andale-inbox.php' => config_path('andale-inbox.php'),
      __DIR__ . '/database/migrations' => database_path('migrations'),
    ]);
    $this->registerRoutes();
    $this->loadTranslationsFrom(__DIR__ . './resources/lang', 'andale-inbox');
    $this->loadJsonTranslationsFrom(__DIR__ . './resources/lang');
  }

  public function register()
  {
    $this->mergeConfigFrom(
      __DIR__ . '/config/andale-inbox.php',
      'andale-inbox'
    );
    $this->app->singleton('Andaletech\Inbox\NanoIdClient', function ($app) {
      return new Client();
    });
    $this->app->singleton('Andaletech\Responder', function ($app) {
      $responserClass = Config('andale-inbox.routing.responder');

      return new $responserClass();
    });
  }

  protected function registerRoutes()
  {
    Route::group(
      [
        'prefix' => config('andale-inbox.routing.prefix', 'andale-inbox'),
        'namespace' => 'Andaletech\Inbox\Http\Controllers',
        'middleware' => config('andale-inbox.routing.middleware', ['web', 'auth']),
        'as' => Utils::getTopRouteName() . '.',
      ],
      function () {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
      }
    );
  }
}
