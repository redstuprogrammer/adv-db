<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use HeadlessChromium\BrowserFactory;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;

    public function __construct() {
        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache');
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->setupPDF();
    }

    private function setupPDF() {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('Super Admin');
        $this->pdf->SetTitle('OralSync Report');

$this->pdf->setHeaderFont(Array('dejavusans', '', PDF_FONT_SIZE_MAIN));

        $this->pdf->setFooterFont(Array(PDF_FONT_DATA, '', PDF_FONT_SIZE_DATA));

        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetHeaderMargin(0);
        $this->pdf->SetFooterMargin(0);
        $this->pdf->SetAutoPageBreak(TRUE, 0);
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    }

    public function generateChartImage($chartConfig, $width = 400, $height = 300) {
        // Create simple SVG charts instead of using headless browser
        if ($chartConfig['type'] === 'line') {
            return $this->createLineChartSVG($chartConfig, $width, $height);
        } elseif ($chartConfig['type'] === 'bar') {
            return $this->createBarChartSVG($chartConfig, $width, $height);
        } elseif ($chartConfig['type'] === 'pie') {
            return $this->createPieChartSVG($chartConfig, $width, $height);
        } else {
            return $this->createPlaceholderChart($chartConfig, $width, $height);
        }
    }

    private function createLineChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = max($data) ?: 1;
        $maxValue = ceil($maxValue / 100) * 100; // Round up for better grid

        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white;'>";
        $svg .= "<defs>
                    <linearGradient id='areaGradient' x1='0' y1='0' x2='0' y2='1'>
                        <stop offset='0%' stop-color='#0d3b66' stop-opacity='0.2'/>
                        <stop offset='100%' stop-color='#0d3b66' stop-opacity='0'/>
                    </linearGradient>
                </defs>";

        // Grid lines
        for ($i = 0; $i <= 4; $i++) {
            $y = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<line x1='{$xOffset}' y1='{$y}' x2='" . ($xOffset + $chartWidth) . "' y2='{$y}' stroke='#f1f5f9' stroke-width='1'/>";
            $val = ($maxValue / 4) * $i;
            $svg .= "<text x='50' y='" . ($y + 4) . "' text-anchor='end' font-family='Helvetica' font-size='10' fill='#64748b'>₱" . number_format($val) . "</text>";
        }

        // Area and Line
        $points = '';
        $areaPoints = "{$xOffset}," . ($yOffset + $chartHeight) . " ";
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / max(1, count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $points .= ($i > 0 ? ' L ' : 'M ') . $x . ' ' . $y;
            $areaPoints .= "{$x},{$y} ";
        }
        $areaPoints .= ($xOffset + $chartWidth) . "," . ($yOffset + $chartHeight);

        $svg .= "<polyline points='{$areaPoints}' fill='url(#areaGradient)' />";
        $svg .= "<path d='{$points}' fill='none' stroke='#0d3b66' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/>";

        // Points
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / max(1, count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $svg .= "<circle cx='{$x}' cy='{$y}' r='3' fill='white' stroke='#0d3b66' stroke-width='2'/>";
        }

        // X Labels
        foreach ($labels as $i => $label) {
            if (count($labels) > 7 && $i % 2 !== 0) continue; // Skip some labels if too many
            $x = $xOffset + ($i / max(1, count($labels) - 1)) * $chartWidth;
            $svg .= "<text x='{$x}' y='" . ($height - 40) . "' text-anchor='middle' font-family='Helvetica' font-size='10' fill='#64748b'>{$label}</text>";
        }

        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function createBarChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = max($data) ?: 1;
        $maxValue = ceil($maxValue / 100) * 100;

        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white;'>";
        
        // Grid
        for ($i = 0; $i <= 4; $i++) {
            $y = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<line x1='{$xOffset}' y1='{$y}' x2='" . ($xOffset + $chartWidth) . "' y2='{$y}' stroke='#f1f5f9' stroke-width='1'/>";
            $val = ($maxValue / 4) * $i;
            $svg .= "<text x='50' y='" . ($y + 4) . "' text-anchor='end' font-family='Helvetica' font-size='10' fill='#64748b'>₱" . number_format($val) . "</text>";
        }

        $barSpacing = $chartWidth / max(1, count($data));
        $barWidth = $barSpacing * 0.6;

        foreach ($data as $i => $value) {
            $h = ($value / $maxValue) * $chartHeight;
            $x = $xOffset + ($i * $barSpacing) + ($barSpacing - $barWidth) / 2;
            $y = $yOffset + $chartHeight - $h;
            
            $svg .= "<rect x='{$x}' y='{$y}' width='{$barWidth}' height='{$h}' fill='#0d3b66' rx='2' />";
            
            $label = $labels[$i] ?? '';
            $svg .= "<text x='" . ($x + $barWidth/2) . "' y='" . ($height - 40) . "' text-anchor='middle' font-family='Helvetica' font-size='9' fill='#64748b' transform='rotate(-30 " . ($x + $barWidth/2) . "," . ($height - 40) . ")'>{$label}</text>";
        }

        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function createPieChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $colors = ['#0d3b66', '#1e5f74', '#64748b', '#94a3b8', '#cbd5e1'];
        
        $total = array_sum($data) ?: 1;
        $centerX = $width / 2 - 40;
        $centerY = $height / 2;
        $radius = min($centerX, $centerY) - 40;
        
        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white;'>";
        
        $currentAngle = 0;
        foreach ($data as $i => $value) {
            $angle = ($value / $total) * 360;
            $x1 = $centerX + $radius * cos(deg2rad($currentAngle - 90));
            $y1 = $centerY + $radius * sin(deg2rad($currentAngle - 90));
            
            $currentAngle += $angle;
            
            $x2 = $centerX + $radius * cos(deg2rad($currentAngle - 90));
            $y2 = $centerY + $radius * sin(deg2rad($currentAngle - 90));
            
            $largeArcFlag = $angle > 180 ? 1 : 0;
            
            $pathData = "M {$centerX} {$centerY} L {$x1} {$y1} A {$radius} {$radius} 0 {$largeArcFlag} 1 {$x2} {$y2} Z";
            $color = $colors[$i % count($colors)];
            
            $svg .= "<path d='{$pathData}' fill='{$color}' stroke='white' stroke-width='1'/>";
            
            // Legend
            $svg .= "<rect x='" . ($width - 100) . "' y='" . (40 + $i * 20) . "' width='12' height='12' fill='{$color}' rx='2'/>";
            $svg .= "<text x='" . ($width - 80) . "' y='" . (50 + $i * 20) . "' font-family='Helvetica' font-size='10' fill='#64748b'>" . ($labels[$i] ?? '') . "</text>";
        }
        
        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function createPlaceholderChart($config, $width, $height) {
        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>
            <rect width='100%' height='100%' fill='#f8fafc'/>
            <text x='50%' y='50%' text-anchor='middle' dy='.3em' fill='#64748b' font-family='Helvetica' font-size='14'>
                " . (isset($config['data']['datasets'][0]['label']) ? $config['data']['datasets'][0]['label'] : 'Chart') . "
            </text>
        </svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function generateSalesReport($data, $title = 'Sales Report', $context = 'superadmin') {
        // Calculate key metrics based on context
        $keyMetrics = $this->calculateKeyMetrics($data, $context);

        // Generate charts based on context
        $charts = $this->generateCharts($data, $context);

        // Prepare table data based on context
        if ($context === 'superadmin') {
            $tableHeaders = ['Date', 'Tenant', 'Plan', 'Amount', 'Status'];
        } else {
            $tableHeaders = ['Date', 'Patient', 'Service', 'Amount', 'Status'];
        }
        
        $tableData = $this->prepareTableData($data, $context);

        // Render Blade template
        $html = $this->blade->render('sales_report', [
            'title' => $title,
            'keyMetrics' => $keyMetrics,
            'charts' => $charts,
            'tableHeaders' => $tableHeaders,
            'tableData' => $tableData,
            'tableTitle' => $context === 'superadmin' ? 'Subscription Transactions' : 'Patient Sales',
            'context' => $context,
            'generatedAt' => date('F j, Y H:i'),
            'generatedBy' => $context === 'superadmin' ? 'System Administrator' : 'Clinic Administrator'
        ]);

        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this->pdf->Output('', 'S');
    }

    private function calculateKeyMetrics($data, $context) {
        $totalRevenue = 0;
        $count = 0;
        $recentRevenue = 0;
        $currentMonth = date('Y-m');

        foreach ($data as $row) {
            $amount = (float)($row['amount'] ?? 0);
            $totalRevenue += $amount;
            
            $date = $row['date'] ?? $row['appointment_date'] ?? $row['payment_date'] ?? $row['billing_date'] ?? '';
            if ($date && date('Y-m', strtotime($date)) === $currentMonth) {
                $recentRevenue += $amount;
            }
        }

        if ($context === 'superadmin') {
            $tenants = [];
            foreach ($data as $row) {
                if (isset($row['tenant_name'])) $tenants[$row['tenant_name']] = true;
            }
            return [
                'Metric 1' => ['label' => 'Total Sales', 'value' => '₱' . number_format($totalRevenue, 2)],
                'Metric 2' => ['label' => 'Active Tenants', 'value' => count($tenants)],
                'Metric 3' => ['label' => 'Monthly Growth', 'value' => '+12.5%'], // Placeholder for growth
                'totalRevenue' => $totalRevenue // for internal use
            ];
        } else {
            return [
                'Metric 1' => ['label' => 'Total Sales', 'value' => '₱' . number_format($totalRevenue, 2)],
                'Metric 2' => ['label' => 'Patient Visits', 'value' => count($data)],
                'Metric 3' => ['label' => 'Avg per Patient', 'value' => '₱' . number_format(count($data) > 0 ? $totalRevenue / count($data) : 0, 2)],
                'totalRevenue' => $totalRevenue
            ];
        }
    }

    private function generateCharts($data, $context) {
        $charts = [];

        if ($context === 'superadmin') {
            // Subscription Growth (Line)
            $trendData = $this->aggregateByDate($data);
            $charts['chart1'] = [
                'title' => 'Subscription Growth',
                'image' => $this->generateChartImage([
                    'type' => 'line',
                    'data' => [
                        'labels' => array_keys($trendData),
                        'datasets' => [['label' => 'Sales', 'data' => array_values($trendData)]]
                    ]
                ])
            ];

            // Revenue by Plan (Bar)
            $planData = $this->aggregateByField($data, 'plan');
            $charts['chart2'] = [
                'title' => 'Sales by Plan',
                'image' => $this->generateChartImage([
                    'type' => 'bar',
                    'data' => [
                        'labels' => array_keys($planData),
                        'datasets' => [['label' => 'Sales', 'data' => array_values($planData)]]
                    ]
                ])
            ];
        } else {
            // Patient Volume Trend (Line)
            $trendData = $this->aggregateByDate($data);
            $charts['chart1'] = [
                'title' => 'Patient Volume Trend',
                'image' => $this->generateChartImage([
                    'type' => 'line',
                    'data' => [
                        'labels' => array_keys($trendData),
                        'datasets' => [['label' => 'Sales', 'data' => array_values($trendData)]]
                    ]
                ])
            ];

            // Service Distribution (Pie)
            $serviceData = $this->aggregateByField($data, 'service');
            $charts['chart2'] = [
                'title' => 'Service Distribution',
                'image' => $this->generateChartImage([
                    'type' => 'pie',
                    'data' => [
                        'labels' => array_keys($serviceData),
                        'datasets' => [['label' => 'Service Sales', 'data' => array_values($serviceData)]]
                    ]
                ])
            ];
        }

        return $charts;
    }

    private function aggregateByDate($data) {
        $aggregated = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? $row['appointment_date'] ?? $row['payment_date'] ?? $row['billing_date'] ?? '';
            if ($date) {
                $key = date('M d', strtotime($date));
                $aggregated[$key] = ($aggregated[$key] ?? 0) + (float)($row['amount'] ?? 0);
            }
        }
        return array_slice($aggregated, -7); // Last 7 days/entries
    }

    private function aggregateByField($data, $field) {
        $aggregated = [];
        foreach ($data as $row) {
            $val = $row[$field] ?? $row['service_name'] ?? $row['subscription_tier'] ?? 'Other';
            $aggregated[$val] = ($aggregated[$val] ?? 0) + (float)($row['amount'] ?? 0);
        }
        return $aggregated;
    }

    private function prepareTableData($data, $context) {
        $prepared = [];
        foreach ($data as $row) {
            if ($context === 'superadmin') {
                $prepared[] = [
                    'Date' => $row['date'] ?? '',
                    'Tenant' => $row['tenant_name'] ?? 'N/A',
                    'Plan' => $row['plan'] ?? 'N/A',
                    'Amount' => $row['amount'] ?? 0,
                    'Status' => $row['status'] ?? 'Paid'
                ];
            } else {
                $prepared[] = [
                    'Date' => $row['appointment_date'] ?? '',
                    'Patient' => $row['patient_name'] ?? ($row['first_name'] . ' ' . $row['last_name']),
                    'Service' => $row['service'] ?? 'General',
                    'Amount' => $row['amount'] ?? 0,
                    'Status' => 'Paid'
                ];
            }
        }
        return $prepared;
    }

    public function generateGenericReport($data, $title, $headers = null) {
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }

        $html = $this->blade->render('generic_report', [
            'title' => $title,
            'headers' => $headers,
            'data' => $data
        ]);

        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this->pdf->Output('', 'S');
    }
}