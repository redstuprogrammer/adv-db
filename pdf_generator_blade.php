<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;

    public function __construct() {
        if (!defined('PDF_FONT_NAME_MAIN'))  define('PDF_FONT_NAME_MAIN',  'helvetica');
        if (!defined('PDF_FONT_SIZE_MAIN'))  define('PDF_FONT_SIZE_MAIN',  10);
        if (!defined('PDF_FONT_NAME_DATA'))  define('PDF_FONT_NAME_DATA',  'helvetica');
        if (!defined('PDF_FONT_SIZE_DATA'))  define('PDF_FONT_SIZE_DATA',  8);
        if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');

        // Suppress the Jenssegers nullable-parameter deprecation warning so it
        // never leaks a byte into the PDF binary stream.
        $prev = error_reporting(E_ALL & ~E_DEPRECATED);
        $container = new \Jenssegers\Blade\Container;
        Container::setInstance($container);
        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache', $container);
        error_reporting($prev);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setupPDF();
    }

    private function setupPDF() {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('OralSync');
        $this->pdf->SetTitle('OralSync Report');

        // Disable built-in header/footer — all content rendered via writeHTML.
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->pdf->SetDefaultMonospacedFont('courier');
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);

        if (defined('PDF_IMAGE_SCALE_RATIO')) {
            $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        }

        $this->pdf->SetFont('dejavusans', '', 10);
    }

    // -------------------------------------------------------------------------
    // SVG chart builders
    // Charts are returned as raw SVG strings and embedded INLINE into the HTML.
    // Do NOT use <img src="..."> or temp files — Azure App Service blocks them.
    // -------------------------------------------------------------------------

    private function createLineChartSVG($data, $labels, $width = 380, $height = 200) {
        $maxValue = !empty($data) ? max($data) : 1;
        $maxValue = $maxValue ?: 1;
        $maxValue = ceil($maxValue / 100) * 100;

        $chartW = $width  - 70;
        $chartH = $height - 50;
        $xOff   = 60;
        $yOff   = 10;
        $count  = count($data);

        $svg  = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$width}' height='{$height}' fill='white'/>";
        $svg .= "<defs><linearGradient id='ag' x1='0' y1='0' x2='0' y2='1'>"
              . "<stop offset='0%' stop-color='#0d3b66' stop-opacity='0.15'/>"
              . "<stop offset='100%' stop-color='#0d3b66' stop-opacity='0'/>"
              . "</linearGradient></defs>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOff + $chartH - ($i / 4) * $chartH;
            $val = number_format(($maxValue / 4) * $i);
            $svg .= "<line x1='{$xOff}' y1='{$y}' x2='" . ($xOff + $chartW) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $svg .= "<text x='55' y='" . ($y + 3) . "' text-anchor='end' font-size='8' fill='#94a3b8'>P{$val}</text>";
        }

        if ($count > 0) {
            $pts  = '';
            $area = "{$xOff}," . ($yOff + $chartH) . " ";
            foreach ($data as $i => $v) {
                $x    = $xOff + ($count > 1 ? ($i / ($count - 1)) * $chartW : $chartW / 2);
                $y    = $yOff + $chartH - ($v / $maxValue) * $chartH;
                $pts .= ($i === 0 ? "M{$x} {$y}" : " L{$x} {$y}");
                $area .= "{$x},{$y} ";
            }
            $area .= ($xOff + $chartW) . "," . ($yOff + $chartH);

            $svg .= "<polygon points='{$area}' fill='url(#ag)'/>";
            $svg .= "<path d='{$pts}' fill='none' stroke='#0d3b66' stroke-width='2' stroke-linejoin='round'/>";

            foreach ($data as $i => $v) {
                $x     = $xOff + ($count > 1 ? ($i / ($count - 1)) * $chartW : $chartW / 2);
                $y     = $yOff + $chartH - ($v / $maxValue) * $chartH;
                $svg  .= "<circle cx='{$x}' cy='{$y}' r='3' fill='white' stroke='#0d3b66' stroke-width='1.5'/>";
                $label = htmlspecialchars($labels[$i] ?? '');
                if ($count <= 7 || $i % 2 === 0) {
                    $svg .= "<text x='{$x}' y='" . ($yOff + $chartH + 14) . "' text-anchor='middle' font-size='8' fill='#94a3b8'>{$label}</text>";
                }
            }
        }

        $svg .= "</svg>";
        return $svg;
    }

    private function createBarChartSVG($data, $labels, $width = 380, $height = 200) {
        $maxValue   = !empty($data) ? max($data) : 1;
        $maxValue   = $maxValue ?: 1;
        $maxValue   = ceil($maxValue / 100) * 100;
        $chartW     = $width  - 70;
        $chartH     = $height - 50;
        $xOff       = 60;
        $yOff       = 10;
        $count      = count($data);
        $barSpacing = $count > 0 ? $chartW / $count : $chartW;
        $barW       = $barSpacing * 0.6;

        $svg  = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$width}' height='{$height}' fill='white'/>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOff + $chartH - ($i / 4) * $chartH;
            $val = number_format(($maxValue / 4) * $i);
            $svg .= "<line x1='{$xOff}' y1='{$y}' x2='" . ($xOff + $chartW) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $svg .= "<text x='55' y='" . ($y + 3) . "' text-anchor='end' font-size='8' fill='#94a3b8'>P{$val}</text>";
        }

        foreach ($data as $i => $v) {
            $h     = ($v / $maxValue) * $chartH;
            $x     = $xOff + ($i * $barSpacing) + ($barSpacing - $barW) / 2;
            $y     = $yOff + $chartH - $h;
            $svg  .= "<rect x='{$x}' y='{$y}' width='{$barW}' height='{$h}' fill='#0d3b66' rx='2'/>";
            $label = htmlspecialchars($labels[$i] ?? '');
            $svg  .= "<text x='" . ($x + $barW / 2) . "' y='" . ($yOff + $chartH + 14) . "' text-anchor='middle' font-size='8' fill='#94a3b8'>{$label}</text>";
        }

        $svg .= "</svg>";
        return $svg;
    }

    private function createPieChartSVG($data, $labels, $width = 380, $height = 200) {
        $colors       = ['#0d3b66', '#1e5f74', '#64748b', '#94a3b8', '#cbd5e1', '#475569'];
        $total        = array_sum($data) ?: 1;
        $cx           = 100;
        $cy           = $height / 2;
        $radius       = min($cx, $cy) - 15;
        $currentAngle = -90;

        $svg  = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$width}' height='{$height}' fill='white'/>";

        foreach ($data as $i => $v) {
            $angle        = ($v / $total) * 360;
            $x1           = $cx + $radius * cos(deg2rad($currentAngle));
            $y1           = $cy + $radius * sin(deg2rad($currentAngle));
            $currentAngle += $angle;
            $x2           = $cx + $radius * cos(deg2rad($currentAngle));
            $y2           = $cy + $radius * sin(deg2rad($currentAngle));
            $large        = $angle > 180 ? 1 : 0;
            $color        = $colors[$i % count($colors)];
            $label        = htmlspecialchars($labels[$i] ?? '');
            $pct          = number_format(($v / $total) * 100, 1);

            $svg .= "<path d='M{$cx} {$cy} L{$x1} {$y1} A{$radius} {$radius} 0 {$large} 1 {$x2} {$y2} Z' fill='{$color}' stroke='white' stroke-width='1'/>";

            $ly   = 20 + $i * 22;
            $svg .= "<rect x='210' y='" . ($ly - 8) . "' width='10' height='10' fill='{$color}' rx='2'/>";
            $svg .= "<text x='226' y='{$ly}' font-size='9' fill='#334155'>{$label} ({$pct}%)</text>";
        }

        $svg .= "</svg>";
        return $svg;
    }

    // -------------------------------------------------------------------------
    // Public report generators
    // -------------------------------------------------------------------------

    public function generateSalesReport($data, $title = 'Sales Report', $context = 'superadmin') {
        $keyMetrics   = $this->calculateKeyMetrics($data, $context);
        $chartSVGs    = $this->generateChartSVGs($data, $context);
        $tableHeaders = ($context === 'superadmin')
            ? ['Date', 'Tenant', 'Plan', 'Amount', 'Status']
            : ['Date', 'Patient', 'Service', 'Amount', 'Status'];
        $tableData    = $this->prepareTableData($data, $context);
        $tableTitle   = $context === 'superadmin' ? 'Subscription Transactions' : 'Patient Sales';

        $html = $this->buildHTML($title, $keyMetrics, $chartSVGs, $tableHeaders, $tableData, $tableTitle);

        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');
        return $this->pdf->Output('', 'S');
    }

    public function generateGenericReport($data, $title, $headers = null) {
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }
        $html = $this->buildHTML($title, [], [], $headers ?? [], $data, 'Records');
        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');
        return $this->pdf->Output('', 'S');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function calculateKeyMetrics($data, $context) {
        $totalRevenue = 0;
        foreach ($data as $row) {
            $totalRevenue += (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
        }

        if ($context === 'superadmin') {
            $tenants = [];
            foreach ($data as $row) {
                if (!empty($row['tenant_name'])) $tenants[$row['tenant_name']] = true;
            }
            return [
                ['label' => 'Total Sales',    'value' => 'P' . number_format($totalRevenue, 2)],
                ['label' => 'Active Tenants', 'value' => count($tenants)],
                ['label' => 'Transactions',   'value' => count($data)],
            ];
        }

        $count = count($data);
        return [
            ['label' => 'Total Sales',     'value' => 'P' . number_format($totalRevenue, 2)],
            ['label' => 'Patient Visits',  'value' => $count],
            ['label' => 'Avg per Patient', 'value' => 'P' . number_format($count > 0 ? $totalRevenue / $count : 0, 2)],
        ];
    }

    private function generateChartSVGs($data, $context) {
        $trendData = $this->aggregateByDate($data);
        $chart1    = [
            'title' => $context === 'superadmin' ? 'Revenue Trend' : 'Patient Volume Trend',
            'svg'   => $this->createLineChartSVG(array_values($trendData), array_keys($trendData)),
        ];

        if ($context === 'superadmin') {
            $planData = $this->aggregateByField($data, 'plan');
            $chart2   = [
                'title' => 'Sales by Plan',
                'svg'   => $this->createBarChartSVG(array_values($planData), array_keys($planData)),
            ];
        } else {
            $serviceData = $this->aggregateByField($data, 'service');
            $chart2      = [
                'title' => 'Service Distribution',
                'svg'   => $this->createPieChartSVG(array_values($serviceData), array_keys($serviceData)),
            ];
        }

        return [$chart1, $chart2];
    }

    private function aggregateByDate($data) {
        $aggregated = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) {
                $key = date('M d', strtotime($date));
                $aggregated[$key] = ($aggregated[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
            }
        }
        return array_slice($aggregated, -7, null, true);
    }

    private function aggregateByField($data, $field) {
        $aggregated = [];
        foreach ($data as $row) {
            $val = $row[$field] ?? $row['service_name'] ?? $row['subscription_tier'] ?? 'Other';
            if (empty(trim((string)$val))) $val = 'Other';
            $aggregated[$val] = ($aggregated[$val] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
        }
        return $aggregated;
    }

    private function prepareTableData($data, $context) {
        $prepared = [];
        foreach ($data as $row) {
            $rawDate = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            $date    = $rawDate ? date('M d, Y', strtotime($rawDate)) : 'N/A';

            if ($context === 'superadmin') {
                $prepared[] = [
                    $date,
                    $row['tenant_name'] ?? 'N/A',
                    $row['plan'] ?? $row['subscription_tier'] ?? 'N/A',
                    'P' . number_format((float)($row['amount'] ?? 0), 2),
                    ucfirst($row['status'] ?? 'paid'),
                ];
            } else {
                $patient = $row['patient_name']
                    ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if (!$patient) $patient = 'N/A';
                $prepared[] = [
                    $date,
                    $patient,
                    $row['service'] ?? $row['service_name'] ?? 'General',
                    'P' . number_format((float)($row['amount'] ?? $row['amount_paid'] ?? 0), 2),
                    'Paid',
                ];
            }
        }
        return $prepared;
    }

    /**
     * Builds a complete HTML string for TCPDF.
     * SVG charts are embedded INLINE — TCPDF renders them natively.
     * No <img> tags, no temp files, no base64 — all three fail on Azure.
     */
    private function buildHTML($title, $keyMetrics, $chartSVGs, $tableHeaders, $tableData, $tableTitle) {
        $generatedAt = date('F j, Y H:i');

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= '
        body  { font-family:dejavusans,sans-serif; color:#0f172a; font-size:10px; margin:0; padding:0; }
        .hdr  { background:#0d3b66; color:white; padding:14px 16px; margin-bottom:16px; }
        .hdr h1 { margin:0 0 3px; font-size:15px; }
        .hdr p  { margin:0; font-size:9px; opacity:0.8; }
        .sec  { font-size:12px; font-weight:bold; color:#0d3b66; border-bottom:2px solid #e2e8f0; padding-bottom:3px; margin:14px 0 8px; }
        table.metrics { width:100%; border-collapse:separate; border-spacing:6px; margin-bottom:12px; }
        td.metric { background:#f8fafc; border:1px solid #e2e8f0; padding:12px 8px; text-align:center; width:33%; }
        .mv { font-size:15px; font-weight:bold; color:#0d3b66; }
        .ml { font-size:9px; color:#64748b; margin-top:3px; }
        table.charts { width:100%; border-collapse:separate; border-spacing:6px; margin-bottom:12px; }
        td.chart { background:white; border:1px solid #e2e8f0; padding:8px; text-align:center; width:50%; vertical-align:top; }
        .ct { font-size:10px; font-weight:bold; color:#0d3b66; margin-bottom:6px; }
        table.data { width:100%; border-collapse:collapse; font-size:9px; }
        table.data th { background:#0d3b66; color:white; padding:7px 8px; text-align:left; }
        table.data td { padding:6px 8px; border-bottom:1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background:#f8fafc; }
        .footer { margin-top:20px; border-top:1px solid #e2e8f0; padding-top:6px; font-size:8px; color:#94a3b8; text-align:center; }
        ';
        $html .= '</style></head><body>';

        // Header
        $html .= '<div class="hdr">'
               . '<h1>' . htmlspecialchars($title) . '</h1>'
               . '<p>Generated: ' . $generatedAt . '</p>'
               . '</div>';

        // Key metrics
        if (!empty($keyMetrics)) {
            $html .= '<div class="sec">Key Metrics</div><table class="metrics"><tr>';
            foreach ($keyMetrics as $m) {
                $html .= '<td class="metric">'
                       . '<div class="mv">' . htmlspecialchars((string)$m['value']) . '</div>'
                       . '<div class="ml">' . htmlspecialchars($m['label']) . '</div>'
                       . '</td>';
            }
            $html .= '</tr></table>';
        }

        // Charts — inline SVG, not <img>
        if (!empty($chartSVGs)) {
            $html .= '<div class="sec">Charts</div><table class="charts"><tr>';
            foreach ($chartSVGs as $chart) {
                $html .= '<td class="chart">'
                       . '<div class="ct">' . htmlspecialchars($chart['title']) . '</div>'
                       . $chart['svg']   // raw <svg>...</svg> embedded directly
                       . '</td>';
            }
            $html .= '</tr></table>';
        }

        // Data table
        if (!empty($tableHeaders)) {
            $html .= '<div class="sec">' . htmlspecialchars($tableTitle) . '</div>';
            $html .= '<table class="data"><thead><tr>';
            foreach ($tableHeaders as $h) {
                $html .= '<th>' . htmlspecialchars($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            if (!empty($tableData)) {
                foreach ($tableData as $row) {
                    $html .= '<tr>';
                    foreach ($row as $cell) {
                        $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="' . count($tableHeaders) . '" style="text-align:center;padding:16px;color:#94a3b8;">No records found.</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">OralSync &mdash; Confidential &mdash; All amounts in Philippine Peso (PHP)</div>';
        $html .= '</body></html>';

        return $html;
    }
}
