<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Form Categories
        Schema::create('tbl_form_category', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        // Forms
        Schema::create('tbl_form', function (Blueprint $table) {
            $table->id();
            $table->string('form_name');
            $table->string('form_code', 100)->unique();
            $table->unsignedBigInteger('form_category_id')->nullable();
            $table->text('description')->nullable();
            $table->string('form_type', 50)->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['Active', 'Inactive', 'Archived'])->default('Inactive');
            $table->boolean('email_notifications')->default(false);
            $table->string('submission_limit')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->longText('draft_data')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_locked']);

            $table->foreign('form_category_id')
                ->references('id')
                ->on('tbl_form_category')
                ->nullOnDelete();
        });

        // Form Fields
        Schema::create('tbl_formfield', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('field_name');
            $table->string('label');
            $table->string('data_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('use_slots')->default(false);
            $table->boolean('require_facility')->default(false);
            $table->longText('options')->nullable();
            $table->longText('options_meta')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('help_text', 500)->nullable();
            $table->string('date_mode', 16)->nullable();
            $table->longText('field_options')->nullable();
            $table->longText('conditions')->nullable();
            $table->integer('field_order')->default(0);
            $table->timestamp('date_created')->useCurrent();

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->cascadeOnDelete();
        });

        // Form Permissions pivot
        Schema::create('tbl_form_permission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->unique(['form_id', 'permission_id'], 'form_permission_unique');

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->cascadeOnDelete();
            $table->foreign('permission_id')
                ->references('id')
                ->on('tbl_permission')
                ->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_form_permission');
        Schema::dropIfExists('tbl_formfield');
        Schema::dropIfExists('tbl_form');
        Schema::dropIfExists('tbl_form_category');
    }
};
