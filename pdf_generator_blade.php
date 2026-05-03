<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;
    private $tempFiles = [];

    public function __construct() {
        if (!defined('PDF_FONT_NAME_MAIN'))  define('PDF_FONT_NAME_MAIN',  'helvetica');
        if (!defined('PDF_FONT_SIZE_MAIN'))  define('PDF_FONT_SIZE_MAIN',  10);
        if (!defined('PDF_FONT_NAME_DATA'))  define('PDF_FONT_NAME_DATA',  'helvetica');
        if (!defined('PDF_FONT_SIZE_DATA'))  define('PDF_FONT_SIZE_DATA',  8);
        if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');

        $container = new \Jenssegers\Blade\Container;
        Container::setInstance($container);

        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache', $container);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setupPDF();
    }

    public function __destruct() {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }

    private function setupPDF() {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('OralSync');
        $this->pdf->SetTitle('OralSync Report');

        // Disable built-in header/footer — content is rendered entirely via writeHTML.
        // Leaving them enabled with zero margins causes TCPDF to emit stray bytes
        // that corrupt the PDF binary stream.
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->pdf->SetDefaultMonospacedFont('courier');

        // Standard A4 margins — zero margins corrupt TCPDF page generation.
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);

        if (defined('PDF_IMAGE_SCALE_RATIO')) {
            $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        }

        $this->pdf->SetFont('dejavusans', '', 10);
    }

    // -------------------------------------------------------------------------
    // Temp SVG helpers
    // -------------------------------------------------------------------------

    private function saveTempSVG($svg) {
        $tmp      = tempnam(sys_get_temp_dir(), 'os_chart');
        $filename = $tmp . '.svg';
        rename($tmp, $filename);
        file_put_contents($filename, $svg);
        $this->tempFiles[] = $filename;
        return $filename;
    }

    // -------------------------------------------------------------------------
    // SVG chart builders
    // -------------------------------------------------------------------------

    private function createLineChartSVG($config, $width, $height) {
        $data   = $config['data']['datasets'][0]['data'] ?? [];
        $labels = $config['data']['labels'] ?? [];

        $maxValue = !empty($data) ? max($data) : 1;
        $maxValue = $maxValue ?: 1;
        $maxValue = ceil($maxValue / 100) * 100;

        $chartWidth  = $width  - 80;
        $chartHeight = $height - 80;
        $xOffset     = 60;
        $yOffset     = 20;

        $svg  = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background:white;'>";
        $svg .= "<defs><linearGradient id='areaGradient' x1='0' y1='0' x2='0' y2='1'>"
              . "<stop offset='0%' stop-color='#0d3b66' stop-opacity='0.2'/>"
              . "<stop offset='100%' stop-color='#0d3b66' stop-opacity='0'/>"
              . "</linearGradient></defs>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<line x1='{$xOffset}' y1='{$y}' x2='" . ($xOffset + $chartWidth) . "' y2='{$y}' stroke='#f1f5f9' stroke-width='1'/>";
            $val  = ($maxValue / 4) * $i;
            $svg .= "<text x='50' y='" . ($y + 4) . "' text-anchor='end' font-family='Helvetica' font-size='10' fill='#64748b'>&#8369;" . number_format($val) . "</text>";
        }

        $points    = '';
        $areaPoints = "{$xOffset}," . ($yOffset + $chartHeight) . " ";
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / max(1, count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $points     .= ($i > 0 ? ' L ' : 'M ') . $x . ' ' . $y;
            $areaPoints .= "{$x},{$y} ";
        }
        $areaPoints .= ($xOffset + $chartWidth) . "," . ($yOffset + $chartHeight);

        $svg .= "<polyline points='{$areaPoints}' fill='url(#areaGradient)'/>";
        $svg .= "<path d='{$points}' fill='none' stroke='#0d3b66' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/>";

        foreach ($data as $i => $value) {
            $x    = $xOffset + ($i / max(1, count($data) - 1)) * $chartWidth;
            $y    = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $svg .= "<circle cx='{$x}' cy='{$y}' r='3' fill='white' stroke='#0d3b66' stroke-width='2'/>";
        }
        foreach ($labels as $i => $label) {
            if (count($labels) > 7 && $i % 2 !== 0) continue;
            $x    = $xOffset + ($i / max(1, count($labels) - 1)) * $chartWidth;
            $label = htmlspecialchars($label);
            $svg .= "<text x='{$x}' y='" . ($height - 40) . "' text-anchor='middle' font-family='Helvetica' font-size='10' fill='#64748b'>{$label}</text>";
        }
        $svg .= "</svg>";
        return $svg;
    }

    private function createBarChartSVG($config, $width, $height) {
        $data   = $config['data']['datasets'][0]['data'] ?? [];
        $labels = $config['data']['labels'] ?? [];

        $maxValue    = !empty($data) ? max($data) : 1;
        $maxValue    = $maxValue ?: 1;
        $maxValue    = ceil($maxValue / 100) * 100;
        $chartWidth  = $width  - 80;
        $chartHeight = $height - 80;
        $xOffset     = 60;
        $yOffset     = 20;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background:white;'>";
        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<line x1='{$xOffset}' y1='{$y}' x2='" . ($xOffset + $chartWidth) . "' y2='{$y}' stroke='#f1f5f9' stroke-width='1'/>";
            $val  = ($maxValue / 4) * $i;
            $svg .= "<text x='50' y='" . ($y + 4) . "' text-anchor='end' font-family='Helvetica' font-size='10' fill='#64748b'>&#8369;" . number_format($val) . "</text>";
        }

        $barSpacing = $chartWidth / max(1, count($data));
        $barWidth   = $barSpacing * 0.6;
        foreach ($data as $i => $value) {
            $h     = ($value / $maxValue) * $chartHeight;
            $x     = $xOffset + ($i * $barSpacing) + ($barSpacing - $barWidth) / 2;
            $y     = $yOffset + $chartHeight - $h;
            $svg  .= "<rect x='{$x}' y='{$y}' width='{$barWidth}' height='{$h}' fill='#0d3b66' rx='2'/>";
            $label = htmlspecialchars($labels[$i] ?? '');
            $svg  .= "<text x='" . ($x + $barWidth / 2) . "' y='" . ($height - 40) . "' text-anchor='middle' font-family='Helvetica' font-size='9' fill='#64748b' transform='rotate(-30 " . ($x + $barWidth / 2) . "," . ($height - 40) . ")'>{$label}</text>";
        }
        $svg .= "</svg>";
        return $svg;
    }

    private function createPieChartSVG($config, $width, $height) {
        $data   = $config['data']['datasets'][0]['data'] ?? [];
        $labels = $config['data']['labels'] ?? [];
        $colors = ['#0d3b66', '#1e5f74', '#64748b', '#94a3b8', '#cbd5e1'];
        $total  = array_sum($data) ?: 1;

        $centerX = $width / 2 - 40;
        $centerY = $height / 2;
        $radius  = min($centerX, $centerY) - 40;

        $svg          = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background:white;'>";
        $currentAngle = 0;

        foreach ($data as $i => $value) {
            $angle = ($value / $total) * 360;
            $x1    = $centerX + $radius * cos(deg2rad($currentAngle - 90));
            $y1    = $centerY + $radius * sin(deg2rad($currentAngle - 90));
            $currentAngle += $angle;
            $x2    = $centerX + $radius * cos(deg2rad($currentAngle - 90));
            $y2    = $centerY + $radius * sin(deg2rad($currentAngle - 90));

            $largeArcFlag = $angle > 180 ? 1 : 0;
            $pathData     = "M {$centerX} {$centerY} L {$x1} {$y1} A {$radius} {$radius} 0 {$largeArcFlag} 1 {$x2} {$y2} Z";
            $color        = $colors[$i % count($colors)];
            $label        = htmlspecialchars($labels[$i] ?? '');

            $svg .= "<path d='{$pathData}' fill='{$color}' stroke='white' stroke-width='1'/>";
            $svg .= "<rect x='" . ($width - 100) . "' y='" . (40 + $i * 20) . "' width='12' height='12' fill='{$color}' rx='2'/>";
            $svg .= "<text x='" . ($width - 80) . "' y='" . (50 + $i * 20) . "' font-family='Helvetica' font-size='10' fill='#64748b'>{$label}</text>";
        }
        $svg .= "</svg>";
        return $svg;
    }

    // -------------------------------------------------------------------------
    // Public report generators
    // -------------------------------------------------------------------------

    public function generateSalesReport($data, $title = 'Sales Report', $context = 'superadmin') {
        $keyMetrics   = $this->calculateKeyMetrics($data, $context);
        $charts       = $this->generateCharts($data, $context);
        $tableHeaders = ($context === 'superadmin')
            ? ['Date', 'Tenant', 'Plan', 'Amount', 'Status']
            : ['Date', 'Patient', 'Service', 'Amount', 'Status'];
        $tableData    = $this->prepareTableData($data, $context);

        // Try Blade view first; fall back to inline HTML so a valid PDF is
        // always produced even when the view file is missing or has errors.
        $viewPath = __DIR__ . '/views/sales_report.blade.php';
        if (file_exists($viewPath)) {
            try {
                $html = $this->blade->render('sales_report', [
                    'title'        => $title,
                    'keyMetrics'   => $keyMetrics,
                    'charts'       => $charts,
                    'tableHeaders' => $tableHeaders,
                    'tableData'    => $tableData,
                    'tableTitle'   => $context === 'superadmin' ? 'Subscription Transactions' : 'Patient Sales',
                    'context'      => $context,
                    'generatedAt'  => date('F j, Y H:i'),
                    'generatedBy'  => $context === 'superadmin' ? 'System Administrator' : 'Clinic Administrator',
                ]);
            } catch (\Exception $e) {
                error_log('OralSync Blade render failed: ' . $e->getMessage());
                $html = $this->buildFallbackHTML($title, $keyMetrics, $tableHeaders, $tableData, $charts);
            }
        } else {
            error_log('OralSync: sales_report.blade.php not found at ' . $viewPath . ' — using fallback HTML.');
            $html = $this->buildFallbackHTML($title, $keyMetrics, $tableHeaders, $tableData, $charts);
        }

        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');
        return $this->pdf->Output('', 'S');
    }

    public function generateGenericReport($data, $title, $headers = null) {
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }

        try {
            $html = $this->blade->render('generic_report', [
                'title'   => $title,
                'headers' => $headers,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            error_log('OralSync Blade render (generic) failed: ' . $e->getMessage());
            $html = $this->buildFallbackHTML($title, [], $headers ?? [], $data, []);
        }

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
                'Metric 1' => ['label' => 'Total Sales',     'value' => '&#8369;' . number_format($totalRevenue, 2)],
                'Metric 2' => ['label' => 'Active Tenants',  'value' => count($tenants)],
                'Metric 3' => ['label' => 'Monthly Growth',  'value' => '+12.5%'],
            ];
        }

        $count = count($data);
        return [
            'Metric 1' => ['label' => 'Total Sales',      'value' => '&#8369;' . number_format($totalRevenue, 2)],
            'Metric 2' => ['label' => 'Patient Visits',   'value' => $count],
            'Metric 3' => ['label' => 'Avg per Patient',  'value' => '&#8369;' . number_format($count > 0 ? $totalRevenue / $count : 0, 2)],
        ];
    }

    private function generateCharts($data, $context) {
        $charts = [];

        $trendData = $this->aggregateByDate($data);
        $charts['chart1'] = [
            'title' => $context === 'superadmin' ? 'Subscription Growth' : 'Patient Volume Trend',
            'path'  => $this->saveTempSVG($this->createLineChartSVG([
                'type' => 'line',
                'data' => ['labels' => array_keys($trendData), 'datasets' => [['data' => array_values($trendData)]]],
            ], 400, 300)),
        ];

        if ($context === 'superadmin') {
            $planData = $this->aggregateByField($data, 'plan');
            $charts['chart2'] = [
                'title' => 'Sales by Plan',
                'path'  => $this->saveTempSVG($this->createBarChartSVG([
                    'type' => 'bar',
                    'data' => ['labels' => array_keys($planData), 'datasets' => [['data' => array_values($planData)]]],
                ], 400, 300)),
            ];
        } else {
            $serviceData = $this->aggregateByField($data, 'service');
            $charts['chart2'] = [
                'title' => 'Service Distribution',
                'path'  => $this->saveTempSVG($this->createPieChartSVG([
                    'type' => 'pie',
                    'data' => ['labels' => array_keys($serviceData), 'datasets' => [['data' => array_values($serviceData)]]],
                ], 400, 300)),
            ];
        }

        return $charts;
    }

    private function aggregateByDate($data) {
        $aggregated = [];
        foreach ($data as $row) {
            // Support every date column name used across both superadmin and tenant queries
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
            if (empty($val)) $val = 'Other';
            $aggregated[$val] = ($aggregated[$val] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
        }
        return $aggregated;
    }

    private function prepareTableData($data, $context) {
        $prepared = [];
        foreach ($data as $row) {
            // Resolve the date from whichever column is present
            $rawDate = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            $date    = $rawDate ? date('M d, Y', strtotime($rawDate)) : 'N/A';

            if ($context === 'superadmin') {
                $prepared[] = [
                    'Date'   => $date,
                    'Tenant' => $row['tenant_name'] ?? 'N/A',
                    'Plan'   => $row['plan']         ?? $row['subscription_tier'] ?? 'N/A',
                    'Amount' => '&#8369;' . number_format((float)($row['amount'] ?? 0), 2),
                    'Status' => ucfirst($row['status'] ?? 'paid'),
                ];
            } else {
                $patient = $row['patient_name']
                    ?? (trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'N/A');
                $prepared[] = [
                    'Date'    => $date,
                    'Patient' => $patient,
                    'Service' => $row['service'] ?? $row['service_name'] ?? 'General',
                    'Amount'  => '&#8369;' . number_format((float)($row['amount'] ?? $row['amount_paid'] ?? 0), 2),
                    'Status'  => 'Paid',
                ];
            }
        }
        return $prepared;
    }

    /**
     * Self-contained HTML report used when the Blade view is missing or broken.
     * Renders cleanly via TCPDF's writeHTML without requiring any external assets.
     */
    private function buildFallbackHTML($title, $keyMetrics, $tableHeaders, $tableData, $charts) {
        $logoPath = __DIR__ . '/oral logo.png';
        $logoTag  = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoTag  = '<img src="data:image/png;base64,' . $logoData . '" style="height:40px;" />';
        }

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            body { font-family: dejavusans, sans-serif; color: #0f172a; margin: 0; padding: 0; }
            .header { background: #0d3b66; color: white; padding: 18px 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 18px; }
            .header p  { margin: 4px 0 0; font-size: 11px; opacity: 0.8; }
            .metrics { display: table; width: 100%; margin-bottom: 20px; border-collapse: separate; border-spacing: 8px; }
            .metric  { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; text-align: center; }
            .metric-value { font-size: 20px; font-weight: bold; color: #0d3b66; }
            .metric-label { font-size: 11px; color: #64748b; margin-top: 4px; }
            .section-title { font-size: 14px; font-weight: bold; color: #0d3b66; margin: 20px 0 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th { background: #0d3b66; color: white; padding: 8px 10px; text-align: left; }
            td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) td { background: #f8fafc; }
            .charts { display: table; width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 20px; }
            .chart-box { display: table-cell; background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center; }
            .chart-title { font-size: 12px; font-weight: bold; color: #0d3b66; margin-bottom: 8px; }
            .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        </style></head><body>';

        // Header
        $html .= '<div class="header">';
        $html .= $logoTag ? '<table><tr><td style="width:50px;">' . $logoTag . '</td><td><h1>' . htmlspecialchars($title) . '</h1><p>Generated on ' . date('F j, Y H:i') . '</p></td></tr></table>' : '<h1>' . htmlspecialchars($title) . '</h1><p>Generated on ' . date('F j, Y H:i') . '</p>';
        $html .= '</div>';

        // Key metrics
        if (!empty($keyMetrics)) {
            $html .= '<div class="section-title">Key Metrics</div>';
            $html .= '<div class="metrics">';
            foreach ($keyMetrics as $m) {
                $html .= '<div class="metric"><div class="metric-value">' . $m['value'] . '</div><div class="metric-label">' . htmlspecialchars($m['label']) . '</div></div>';
            }
            $html .= '</div>';
        }

        // Charts (embed as SVG images)
        if (!empty($charts)) {
            $html .= '<div class="section-title">Charts</div>';
            $html .= '<div class="charts">';
            foreach ($charts as $chart) {
                $html .= '<div class="chart-box"><div class="chart-title">' . htmlspecialchars($chart['title']) . '</div>';
                if (!empty($chart['path']) && file_exists($chart['path'])) {
                    $svgData = base64_encode(file_get_contents($chart['path']));
                    $html   .= '<img src="data:image/svg+xml;base64,' . $svgData . '" style="max-width:100%;height:auto;" />';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Transactions table
        if (!empty($tableData) && !empty($tableHeaders)) {
            $html .= '<div class="section-title">Transactions</div>';
            $html .= '<table><thead><tr>';
            foreach ($tableHeaders as $h) {
                $html .= '<th>' . htmlspecialchars($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($tableData as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . $cell . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">OralSync &mdash; Confidential Report &mdash; Page {nb}</div>';
        $html .= '</body></html>';

        return $html;
    }
}
