<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<int, array{table: string, name: string, columns: array<int, string>}>
     */
    private function indexDefinitions(): array
    {
        return [
            [
                'table' => 'tbl_form_submission',
                'name' => 'idx_fs_account_workflow_status_submitted',
                'columns' => ['account_id', 'current_workflow_status', 'submitted_at'],
            ],
            [
                'table' => 'tbl_workflow_step_progress',
                'name' => 'idx_wsp_canonical_status_acted',
                'columns' => ['canonical_submission_id', 'status', 'acted_at'],
            ],
            [
                'table' => 'tbl_snapshot',
                'name' => 'idx_snapshot_canonical_status_created',
                'columns' => ['canonical_submission_id', 'status', 'created_at'],
            ],
            [
                'table' => 'tbl_user_role',
                'name' => 'idx_user_role_account_active_expiry',
                'columns' => ['account_id', 'is_active', 'expiry_date'],
            ],
            [
                'table' => 'tbl_slots',
                'name' => 'idx_slots_facility_date_status',
                'columns' => ['facility_id', 'date', 'status'],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexDefinitions() as $definition) {
            $table = $definition['table'];
            $columns = $definition['columns'];
            $name = $definition['name'];

            if (! Schema::hasTable($table) || ! $this->tableHasColumns($table, $columns)) {
                continue;
            }

            if ($this->hasIndexByName($table, $name) || $this->hasIndexForColumns($table, $columns)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexDefinitions() as $definition) {
            $table = $definition['table'];
            $name = $definition['name'];

            if (! Schema::hasTable($table) || ! $this->hasIndexByName($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function tableHasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasIndexByName(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [DB::getDatabaseName(), $table, $indexName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexForColumns(string $table, array $columns): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $columnList = implode(',', $columns);

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM (
                SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS index_columns
                FROM information_schema.statistics
                WHERE table_schema = ?
                  AND table_name = ?
                GROUP BY index_name
             ) AS indexes_for_table
             WHERE index_columns = ?',
            [DB::getDatabaseName(), $table, $columnList]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
