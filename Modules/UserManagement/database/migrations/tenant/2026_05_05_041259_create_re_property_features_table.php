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
        Schema::create('re_property_features', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('properties_id')
                  ->constrained('re_properties')
                  ->cascadeOnDelete();

            $table->foreignId('features_id')
                  ->constrained('re_features')
                  ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('re_property_features');
    }
};
