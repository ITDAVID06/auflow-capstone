<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('tbl_slots')) {
            if (Schema::hasColumn('tbl_slots', 'form_id')
                && ! $this->hasForeignKey('tbl_slots', 'fk_slots_form')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->foreign('form_id', 'fk_slots_form')
                        ->references('id')
                        ->on('tbl_form')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_slots', 'account_id')
                && ! $this->hasForeignKey('tbl_slots', 'fk_slots_account')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->foreign('account_id', 'fk_slots_account')
                        ->references('account_id')
                        ->on('tbl_user')
                        ->restrictOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_slots', 'facility_id')
                && ! $this->hasForeignKey('tbl_slots', 'fk_slots_facility')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->foreign('facility_id', 'fk_slots_facility')
                        ->references('id')
                        ->on('tbl_facility')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('tbl_slots')) {
            if ($this->hasForeignKey('tbl_slots', 'fk_slots_form')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->dropForeign('fk_slots_form');
                });
            }

            if ($this->hasForeignKey('tbl_slots', 'fk_slots_account')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->dropForeign('fk_slots_account');
                });
            }

            if ($this->hasForeignKey('tbl_slots', 'fk_slots_facility')) {
                Schema::table('tbl_slots', function (Blueprint $table): void {
                    $table->dropForeign('fk_slots_facility');
                });
            }
        }
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.table_constraints
             WHERE constraint_schema = ?
               AND table_name = ?
               AND constraint_name = ?
               AND constraint_type = "FOREIGN KEY"',
            [DB::getDatabaseName(), $table, $constraintName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
