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

        $this->pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
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
        } else {
            return $this->createPlaceholderChart($chartConfig, $width, $height);
        }
    }

    private function createLineChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = max($data) ?: 1;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white; border-radius: 8px;'>";

        // Grid lines
        $svg .= "<defs><pattern id='grid' width='40' height='20' patternUnits='userSpaceOnUse'><path d='M 40 0 L 0 0 0 20' fill='none' stroke='#f1f5f9' stroke-width='1'/></pattern></defs>";
        $svg .= "<rect width='100%' height='100%' fill='url(#grid)' />";

        // Chart area
        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;

        // Draw line
        $points = '';
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / (count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $points .= ($i > 0 ? ' L ' : 'M ') . $x . ' ' . $y;
        }

        $svg .= "<path d='{$points}' fill='none' stroke='#0d3b66' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/>";

        // Data points
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / (count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $svg .= "<circle cx='{$x}' cy='{$y}' r='4' fill='#0d3b66' stroke='white' stroke-width='2'/>";
        }

        // Labels
        foreach ($labels as $i => $label) {
            $x = $xOffset + ($i / (count($labels) - 1)) * $chartWidth;
            $svg .= "<text x='{$x}' y='" . ($height - 10) . "' text-anchor='middle' font-family='Arial' font-size='10' fill='#64748b'>{$label}</text>";
        }

        // Y-axis labels
        for ($i = 0; $i <= 4; $i++) {
            $value = ($maxValue / 4) * $i;
            $y = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<text x='30' y='" . ($y + 4) . "' text-anchor='end' font-family='Arial' font-size='10' fill='#64748b'>₱" . number_format($value) . "</text>";
        }

        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function createBarChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = max($data) ?: 1;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white; border-radius: 8px;'>";

        // Grid lines
        $svg .= "<defs><pattern id='grid' width='40' height='20' patternUnits='userSpaceOnUse'><path d='M 40 0 L 0 0 0 20' fill='none' stroke='#f1f5f9' stroke-width='1'/></pattern></defs>";
        $svg .= "<rect width='100%' height='100%' fill='url(#grid)' />";

        // Chart area
        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;
        $barWidth = $chartWidth / count($data) * 0.8;
        $barSpacing = $chartWidth / count($data);

        // Draw bars
        foreach ($data as $i => $value) {
            $barHeight = ($value / $maxValue) * $chartHeight;
            $x = $xOffset + $i * $barSpacing + ($barSpacing - $barWidth) / 2;
            $y = $yOffset + $chartHeight - $barHeight;

            $svg .= "<rect x='{$x}' y='{$y}' width='{$barWidth}' height='{$barHeight}' fill='#1e5f74' stroke='#0d3b66' stroke-width='1' rx='2'/>";
        }

        // Labels
        foreach ($labels as $i => $label) {
            $x = $xOffset + $i * $barSpacing + $barSpacing / 2;
            $svg .= "<text x='{$x}' y='" . ($height - 10) . "' text-anchor='middle' font-family='Arial' font-size='10' fill='#64748b' transform='rotate(-45 {$x} " . ($height - 10) . ")'>{$label}</text>";
        }

        // Y-axis labels
        for ($i = 0; $i <= 4; $i++) {
            $value = ($maxValue / 4) * $i;
            $y = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<text x='30' y='" . ($y + 4) . "' text-anchor='end' font-family='Arial' font-size='10' fill='#64748b'>₱" . number_format($value) . "</text>";
        }

        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function createPlaceholderChart($config, $width, $height) {
        // Create a simple SVG placeholder
        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>
            <rect width='100%' height='100%' fill='#f8fafc'/>
            <text x='50%' y='50%' text-anchor='middle' dy='.3em' fill='#64748b' font-family='Arial' font-size='16'>
                Chart: " . (isset($config['type']) ? $config['type'] : 'Unknown') . "
            </text>
        </svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function generateSalesReport($data, $title = 'Sales Report') {
        // Calculate key metrics
        $keyMetrics = $this->calculateKeyMetrics($data);

        // Generate charts
        $charts = $this->generateCharts($data);

        // Prepare table data
        $tableHeaders = ['Date', 'Clinic', 'Service', 'Amount', 'Status'];
        $tableData = $this->prepareTableData($data);

        // Render Blade template
        $html = $this->blade->render('sales_report', [
            'title' => $title,
            'keyMetrics' => $keyMetrics,
            'charts' => $charts,
            'tableHeaders' => $tableHeaders,
            'tableData' => $tableData,
            'tableTitle' => 'Revenue Transactions'
        ]);

        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this->pdf->Output('', 'S'); // Return as string
    }

    private function calculateKeyMetrics($data) {
        $totalRevenue = 0;
        $activeSubscriptions = 0;
        $monthlyRevenue = 0;

        $currentMonth = date('Y-m');

        foreach ($data as $row) {
            if (isset($row['amount'])) {
                $totalRevenue += (float)$row['amount'];

                if (isset($row['appointment_date']) && date('Y-m', strtotime($row['appointment_date'])) === $currentMonth) {
                    $monthlyRevenue += (float)$row['amount'];
                }
            }

            // Count unique clinics as active subscriptions
            if (isset($row['clinic_name'])) {
                $activeSubscriptions = max($activeSubscriptions, 1); // Simplified
            }
        }

        return [
            'totalRevenue' => $totalRevenue,
            'activeSubscriptions' => $activeSubscriptions,
            'monthlyRevenue' => $monthlyRevenue
        ];
    }

    private function generateCharts($data) {
        $charts = [];

        // Revenue trend chart
        $revenueData = $this->aggregateRevenueByMonth($data);
        $charts['revenueTrend'] = $this->generateChartImage([
            'type' => 'line',
            'data' => [
                'labels' => array_keys($revenueData),
                'datasets' => [[
                    'label' => 'Revenue (₱)',
                    'data' => array_values($revenueData),
                    'borderColor' => '#0d3b66',
                    'backgroundColor' => 'rgba(13, 59, 102, 0.1)',
                    'tension' => 0.4
                ]]
            ],
            'options' => [
                'responsive' => false,
                'plugins' => [
                    'legend' => ['display' => true]
                ]
            ]
        ]);

        // Clinic comparison chart
        $clinicData = $this->aggregateRevenueByClinic($data);
        $charts['clinicComparison'] = $this->generateChartImage([
            'type' => 'bar',
            'data' => [
                'labels' => array_keys($clinicData),
                'datasets' => [[
                    'label' => 'Revenue (₱)',
                    'data' => array_values($clinicData),
                    'backgroundColor' => '#1e5f74',
                    'borderColor' => '#0d3b66',
                    'borderWidth' => 1
                ]]
            ],
            'options' => [
                'responsive' => false,
                'plugins' => [
                    'legend' => ['display' => true]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true
                    ]
                ]
            ]
        ]);

        return $charts;
    }

    private function aggregateRevenueByMonth($data) {
        $monthly = [];
        foreach ($data as $row) {
            if (isset($row['appointment_date']) && isset($row['amount'])) {
                $month = date('M Y', strtotime($row['appointment_date']));
                $monthly[$month] = ($monthly[$month] ?? 0) + (float)$row['amount'];
            }
        }
        ksort($monthly);
        return $monthly;
    }

    private function aggregateRevenueByClinic($data) {
        $clinicRevenue = [];
        foreach ($data as $row) {
            $clinic = $row['clinic_name'] ?? 'Unknown Clinic';
            $clinicRevenue[$clinic] = ($clinicRevenue[$clinic] ?? 0) + (float)($row['amount'] ?? 0);
        }
        arsort($clinicRevenue);
        return array_slice($clinicRevenue, 0, 5, true); // Top 5 clinics
    }

    private function prepareTableData($data) {
        $tableData = [];
        foreach ($data as $row) {
            $tableData[] = [
                'Date' => isset($row['appointment_date']) ? date('M d, Y', strtotime($row['appointment_date'])) : '',
                'Clinic' => $row['clinic_name'] ?? 'N/A',
                'Service' => $row['service'] ?? 'General Service',
                'Amount' => $row['amount'] ?? 0,
                'Status' => 'Paid'
            ];
        }
        return $tableData;
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