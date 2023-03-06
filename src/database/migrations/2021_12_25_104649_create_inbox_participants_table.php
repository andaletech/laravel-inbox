<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInboxParticipantsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create(config('andale-inbox.tables.participants', 'inbox_participants'), function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('nano_id')->index();
      $table->unsignedBigInteger('thread_id');
      $table->unsignedBigInteger('message_id');
      $table->morphs('participant');
      $table->json('tags')->nullable();
      $table->json('extra')->nullable();
      $table->timestamp('read_at')->nullable();
      $table->timestamp('trashed_at')->nullable();
      $table->json('email_header_store')->nullable();
      $table->timestamps();
      $table->softDeletes();

      $table->foreign('thread_id')
        ->references('id')
        ->on(config('andale-inbox.tables.threads', 'inbox_threads'))
        ->onDelete('cascade');
      $table->foreign('message_id')
        ->references('id')
        ->on(config('andale-inbox.tables.messages', 'inbox_messages'))
        ->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists(config('andale-inbox.tables.participants', 'inbox_participants'));
  }
}
