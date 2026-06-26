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
        Schema::create('re_properties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('permalink')->nullable()->unique();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('user_type')->nullable();
            $table->enum('purpose', ['sale', 'rent']);
            $table->decimal('price', 15, 2)->nullable();
            $table->string('type')->nullable();
            $table->string('completion_status')->nullable();
            $table->string('furnishing_status')->nullable();
            $table->string('reference_no')->nullable();
            $table->date('trucheck_on')->nullable();
            $table->date('added_on')->nullable();
            $table->string('neighborhood')->nullable();
            $table->double('area_size')->nullable();
            $table->string('total_bedroom')->nullable();
            $table->string('total_bathroom')->nullable();
            $table->double('balcony_size')->nullable();
            $table->string('usage')->nullable();
            $table->string('ownership')->nullable();
            $table->boolean('parking_availability')->default(true);
            $table->longText('description')->nullable();
            $table->string('project_name')->nullable();
            $table->string('developer')->nullable();
            $table->string('project_status')->nullable();
            $table->date('last_inspected')->nullable();
            $table->string('handover_year')->nullable();
            $table->string('handover_quarter')->nullable();
            $table->string('building_name')->nullable();
            $table->string('parking_spaces')->nullable();
            $table->integer('building_floors')->nullable();
            $table->integer('building_area')->nullable();
            $table->integer('swimming_pools')->nullable();
            $table->integer('elevators')->nullable();
            $table->string('permit_number')->nullable();
            $table->string('zone_name')->nullable();
            $table->string('registered_agency')->nullable();
            $table->string('rera_orn')->nullable();
            $table->string('agent_brn')->nullable();
            $table->text('location')->nullable();
            $table->string('currency')->nullable(); 
            $table->enum('status', ['published', 'unplished'])->default('published');
            $table->enum('moderation_status', ['pending', 'approved','rejected'])->default('pending');
            $table->string('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('re_properties');
    }
};
