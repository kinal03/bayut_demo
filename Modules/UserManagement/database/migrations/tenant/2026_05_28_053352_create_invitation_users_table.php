<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitation_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('user_type')->default('agent');
            $table->string('token')->unique();
            $table->string('tenant_id')->nullable();
            $table->enum('status', ['pending', 'processing','accepted', 'rejected', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->string('mobile')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('landline')->nullable();

            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('nationality')->nullable();

            $table->integer('experience')->nullable();

            $table->string('languages')->nullable(); // English, Arabic, etc.
            $table->string('specialities')->nullable(); // Residential Sales
            $table->string('speciality_areas')->nullable(); // Dubai Marina

            // Social Links
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('youtube')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_users');
    }
};
