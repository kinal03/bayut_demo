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
        Schema::create('frontend_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('password')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->longText('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
           $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frontend_user');
    }
};
