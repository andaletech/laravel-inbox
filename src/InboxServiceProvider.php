<?php

namespace Andaletech\Inbox;

use Hidehalo\Nanoid\Client;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
  public function boot()
  {
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
        'name' => config('andale-inbox.routing.name', ['web', 'auth']),
      ],
      function () {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
      }
    );
  }
}
