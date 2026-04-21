<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #1a202c;
            margin: 0;
            padding: 0;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: linear-gradient(135deg, #0d3b66 0%, #1e5f74 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: #0d3b66;
        }

        .header-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #64748b;
        }

        .content {
            margin-top: 100px;
            margin-bottom: 60px;
            padding: 0 30px;
        }

        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .table-header {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #f1f5f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #374151;
        }

        .status-created {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-updated {
            background: #fef3c7;
            color: #92400e;
        }

        .status-deleted {
            background: #fee2e2;
            color: #dc2626;
        }

        @media print {
            .data-table {
                page-break-inside: avoid;
            }

            tbody tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <div class="logo">OS</div>
            <div>
                <h1 class="header-title">OralSync</h1>
                <p class="header-subtitle">Professional Dental Management</p>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 14px; font-weight: 500;">{{ $title }}</div>
            <div style="font-size: 12px; opacity: 0.8;">Generated: {{ date('M j, Y H:i') }}</div>
        </div>
    </div>

    <div class="footer">
        <div>Page <span class="page-number"></span> of <span class="total-pages"></span></div>
    </div>

    <div class="content">
        <div class="data-table">
            <div class="table-header">
                <h2 class="table-title">{{ $title }}</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        @foreach($headers as $header)
                        <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                    <tr>
                        @foreach($headers as $header)
                        <td>
                            @if($header === 'Status' || $header === 'Activity Type')
                                <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $row[$header] ?? 'inactive')) }}">
                                    {{ $row[$header] ?? 'N/A' }}
                                </span>
                            @else
                                {{ $row[$header] ?? '' }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>