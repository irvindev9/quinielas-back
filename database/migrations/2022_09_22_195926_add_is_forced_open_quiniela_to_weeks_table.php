<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsForcedOpenQuinielaToWeeksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('weeks', function (Blueprint $table) {
            $table->tinyInteger('is_forced_open_quiniela')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('weeks', function (Blueprint $table) {
            $table->dropColumn('is_forced_open_quiniela');
        });
    }
}
