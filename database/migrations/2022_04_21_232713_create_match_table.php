<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('match', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('week_id')->unsigned();
            $table->bigInteger('team_id')->unsigned();
            $table->bigInteger('team_id_2')->unsigned();
            $table->tinyInteger('winner_id')->nullable();
            $table->timestamps();

            $table->foreign('week_id')->references('id')->on('weeks');
            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('team_id_2')->references('id')->on('teams');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('match');
    }
}
