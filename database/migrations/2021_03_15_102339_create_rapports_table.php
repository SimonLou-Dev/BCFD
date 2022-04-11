<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRapportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('Rapports', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->boolean('dispensaire')->default(false);
            $table->integer('interType');
            $table->integer('patient_id');
            $table->integer('transport');
            $table->longText('description');
            $table->integer('price');
            $table->bigInteger('ata')->nullable();
            $table->integer('pathology_id')->nullable();
            $table->string('started_at')->nullable();
            $table->bigInteger('discord_msg_id')->nullable()->default(null);
            $table->string('service');

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
        Schema::dropIfExists('Rapports');
    }
}
