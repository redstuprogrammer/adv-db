<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use HeadlessChromium\BrowserFactory;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;
    private $tempFiles = [];

    public function __construct() {
        if (!defined('PDF_FONT_NAME_MAIN')) define('PDF_FONT_NAME_MAIN', 'helvetica');
        if (!defined('PDF_FONT_SIZE_MAIN')) define('PDF_FONT_SIZE_MAIN', 10);
        if (!defined('PDF_FONT_NAME_DATA')) define('PDF_FONT_NAME_DATA', 'helvetica');
        if (!defined('PDF_FONT_SIZE_DATA')) define('PDF_FONT_SIZE_DATA', 8);
        if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');

        $container = new \Jenssegers\Blade\Container;
        Container::setInstance($container);

        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache', $container);
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->setupPDF();
    }

    public function __destruct() {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }

    private function setupPDF() {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('Super Admin');
        $this->pdf->SetTitle('OralSync Report');
        $this->pdf->setHeaderFont(Array('dejavusans', '', PDF_FONT_SIZE_MAIN));
        $this->pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetHeaderMargin(0);
        $this->pdf->SetFooterMargin(0);
        $this->pdf->SetAutoPageBreak(TRUE, 0);
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    }

    private function saveTempSVG($svg) {
        $tmp = tempnam(sys_get_temp_dir(), 'os_chart');
        $filename = $tmp . '.svg';
        rename($tmp, $filename);
        file_put_contents($filename, $svg);
        $this->tempFiles[] = $filename;
        return $filename;
    }

    private function createLineChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = !empty($data) ? max($data) : 1;
        $maxValue = $maxValue ?: 1;
        $maxValue = ceil($maxValue / 100) * 100;

        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white;'>";
        $svg .= "<defs><linearGradient id='areaGradient' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='#0d3b66' stop-opacity='0.2'/><stop offset='100%' stop-color='#0d3b66' stop-opacity='0'/></linearGradient></defs>";
        for ($i = 0; $i <= 4; $i++) {
            $y = $yOffset + $chartHeight - ($i / 4) * $chartHeight;
            $svg .= "<line x1='{$xOffset}' y1='{$y}' x2='" . ($xOffset + $chartWidth) . "' y2='{$y}' stroke='#f1f5f9' stroke-width='1'/>";
            $val = ($maxValue / 4) * $i;
            $svg .= "<text x='50' y='" . ($y + 4) . "' text-anchor='end' font-family='Helvetica' font-size='10' fill='#64748b'>₱" . number_format($val) . "</text>";
        }
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
        foreach ($data as $i => $value) {
            $x = $xOffset + ($i / max(1, count($data) - 1)) * $chartWidth;
            $y = $yOffset + $chartHeight - ($value / $maxValue) * $chartHeight;
            $svg .= "<circle cx='{$x}' cy='{$y}' r='3' fill='white' stroke='#0d3b66' stroke-width='2'/>";
        }
        foreach ($labels as $i => $label) {
            if (count($labels) > 7 && $i % 2 !== 0) continue;
            $x = $xOffset + ($i / max(1, count($labels) - 1)) * $chartWidth;
            $svg .= "<text x='{$x}' y='" . ($height - 40) . "' text-anchor='middle' font-family='Helvetica' font-size='10' fill='#64748b'>{$label}</text>";
        }
        $svg .= "</svg>";
        return $svg;
    }

    private function createBarChartSVG($config, $width, $height) {
        $data = isset($config['data']['datasets'][0]['data']) ? $config['data']['datasets'][0]['data'] : [];
        $labels = isset($config['data']['labels']) ? $config['data']['labels'] : [];
        $maxValue = !empty($data) ? max($data) : 1;
        $maxValue = $maxValue ?: 1;
        $maxValue = ceil($maxValue / 100) * 100;
        $chartWidth = $width - 80;
        $chartHeight = $height - 80;
        $xOffset = 60;
        $yOffset = 20;
        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg' style='background: white;'>";
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
        return $svg;
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
            $svg .= "<rect x='" . ($width - 100) . "' y='" . (40 + $i * 20) . "' width='12' height='12' fill='{$color}' rx='2'/><text x='" . ($width - 80) . "' y='" . (50 + $i * 20) . "' font-family='Helvetica' font-size='10' fill='#64748b'>" . ($labels[$i] ?? '') . "</text>";
        }
        $svg .= "</svg>";
        return $svg;
    }

    public function generateSalesReport($data, $title = 'Sales Report', $context = 'superadmin') {
        $keyMetrics = $this->calculateKeyMetrics($data, $context);
        $charts = $this->generateCharts($data, $context);
        $tableHeaders = ($context === 'superadmin') ? ['Date', 'Tenant', 'Plan', 'Amount', 'Status'] : ['Date', 'Patient', 'Service', 'Amount', 'Status'];
        $tableData = $this->prepareTableData($data, $context);

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
        $currentMonth = date('Y-m');
        foreach ($data as $row) {
            $amount = (float)($row['amount'] ?? 0);
            $totalRevenue += $amount;
        }
        if ($context === 'superadmin') {
            $tenants = [];
            foreach ($data as $row) { if (isset($row['tenant_name'])) $tenants[$row['tenant_name']] = true; }
            return [
                'Metric 1' => ['label' => 'Total Sales', 'value' => '₱' . number_format($totalRevenue, 2)],
                'Metric 2' => ['label' => 'Active Tenants', 'value' => count($tenants)],
                'Metric 3' => ['label' => 'Monthly Growth', 'value' => '+12.5%']
            ];
        } else {
            return [
                'Metric 1' => ['label' => 'Total Sales', 'value' => '₱' . number_format($totalRevenue, 2)],
                'Metric 2' => ['label' => 'Patient Visits', 'value' => count($data)],
                'Metric 3' => ['label' => 'Avg per Patient', 'value' => '₱' . number_format(count($data) > 0 ? $totalRevenue / count($data) : 0, 2)]
            ];
        }
    }

    private function generateCharts($data, $context) {
        $charts = [];
        if ($context === 'superadmin') {
            $trendData = $this->aggregateByDate($data);
            $charts['chart1'] = ['title' => 'Subscription Growth', 'path' => $this->saveTempSVG($this->createLineChartSVG(['type' => 'line', 'data' => ['labels' => array_keys($trendData), 'datasets' => [['data' => array_values($trendData)]]]], 400, 300))];
            $planData = $this->aggregateByField($data, 'plan');
            $charts['chart2'] = ['title' => 'Sales by Plan', 'path' => $this->saveTempSVG($this->createBarChartSVG(['type' => 'bar', 'data' => ['labels' => array_keys($planData), 'datasets' => [['data' => array_values($planData)]]]], 400, 300))];
        } else {
            $trendData = $this->aggregateByDate($data);
            $charts['chart1'] = ['title' => 'Patient Volume Trend', 'path' => $this->saveTempSVG($this->createLineChartSVG(['type' => 'line', 'data' => ['labels' => array_keys($trendData), 'datasets' => [['data' => array_values($trendData)]]]], 400, 300))];
            $serviceData = $this->aggregateByField($data, 'service');
            $charts['chart2'] = ['title' => 'Service Distribution', 'path' => $this->saveTempSVG($this->createPieChartSVG(['type' => 'pie', 'data' => ['labels' => array_keys($serviceData), 'datasets' => [['data' => array_values($serviceData)]]]], 400, 300))];
        }
        return $charts;
    }

    private function aggregateByDate($data) {
        $aggregated = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? $row['appointment_date'] ?? $row['payment_date'] ?? $row['billing_date'] ?? '';
            if ($date) { $key = date('M d', strtotime($date)); $aggregated[$key] = ($aggregated[$key] ?? 0) + (float)($row['amount'] ?? 0); }
        }
        return array_slice($aggregated, -7);
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
                $prepared[] = ['Date' => $row['date'] ?? '', 'Tenant' => $row['tenant_name'] ?? 'N/A', 'Plan' => $row['plan'] ?? 'N/A', 'Amount' => $row['amount'] ?? 0, 'Status' => $row['status'] ?? 'Paid'];
            } else {
                $prepared[] = ['Date' => $row['appointment_date'] ?? '', 'Patient' => $row['patient_name'] ?? ($row['first_name'] . ' ' . $row['last_name']), 'Service' => $row['service'] ?? 'General', 'Amount' => $row['amount'] ?? 0, 'Status' => 'Paid'];
            }
        }
        return $prepared;
    }

    public function generateGenericReport($data, $title, $headers = null) {
        if (!$headers && !empty($data)) $headers = array_keys($data[0]);
        $html = $this->blade->render('generic_report', ['title' => $title, 'headers' => $headers, 'data' => $data]);
        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');
        return $this->pdf->Output('', 'S');
    }
}