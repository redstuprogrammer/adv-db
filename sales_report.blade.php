<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: dejavusans, sans-serif;
        color: #0f172a;
        margin: 0;
        padding: 0;
        font-size: 11px;
    }

    /* ── Header ──────────────────────────────────────────────── */
    .report-header {
        background-color: #0d3b66;
        color: white;
        padding: 16px 20px;
        margin-bottom: 18px;
    }
    .report-header h1 {
        margin: 0 0 4px;
        font-size: 17px;
        font-weight: bold;
    }
    .report-header p {
        margin: 0;
        font-size: 10px;
        opacity: 0.8;
    }

    /* ── Section titles ──────────────────────────────────────── */
    .section-title {
        font-size: 13px;
        font-weight: bold;
        color: #0d3b66;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 4px;
        margin: 18px 0 10px;
    }

    /* ── Key metrics — use a table so TCPDF lays them out side-by-side ── */
    .metrics-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px;
        margin-bottom: 16px;
    }
    .metric-cell {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 14px 10px;
        text-align: center;
        width: 33%;
    }
    .metric-value {
        font-size: 18px;
        font-weight: bold;
        color: #0d3b66;
    }
    .metric-label {
        font-size: 10px;
        color: #64748b;
        margin-top: 4px;
    }

    /* ── Charts ──────────────────────────────────────────────── */
    .charts-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px;
        margin-bottom: 16px;
    }
    .chart-cell {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px;
        text-align: center;
        width: 50%;
        vertical-align: top;
    }
    .chart-cell-title {
        font-size: 11px;
        font-weight: bold;
        color: #0d3b66;
        margin-bottom: 8px;
    }

    /* ── Transactions table ───────────────────────────────────── */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    .data-table th {
        background-color: #0d3b66;
        color: white;
        padding: 8px 10px;
        text-align: left;
        font-weight: bold;
    }
    .data-table td {
        padding: 7px 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    .data-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }

    /* ── Footer ──────────────────────────────────────────────── */
    .report-footer {
        margin-top: 24px;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
        font-size: 9px;
        color: #94a3b8;
        text-align: center;
    }
</style>
</head>
<body>

{{-- ── Report header ────────────────────────────────────────── --}}
<div class="report-header">
    <h1>{{ $title }}</h1>
    <p>Generated on {{ $generatedAt }} &nbsp;|&nbsp; By: {{ $generatedBy }}</p>
</div>

{{-- ── Key metrics ──────────────────────────────────────────── --}}
<div class="section-title">Key Metrics</div>
<table class="metrics-table">
    <tr>
        @foreach($keyMetrics as $metric)
        <td class="metric-cell">
            <div class="metric-value">{!! $metric['value'] !!}</div>
            <div class="metric-label">{{ $metric['label'] }}</div>
        </td>
        @endforeach
    </tr>
</table>

{{-- ── Charts ───────────────────────────────────────────────── --}}
@if(!empty($charts))
<div class="section-title">Charts</div>
<table class="charts-table">
    <tr>
        @foreach($charts as $chart)
        <td class="chart-cell">
            <div class="chart-cell-title">{{ $chart['title'] }}</div>
            @if(!empty($chart['path']) && file_exists($chart['path']))
                {{-- TCPDF supports SVG via <img> with a file:// path --}}
                <img src="{{ $chart['path'] }}" style="max-width:100%;height:auto;" />
            @endif
        </td>
        @endforeach
    </tr>
</table>
@endif

{{-- ── Transactions table ───────────────────────────────────── --}}
<div class="section-title">{{ $tableTitle }}</div>
<table class="data-table">
    <thead>
        <tr>
            @foreach($tableHeaders as $header)
            <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($tableData as $row)
        <tr>
            @foreach($row as $cell)
            <td>{!! $cell !!}</td>
            @endforeach
        </tr>
        @empty
        <tr>
            <td colspan="{{ count($tableHeaders) }}" style="text-align:center;padding:20px;color:#64748b;">
                No records found.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="report-footer">
    OralSync &mdash; Confidential Report &mdash; All figures in Philippine Peso (PHP)
</div>

</body>
</html>
