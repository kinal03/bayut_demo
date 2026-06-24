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
        Schema::create('re_features', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('arabic_name')->nullable();
            $table->string('icon')->nullable();
            $table->enum('status', ['published', 'unpublished'])->default('published');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('re_features');
    }
};
