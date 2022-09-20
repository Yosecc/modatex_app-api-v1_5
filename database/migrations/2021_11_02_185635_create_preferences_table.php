<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('API_CATEGORY_PREFERENCES', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('CLIENT_NUM');
            $table->integer('TIPOMODELO_DP1_NUM');
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
        Schema::dropIfExists('API_CATEGORY_PREFERENCES');
    }
}
