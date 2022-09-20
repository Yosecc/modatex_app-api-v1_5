<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubcategoryPreferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('API_SUBCATEGORY_PREFERENCES', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('TIPOMODELO_DP1_NUM');
            $table->integer('TIPOMODELO_DP2_NUM');
            $table->integer('CLIENT_NUM');
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
        Schema::dropIfExists('API_SUBCATEGORY_PREFERENCES');
    }
}
