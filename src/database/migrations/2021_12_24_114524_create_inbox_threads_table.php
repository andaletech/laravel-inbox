<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInboxThreadsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create(config('andale-inbox.tables.threads', 'inbox_threads'), function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('nano_id')->index();
      $table->string('subject')->nullable();
      $table->morphs('owner');
      $table->timestamps();
      $table->softDeletes();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists(config('andale-inbox.tables.threads', 'inbox_threads'));
  }
}
