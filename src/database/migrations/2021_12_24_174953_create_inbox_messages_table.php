<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInboxMessagesTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create(config('andale-inbox.tables.messages', 'inbox_messages'), function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->unsignedBigInteger('thread_id');
      $table->unsignedBigInteger('user_id')->nullable()->index();
      $table->string('nano_id')->index();
      $table->string('subject')->nullable()->index();
      $table->longText('body')->nullable();
      $table->text('body_plain_text')->nullable();
      $table->morphs('from');
      $table->boolean('is_draft')->default(false);
      $table->timestamps();
      $table->softDeletes();

      $table->foreign('thread_id')->references('id')->on(config('andale-inbox.tables.threads', 'inbox_threads'))->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists(config('andale-inbox.tables.messages', 'inbox_messages'));
  }
}
