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

        if (Schema::hasTable('tbl_workflow')
            && Schema::hasColumn('tbl_workflow', 'created_by')
            && ! $this->hasForeignKey('tbl_workflow', 'fk_workflow_created_by_user')) {
            Schema::table('tbl_workflow', function (Blueprint $table): void {
                $table->foreign('created_by', 'fk_workflow_created_by_user')
                    ->references('account_id')
                    ->on('tbl_user')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('tbl_workflow_step')) {
            if (Schema::hasColumn('tbl_workflow_step', 'assigned_account_id')
                && ! $this->hasForeignKey('tbl_workflow_step', 'fk_workflow_step_assigned_user')) {
                Schema::table('tbl_workflow_step', function (Blueprint $table): void {
                    $table->foreign('assigned_account_id', 'fk_workflow_step_assigned_user')
                        ->references('account_id')
                        ->on('tbl_user')
                        ->nullOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_workflow_step', 'if_rejected_id')
                && ! $this->hasForeignKey('tbl_workflow_step', 'fk_workflow_step_if_rejected_step')) {
                Schema::table('tbl_workflow_step', function (Blueprint $table): void {
                    $table->foreign('if_rejected_id', 'fk_workflow_step_if_rejected_step')
                        ->references('id')
                        ->on('tbl_workflow_step')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('tbl_workflow_step_progress')) {
            if (Schema::hasColumn('tbl_workflow_step_progress', 'form_id')
                && ! $this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_form')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->foreign('form_id', 'fk_wsp_form')
                        ->references('id')
                        ->on('tbl_form')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_workflow_step_progress', 'workflow_id')
                && ! $this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_workflow')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->foreign('workflow_id', 'fk_wsp_workflow')
                        ->references('id')
                        ->on('tbl_workflow')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_workflow_step_progress', 'step_id')
                && ! $this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_step')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->foreign('step_id', 'fk_wsp_step')
                        ->references('id')
                        ->on('tbl_workflow_step')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_workflow_step_progress', 'actor_id')
                && ! $this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_actor')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->foreign('actor_id', 'fk_wsp_actor')
                        ->references('account_id')
                        ->on('tbl_user')
                        ->restrictOnDelete();
                });
            }
        }

        if (Schema::hasTable('tbl_workflow_step_progress_comment_attachment')) {
            if (Schema::hasColumn('tbl_workflow_step_progress_comment_attachment', 'progress_id')
                && ! $this->hasForeignKey('tbl_workflow_step_progress_comment_attachment', 'fk_wspca_progress')) {
                Schema::table('tbl_workflow_step_progress_comment_attachment', function (Blueprint $table): void {
                    $table->foreign('progress_id', 'fk_wspca_progress')
                        ->references('id')
                        ->on('tbl_workflow_step_progress')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('tbl_workflow_step_progress_comment_attachment', 'uploaded_by')
                && ! $this->hasForeignKey('tbl_workflow_step_progress_comment_attachment', 'fk_wspca_uploaded_by')) {
                Schema::table('tbl_workflow_step_progress_comment_attachment', function (Blueprint $table): void {
                    $table->foreign('uploaded_by', 'fk_wspca_uploaded_by')
                        ->references('account_id')
                        ->on('tbl_user')
                        ->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('tbl_workflow_step_progress_comment_attachment')) {
            if ($this->hasForeignKey('tbl_workflow_step_progress_comment_attachment', 'fk_wspca_progress')) {
                Schema::table('tbl_workflow_step_progress_comment_attachment', function (Blueprint $table): void {
                    $table->dropForeign('fk_wspca_progress');
                });
            }

            if ($this->hasForeignKey('tbl_workflow_step_progress_comment_attachment', 'fk_wspca_uploaded_by')) {
                Schema::table('tbl_workflow_step_progress_comment_attachment', function (Blueprint $table): void {
                    $table->dropForeign('fk_wspca_uploaded_by');
                });
            }
        }

        if (Schema::hasTable('tbl_workflow_step_progress')) {
            if ($this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_form')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->dropForeign('fk_wsp_form');
                });
            }

            if ($this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_workflow')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->dropForeign('fk_wsp_workflow');
                });
            }

            if ($this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_step')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->dropForeign('fk_wsp_step');
                });
            }

            if ($this->hasForeignKey('tbl_workflow_step_progress', 'fk_wsp_actor')) {
                Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
                    $table->dropForeign('fk_wsp_actor');
                });
            }
        }

        if (Schema::hasTable('tbl_workflow_step')) {
            if ($this->hasForeignKey('tbl_workflow_step', 'fk_workflow_step_assigned_user')) {
                Schema::table('tbl_workflow_step', function (Blueprint $table): void {
                    $table->dropForeign('fk_workflow_step_assigned_user');
                });
            }

            if ($this->hasForeignKey('tbl_workflow_step', 'fk_workflow_step_if_rejected_step')) {
                Schema::table('tbl_workflow_step', function (Blueprint $table): void {
                    $table->dropForeign('fk_workflow_step_if_rejected_step');
                });
            }
        }

        if (Schema::hasTable('tbl_workflow') && $this->hasForeignKey('tbl_workflow', 'fk_workflow_created_by_user')) {
            Schema::table('tbl_workflow', function (Blueprint $table): void {
                $table->dropForeign('fk_workflow_created_by_user');
            });
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
