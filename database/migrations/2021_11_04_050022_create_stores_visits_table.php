<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoresVisitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('API_LOCAL_VISITS', function (Blueprint $table) {
            $table->id();
            $table->integer('GROUP_CD');
            $table->integer('LOCAL_CD');
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
        Schema::dropIfExists('API_LOCAL_VISITS');
    }
}
