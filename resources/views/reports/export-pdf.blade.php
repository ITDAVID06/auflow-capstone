<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $form['form_name'] }} Report</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #111827;
            background: #f9fafb;
            line-height: 1.45;
        }

        .page {
            margin: 0 auto;
            max-width: 1080px;
            padding: 20px;
        }

        .header {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .meta {
            margin-top: 8px;
            color: #4b5563;
            font-size: 14px;
        }

        .filters {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border: 1px solid #d1d5db;
            border-radius: 9999px;
            padding: 4px 10px;
            font-size: 12px;
            color: #374151;
            background: #f3f4f6;
        }

        .actions {
            margin-top: 12px;
        }

        .actions button {
            border: 1px solid #111827;
            background: #111827;
            color: #ffffff;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 14px;
            cursor: pointer;
        }

        .table-wrap {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #374151;
            padding: 10px;
        }

        tbody td {
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
            color: #111827;
            padding: 10px;
            vertical-align: top;
            word-break: break-word;
        }

        tbody tr:nth-child(even) {
            background: #fcfcfd;
        }

        .empty {
            padding: 28px;
            color: #4b5563;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .header,
            .table-wrap {
                border: none;
                border-radius: 0;
            }

            .actions {
                display: none;
            }

            @page {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <section class="header">
        <h1 class="title">{{ $form['form_name'] }} Report</h1>
        <div class="meta">
            Form Code: {{ $form['form_code'] }}
            <br>
            Generated: {{ \Carbon\CarbonImmutable::parse($generatedAt)->format('Y-m-d H:i:s') }}
            <br>
            Rows: {{ count($rows) }}
        </div>

        <div class="filters">
            @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                <span class="chip">Date: {{ $filters['date_from'] ?? 'Any' }} to {{ $filters['date_to'] ?? 'Any' }}</span>
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

        <div class="actions">
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </section>

    <section class="table-wrap">
        @if(empty($rows))
            <div class="empty">No submissions match the selected filters.</div>
        @else
            <table>
                <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($columns as $column)
                            <td>{{ $row[$column['key']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>

@if($autoprint)
    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
@endif
</body>
</html>
