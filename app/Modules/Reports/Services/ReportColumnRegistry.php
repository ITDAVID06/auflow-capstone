<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;

class ReportColumnRegistry
{
    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function resolveAllColumns(Form $form): array
    {
        $columns = [
            ['key' => 'id', 'label' => 'Submission ID', 'type' => 'system'],
            ['key' => 'submitter_name', 'label' => 'Submitted By', 'type' => 'system'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'system'],
            ['key' => 'submission_status', 'label' => 'Submission Status', 'type' => 'system'],
        ];

        foreach ($form->fields as $field) {
            if (! is_string($field->field_name) || $field->field_name === '') {
                continue;
            }

            $columns[] = [
                'key' => (string) $field->field_name,
                'label' => (string) $field->label,
                'type' => (string) $field->data_type,
            ];
        }

        $columns[] = ['key' => 'workflow_status', 'label' => 'Workflow Status', 'type' => 'system'];
        $columns[] = ['key' => 'attachments', 'label' => 'Attachments', 'type' => 'system'];
        $columns[] = ['key' => 'snapshot', 'label' => 'Snapshot', 'type' => 'system'];
        $columns[] = ['key' => 'created_at', 'label' => 'Submitted On', 'type' => 'system'];

        return $columns;
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $allColumns
     * @param  mixed  $selectedKeys
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function resolveSelectedColumns(array $allColumns, $selectedKeys): array
    {
        if (! is_array($selectedKeys) || $selectedKeys === []) {
            return $allColumns;
        }

        $columnMap = [];
        foreach ($allColumns as $column) {
            $columnMap[$column['key']] = $column;
        }

        $selectedColumns = [];
        foreach ($selectedKeys as $selectedKey) {
            if (! is_string($selectedKey)) {
                continue;
            }

            if (! isset($columnMap[$selectedKey])) {
                continue;
            }

            $selectedColumns[] = $columnMap[$selectedKey];
        }

        return $selectedColumns === [] ? $allColumns : $selectedColumns;
    }

    /**
     * @return array<int, string>
     */
    public function keys(array $columns): array
    {
        return array_values(array_column($columns, 'key'));
    }

    /**
     * @return array<string, string>
     */
    public function resolveFormFieldTypes(Form $form): array
    {
        $types = [];
        foreach ($form->fields as $field) {
            if (! is_string($field->field_name) || $field->field_name === '') {
                continue;
            }

            $types[$field->field_name] = strtolower((string) ($field->data_type ?? 'text'));
        }

        return $types;
    }
}
