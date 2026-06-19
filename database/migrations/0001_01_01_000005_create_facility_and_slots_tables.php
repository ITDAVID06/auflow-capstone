<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facilities
        Schema::create('tbl_facility', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Slots
        Schema::create('tbl_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->date('date');
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->timestamps();

            $table->index(['form_id', 'facility_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_slots');
        Schema::dropIfExists('tbl_facility');
    }
};
