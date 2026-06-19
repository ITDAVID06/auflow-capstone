<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tbl_snapshot');
        Schema::create('tbl_snapshot', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 32)->unique();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('step_id');
            $table->string('workflow_step', 120);
            $table->string('status', 20);
            $table->unsignedBigInteger('approved_by')->index();
            $table->timestamp('approved_at')->useCurrent()->useCurrentOnUpdate();
            $table->text('comment')->nullable();
            $table->longText('payload_json');
            $table->string('action_hash', 64)->nullable()->index();
            $table->mediumText('rendered_html')->nullable();
            $table->boolean('locked')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['form_id', 'workflow_id', 'step_id']);

            $table->foreign('approved_by')
                ->references('account_id')
                ->on('tbl_user');
        });

        if (DB::getDriverName() !== 'sqlite') {
            // Immutability trigger
            DB::unprepared("
                CREATE TRIGGER `trg_tbl_snapshot_no_update` BEFORE UPDATE ON `tbl_snapshot` FOR EACH ROW
                BEGIN
                    IF OLD.locked = 1 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Snapshots are immutable';
                    END IF;
                END
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS `trg_tbl_snapshot_no_update`');
        }
        Schema::dropIfExists('tbl_snapshot');
    }
};
