<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('iso_code')->nullable();
            $table->string('iso3')->nullable();
            $table->string('phone_code')->nullable();
            $table->bigInteger('min_length')->nullable();
            $table->bigInteger('max_length')->nullable();
            $table->string('currency')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('timezones')->nullable();
            $table->string('status')->nullable();
            $table->string('flag')->nullable();
            $table->tinyInteger('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Countries');
    }
};
