<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications_apps', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('body');
            $table->text('image')->nullable();
            $table->text('data')->nullable();
            $table->integer('client_num');
            $table->integer('view_status');
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
        Schema::dropIfExists('notifications_apps');
    }
};
