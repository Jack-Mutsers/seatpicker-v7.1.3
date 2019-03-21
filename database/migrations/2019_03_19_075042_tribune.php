<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Tribune extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tribune', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('visible');
            $table->integer('active');
            $table->text('tribune');
            $table->string('name', 191)->unique();
            $table->timestamp('creation_date');
        });

    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tribune');
    }
}
