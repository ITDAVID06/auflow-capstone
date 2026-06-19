<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User Status (must come before tbl_user due to FK)
        Schema::create('tbl_user_status', function (Blueprint $table) {
            $table->id();
            $table->string('status_name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Users
        Schema::create('tbl_user', function (Blueprint $table) {
            $table->bigIncrements('account_id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->unsignedBigInteger('user_status_id');
            $table->timestamps();

            $table->foreign('user_status_id')
                ->references('id')
                ->on('tbl_user_status');
        });

        // User Profiles
        Schema::create('tbl_userprofile', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('student_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('profile_picture')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('account_id')
                ->on('tbl_user');
        });

        // Roles
        Schema::create('tbl_role', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('description')->nullable();
            $table->enum('role_type', ['Requester', 'Approver', 'Verifier', 'Admin', 'Auditor']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Permissions
        Schema::create('tbl_permission', function (Blueprint $table) {
            $table->id();
            $table->string('permission_name');
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->string('resource');
            $table->string('action');
            $table->timestamps();
        });

        // Role-Permission pivot
        Schema::create('tbl_role_permission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'uq_role_permission');

            $table->foreign('role_id')
                ->references('id')
                ->on('tbl_role');
            $table->foreign('permission_id')
                ->references('id')
                ->on('tbl_permission');
        });

        // User-Role pivot
        Schema::create('tbl_user_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('role_id');
            $table->date('assigned_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('assigned_by');
            $table->timestamps();

            $table->unique(['account_id', 'role_id'], 'uq_user_role');

            $table->foreign('account_id')
                ->references('account_id')
                ->on('tbl_user');
            $table->foreign('role_id')
                ->references('id')
                ->on('tbl_role');
            $table->foreign('assigned_by')
                ->references('account_id')
                ->on('tbl_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_user_role');
        Schema::dropIfExists('tbl_role_permission');
        Schema::dropIfExists('tbl_permission');
        Schema::dropIfExists('tbl_role');
        Schema::dropIfExists('tbl_userprofile');
        Schema::dropIfExists('tbl_user');
        Schema::dropIfExists('tbl_user_status');
    }
};
