<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGenericParticipantsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create(config('andale-inbox.tables.generic_participants', 'inbox_generic_participants'), function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('name')->nullable()->index();
      $table->string('email')->nullable()->index();
      $table->string('phone_number')->nullable();
      $table->json('extra')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists(config('andale-inbox.tables.generic_participants', 'inbox_generic_participants'));
  }
}
