<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // JSON check constraints matching the SQL dump
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // JSON check constraints matching the SQL dump
        DB::statement('ALTER TABLE `tbl_form` ADD CONSTRAINT `tbl_form_draft_data_json_chk` CHECK (json_valid(`draft_data`) or `draft_data` is null)');
        DB::statement('ALTER TABLE `tbl_formfield` ADD CONSTRAINT `tbl_formfield_options_meta_json_chk` CHECK (json_valid(`options_meta`) or `options_meta` is null)');
        DB::statement('ALTER TABLE `tbl_formfield` ADD CONSTRAINT `tbl_formfield_field_options_json_chk` CHECK (json_valid(`field_options`) or `field_options` is null)');
        DB::statement('ALTER TABLE `tbl_formfield` ADD CONSTRAINT `tbl_formfield_conditions_json_chk` CHECK (json_valid(`conditions`) or `conditions` is null)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `tbl_form` DROP CONSTRAINT IF EXISTS `tbl_form_draft_data_json_chk`');
        DB::statement('ALTER TABLE `tbl_formfield` DROP CONSTRAINT IF EXISTS `tbl_formfield_options_meta_json_chk`');
        DB::statement('ALTER TABLE `tbl_formfield` DROP CONSTRAINT IF EXISTS `tbl_formfield_field_options_json_chk`');
        DB::statement('ALTER TABLE `tbl_formfield` DROP CONSTRAINT IF EXISTS `tbl_formfield_conditions_json_chk`');
    }
};
