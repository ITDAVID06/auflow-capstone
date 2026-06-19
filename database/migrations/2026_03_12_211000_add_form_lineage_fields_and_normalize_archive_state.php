<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_form', function (Blueprint $table): void {
            $table->string('form_family_code', 150)->nullable()->after('form_code');
            $table->unsignedBigInteger('parent_form_id')->nullable()->after('form_family_code');
            $table->date('revision_effective_at')->nullable()->after('version');

            $table->index('form_family_code');
            $table->unique(['form_family_code', 'version'], 'tbl_form_family_code_version_unique');
            $table->index('parent_form_id');
            $table->index('revision_effective_at');

            $table->foreign('parent_form_id')
                ->references('id')
                ->on('tbl_form')
                ->nullOnDelete();
        });

        DB::table('tbl_form')
            ->orderBy('id')
            ->get(['id', 'form_code', 'status', 'updated_at', 'deleted_at'])
            ->each(function (object $form): void {
                $updates = [];

                if (! isset($form->form_family_code) || $form->form_family_code === null) {
                    $updates['form_family_code'] = $form->form_code;
                }

                if (strcasecmp((string) $form->status, 'Archived') === 0) {
                    $updates['status'] = 'Inactive';

                    if ($form->deleted_at === null) {
                        $updates['deleted_at'] = $form->updated_at ?? now();
                    }
                }

                if ($updates !== []) {
                    DB::table('tbl_form')->where('id', $form->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tbl_form', function (Blueprint $table): void {
            $table->dropForeign(['parent_form_id']);
            $table->dropUnique('tbl_form_family_code_version_unique');
            $table->dropIndex(['form_family_code']);
            $table->dropIndex(['parent_form_id']);
            $table->dropIndex(['revision_effective_at']);

            $table->dropColumn(['form_family_code', 'parent_form_id', 'revision_effective_at']);
        });
    }
};
