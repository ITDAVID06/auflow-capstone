<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $form['form_name'] }} Report</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 11px;
            line-height: 1.4;
        }

        /* ── Header ── */
        .pdf-header {
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .pdf-header-inner {
            display: table;
            width: 100%;
        }

        .pdf-header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 140px;
        }

        .pdf-header-logo .brand {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.5px;
        }

        .pdf-header-logo .brand-sub {
            font-size: 9px;
            color: #6b7280;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .pdf-header-meta {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            color: #374151;
            font-size: 10px;
        }

        .pdf-header-meta .report-title {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .pdf-header-meta .meta-line {
            margin-top: 2px;
            color: #4b5563;
        }

        /* ── Filter chips ── */
        .filters {
            margin-bottom: 12px;
            font-size: 10px;
            color: #374151;
        }

        .filters .filter-label {
            font-weight: 600;
            margin-right: 4px;
        }

        .chip {
            display: inline-block;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 1px 6px;
            margin-right: 4px;
            margin-bottom: 4px;
            background: #f3f4f6;
            color: #374151;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        thead th {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            border-top: 1px solid #d1d5db;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #374151;
            padding: 6px 8px;
            font-weight: 700;
        }

        tbody td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
            color: #111827;
            padding: 6px 8px;
            vertical-align: top;
            word-break: break-word;
        }

        tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .empty-row td {
            text-align: center;
            padding: 20px;
            color: #6b7280;
        }

        /* ── Page breaks ── */
        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        /* ── Footer ── */
        .pdf-footer {
            margin-top: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 9px;
            color: #9ca3af;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="pdf-header">
    <div class="pdf-header-inner">
        <div class="pdf-header-logo">
            <div class="brand">AUFlow</div>
            <div class="brand-sub">Automated Workflow System</div>
        </div>
        <div class="pdf-header-meta">
            <div class="report-title">{{ $form['form_name'] }} Report</div>
            <div class="meta-line">Form Code: {{ $form['form_code'] }}</div>
            <div class="meta-line">Generated: {{ \Carbon\CarbonImmutable::parse($generatedAt)->format('d M Y, H:i:s') }}</div>
            <div class="meta-line">Total Rows: {{ count($rows) }}</div>
        </div>
    </div>
</div>

@if(!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['submission_status']) || !empty($filters['submitter']) || !empty($filters['export_limit']))
<div class="filters">
    <span class="filter-label">Filters:</span>
    @if(!empty($filters['date_from']) || !empty($filters['date_to']))
        <span class="chip">Date: {{ $filters['date_from'] ?? 'Any' }} &rarr; {{ $filters['date_to'] ?? 'Any' }}</span>
    @endif
    @if(!empty($filters['submission_status']))
        <span class="chip">Status: {{ ucfirst((string) $filters['submission_status']) }}</span>
    @endif
    @if(!empty($filters['submitter']))
        <span class="chip">Submitter: {{ $filters['submitter'] }}</span>
    @endif
    @if(!empty($filters['export_limit']))
        <span class="chip">Export Limit: {{ $filters['export_limit'] }}</span>
    @endif
</div>
@endif

<table>
    <thead>
    <tr>
        @foreach($columns as $column)
            <th>{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            @foreach($columns as $column)
                <td>{{ $row[$column['key']] ?? '' }}</td>
            @endforeach
        </tr>
    @empty
        <tr class="empty-row">
            <td colspan="{{ count($columns) }}">No submissions match the selected filters.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="pdf-footer">
    AUFlow &mdash; {{ \Carbon\CarbonImmutable::parse($generatedAt)->format('d M Y') }} &mdash; Confidential
</div>

</body>
</html>
