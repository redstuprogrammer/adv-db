<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;

    public function __construct() {
        if (!defined('PDF_FONT_NAME_MAIN'))  define('PDF_FONT_NAME_MAIN',  'dejavusans');
        if (!defined('PDF_FONT_SIZE_MAIN'))  define('PDF_FONT_SIZE_MAIN',  10);
        if (!defined('PDF_FONT_NAME_DATA'))  define('PDF_FONT_NAME_DATA',  'dejavusans');
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

    private function setupPDF(): void {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('OralSync');
        $this->pdf->SetTitle('OralSync Report');

        // Disable built-in header/footer — rendered manually below.
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->pdf->SetDefaultMonospacedFont('courier');
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 22);

        if (defined('PDF_IMAGE_SCALE_RATIO')) {
            $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        }

        // DejaVu Sans supports full Unicode including ₱
        $this->pdf->SetFont('dejavusans', '', 10);
    }

    // =========================================================================
    // SVG chart builders
    // Return raw SVG strings — rendered via ImageSVG(), NOT writeHTML().
    // TCPDF does NOT render <svg> tags inside writeHTML; ImageSVG() is correct.
    // =========================================================================

    private function createLineChartSVG(array $data, array $labels, int $w = 500, int $h = 170): string {
        $max   = max(!empty($data) ? $data : [1]);
        $max   = $max ?: 1;
        $max   = ceil($max / 100) * 100;
        $cw    = $w - 80;
        $ch    = $h - 48;
        $xOff  = 68;
        $yOff  = 14;
        $n     = count($data);

        $svg  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
        $svg .= "<defs><linearGradient id='lg' x1='0' y1='0' x2='0' y2='1'>"
              . "<stop offset='0%' stop-color='#0d3b66' stop-opacity='0.18'/>"
              . "<stop offset='100%' stop-color='#0d3b66' stop-opacity='0.01'/>"
              . "</linearGradient></defs>";

        // Grid + Y labels
        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOff + $ch - ($i / 4) * $ch;
            $val = number_format(($max / 4) * $i, 0);
            $svg .= "<line x1='{$xOff}' y1='{$y}' x2='" . ($xOff + $cw) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $svg .= "<text x='" . ($xOff - 5) . "' y='" . ($y + 4) . "' text-anchor='end' font-size='9' fill='#94a3b8' font-family='Arial'>{$val}</text>";
        }
        // Axes
        $svg .= "<line x1='{$xOff}' y1='{$yOff}' x2='{$xOff}' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";
        $svg .= "<line x1='{$xOff}' y1='" . ($yOff + $ch) . "' x2='" . ($xOff + $cw) . "' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";

        if ($n > 0) {
            $pts = [];
            foreach ($data as $i => $v) {
                $pts[] = [
                    $xOff + ($n > 1 ? ($i / ($n - 1)) * $cw : $cw / 2),
                    $yOff + $ch - ($v / $max) * $ch
                ];
            }
            // Area
            $area = "M{$pts[0][0]}," . ($yOff + $ch);
            foreach ($pts as [$px, $py]) $area .= " L{$px},{$py}";
            $area .= " L{$pts[$n-1][0]}," . ($yOff + $ch) . " Z";
            $svg .= "<path d='{$area}' fill='url(#lg)'/>";
            // Line
            $path = "M{$pts[0][0]},{$pts[0][1]}";
            for ($i = 1; $i < $n; $i++) $path .= " L{$pts[$i][0]},{$pts[$i][1]}";
            $svg .= "<path d='{$path}' fill='none' stroke='#0d3b66' stroke-width='2.5' stroke-linejoin='round' stroke-linecap='round'/>";
            // Points + X labels
            foreach ($pts as $i => [$px, $py]) {
                $svg .= "<circle cx='{$px}' cy='{$py}' r='3.5' fill='white' stroke='#0d3b66' stroke-width='2'/>";
                if ($n <= 8 || $i % 2 === 0) {
                    $lbl = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
                    $svg .= "<text x='{$px}' y='" . ($yOff + $ch + 15) . "' text-anchor='middle' font-size='8' fill='#64748b' font-family='Arial'>{$lbl}</text>";
                }
            }
        }

        $svg .= "</svg>";
        return $svg;
    }

    private function createBarChartSVG(array $data, array $labels, int $w = 500, int $h = 170): string {
        $max      = max(!empty($data) ? $data : [1]);
        $max      = $max ?: 1;
        $max      = ceil($max / 100) * 100;
        $cw       = $w - 80;
        $ch       = $h - 48;
        $xOff     = 68;
        $yOff     = 14;
        $n        = count($data);
        $spacing  = $n > 0 ? $cw / $n : $cw;
        $barW     = max(8, $spacing * 0.55);
        $colors   = ['#0d3b66', '#1e5f74', '#2d8a6b', '#64748b', '#94a3b8'];

        $svg  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOff + $ch - ($i / 4) * $ch;
            $val = number_format(($max / 4) * $i, 0);
            $svg .= "<line x1='{$xOff}' y1='{$y}' x2='" . ($xOff + $cw) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $svg .= "<text x='" . ($xOff - 5) . "' y='" . ($y + 4) . "' text-anchor='end' font-size='9' fill='#94a3b8' font-family='Arial'>{$val}</text>";
        }
        $svg .= "<line x1='{$xOff}' y1='{$yOff}' x2='{$xOff}' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";
        $svg .= "<line x1='{$xOff}' y1='" . ($yOff + $ch) . "' x2='" . ($xOff + $cw) . "' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";

        foreach ($data as $i => $v) {
            $bh    = ($v / $max) * $ch;
            $bx    = $xOff + ($i * $spacing) + ($spacing - $barW) / 2;
            $by    = $yOff + $ch - $bh;
            $color = $colors[$i % count($colors)];
            $svg  .= "<rect x='{$bx}' y='{$by}' width='{$barW}' height='{$bh}' fill='{$color}' rx='3'/>";
            $lbl   = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
            $svg  .= "<text x='" . ($bx + $barW / 2) . "' y='" . ($yOff + $ch + 15) . "' text-anchor='middle' font-size='8' fill='#64748b' font-family='Arial'>{$lbl}</text>";
        }

        $svg .= "</svg>";
        return $svg;
    }

    private function createPieChartSVG(array $data, array $labels, int $w = 500, int $h = 170): string {
        $colors = ['#0d3b66', '#1e5f74', '#2d8a6b', '#64748b', '#94a3b8', '#475569'];
        $total  = array_sum($data) ?: 1;
        $cx     = 85;
        $cy     = $h / 2;
        $r      = 65;
        $angle  = -90;

        $svg  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";

        foreach ($data as $i => $v) {
            $sweep = ($v / $total) * 360;
            if ($sweep >= 360) $sweep = 359.99;
            $x1   = $cx + $r * cos(deg2rad($angle));
            $y1   = $cy + $r * sin(deg2rad($angle));
            $angle += $sweep;
            $x2   = $cx + $r * cos(deg2rad($angle));
            $y2   = $cy + $r * sin(deg2rad($angle));
            $lg   = $sweep > 180 ? 1 : 0;
            $col  = $colors[$i % count($colors)];
            $svg .= "<path d='M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$lg},1 {$x2},{$y2} Z' fill='{$col}' stroke='white' stroke-width='1.5'/>";
        }

        // Legend
        foreach ($data as $i => $v) {
            $col  = $colors[$i % count($colors)];
            $lbl  = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
            $pct  = number_format(($v / $total) * 100, 1);
            $ly   = 28 + $i * 26;
            if ($ly + 14 > $h) break;
            $svg .= "<rect x='175' y='" . ($ly - 10) . "' width='12' height='12' fill='{$col}' rx='2'/>";
            $svg .= "<text x='193' y='{$ly}' font-size='10' fill='#334155' font-family='Arial'>{$lbl}</text>";
            $svg .= "<text x='193' y='" . ($ly + 13) . "' font-size='9' fill='#94a3b8' font-family='Arial'>{$pct}%</text>";
        }

        $svg .= "</svg>";
        return $svg;
    }

    // =========================================================================
    // Public report generators
    // =========================================================================

    public function generateSalesReport(array $data, string $title = 'Sales Report', string $context = 'superadmin'): string {
        $keyMetrics   = $this->calculateKeyMetrics($data, $context);
        $chartSVGs    = $this->generateChartSVGs($data, $context);
        $tableHeaders = ($context === 'superadmin')
            ? ['Date', 'Tenant / Clinic', 'Plan', 'Amount (PHP)', 'Status']
            : ['Date', 'Patient', 'Amount (PHP)', 'Type'];
        $tableData    = $this->prepareTableData($data, $context);
        $tableTitle   = $context === 'superadmin' ? 'Subscription Transactions' : 'Clinic Transactions';

        $this->pdf->AddPage();
        $this->renderPageHeader($title, $context);
        $this->renderKeyMetrics($keyMetrics);
        $this->renderCharts($chartSVGs);
        $this->renderTable($tableHeaders, $tableData, $tableTitle);
        $this->renderPageFooter();

        return $this->pdf->Output('', 'S');
    }

    public function generateGenericReport(array $data, string $title, ?array $headers = null): string {
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }
        $this->pdf->AddPage();
        $this->renderPageHeader($title, 'generic');
        $this->renderTable($headers ?? [], $data, 'Records');
        $this->renderPageFooter();
        return $this->pdf->Output('', 'S');
    }

    // =========================================================================
    // Direct rendering helpers — write to $this->pdf using native TCPDF calls
    // =========================================================================

    private function renderPageHeader(string $title, string $context): void {
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30; // usable width

        // Dark header band
        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect(15, 15, $pw, 28, 'F');

        // Brand name (left)
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 21);
        $pdf->Cell($pw * 0.55, 8, 'OralSync', 0, 0, 'L');

        // Generated date (right)
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetXY(20, 21);
        $pdf->Cell($pw, 8, 'Generated: ' . date('F j, Y  H:i'), 0, 0, 'R');

        // Report title (left, second line)
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetXY(20, 30);
        $pdf->Cell($pw * 0.65, 7, $title, 0, 0, 'L');

        // Context badge (right, second line)
        if ($context && $context !== 'generic') {
            $ctxLabel = $context === 'superadmin' ? 'System-Wide Report' : 'Clinic Report';
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetXY(20, 30);
            $pdf->Cell($pw, 7, $ctxLabel, 0, 0, 'R');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->Ln(32);
    }

    private function renderKeyMetrics(array $metrics): void {
        if (empty($metrics)) return;
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30;
        $n    = count($metrics);
        $gap  = 5;
        $colW = ($pw - $gap * ($n - 1)) / $n;
        $y    = $pdf->GetY();

        $this->renderSectionLabel('Performance Overview');
        $y = $pdf->GetY();

        foreach ($metrics as $i => $m) {
            $x = 15 + $i * ($colW + $gap);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, 26, 3, '1111', 'DF');

            // Value (₱ renders because dejavusans is set)
            $pdf->SetFont('dejavusans', 'B', 13);
            $pdf->SetTextColor(13, 59, 102);
            $pdf->SetXY($x, $y + 5);
            $pdf->Cell($colW, 8, (string)($m['value'] ?? ''), 0, 0, 'C');

            // Label
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($x, $y + 14);
            $pdf->Cell($colW, 6, strtoupper((string)($m['label'] ?? '')), 0, 0, 'C');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 32);
    }

    private function renderCharts(array $chartSVGs): void {
        if (empty($chartSVGs)) return;
        $pdf    = $this->pdf;
        $pw     = $pdf->getPageWidth() - 30;
        $colW   = ($pw - 8) / 2;
        $cardH  = 62; // mm total card height
        $svgH   = 50; // mm for the SVG inside the card

        $this->renderSectionLabel('Sales Analytics');
        $y = $pdf->GetY();

        foreach ($chartSVGs as $i => $chart) {
            $x = 15 + $i * ($colW + 8);

            // Card
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, $cardH, 3, '1111', 'DF');

            // Chart title
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->SetXY($x + 3, $y + 3);
            $pdf->Cell($colW - 6, 5, $chart['title'], 0, 0, 'C');

            // Render SVG via ImageSVG — the only correct TCPDF API for vector SVGs
            if (!empty($chart['svg'])) {
                try {
                    $pdf->ImageSVG(
                        '@' . $chart['svg'], // '@' prefix = SVG from string buffer
                        $x + 3,
                        $y + 9,
                        $colW - 6,
                        $svgH,
                        '', '', 0, false
                    );
                } catch (\Exception $e) {
                    $pdf->SetFont('dejavusans', 'I', 7);
                    $pdf->SetTextColor(148, 163, 184);
                    $pdf->SetXY($x + 3, $y + 28);
                    $pdf->Cell($colW - 6, 5, '[Chart unavailable]', 0, 0, 'C');
                }
            }
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + $cardH + 6);
    }

    private function renderTable(array $headers, array $rows, string $title): void {
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30;
        $n    = count($headers);
        if ($n === 0) return;

        $this->renderSectionLabel($title);

        // Column widths
        $colW = array_fill(0, $n, $pw / $n);
        
        if ($n === 5) {
            $colW = [38, 45, 40, 35, 22];
            $total = array_sum($colW);
            $colW  = array_map(fn($c) => $c / $total * $pw, $colW);
        } elseif ($n === 4) {
            $colW = [40, 70, 45, 35];
            $total = array_sum($colW);
            $colW  = array_map(fn($c) => $c / $total * $pw, $colW);
        }

        // Header row
        $pdf->SetFillColor(13, 59, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetLineWidth(0.1);

        $x = 15;
        $y = $pdf->GetY();
        foreach ($headers as $ci => $h) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($colW[$ci], 8, strtoupper((string)$h), 0, 0, 'C', true);
            $x += $colW[$ci];
        }
        $pdf->Ln(8);

        if (empty($rows)) {
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->Cell($pw, 12, 'No transaction data available for this period.', 0, 1, 'C', true);
            $pdf->SetTextColor(30, 41, 59);
            return;
        }

        // Data rows
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.2);

        $even = false;
        foreach ($rows as $row) {
            // Auto page-break
            if ($pdf->GetY() + 8 > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $this->renderPageHeader('(continued)', '');
            }

            $even = !$even;
            $pdf->SetFillColor($even ? 248 : 255, $even ? 250 : 255, $even ? 252 : 255);

            $cells = array_values((array)$row);
            $x     = 15;
            $y     = $pdf->GetY();

            foreach ($cells as $ci => $cell) {
                $cellStr = (string)$cell;

                // Colour status/type column
                if ($ci === count($cells) - 1) {
                    $lower = strtolower($cellStr);
                    if (in_array($lower, ['paid', 'full payment'])) {
                        $pdf->SetTextColor(21, 128, 61);
                    } elseif (in_array($lower, ['partial', 'partial payment', 'downpayment'])) {
                        $pdf->SetTextColor(161, 98, 7);
                    } else {
                        $pdf->SetTextColor(30, 41, 59);
                    }
                } else {
                    $pdf->SetTextColor(30, 41, 59);
                }

                // Amount column: right-align
                $align = ($ci === $n - 2) ? 'R' : 'L';
                $pdf->SetXY($x, $y);
                $pdf->Cell($colW[$ci], 8, $cellStr, 'B', 0, $align, true);
                $x += $colW[$ci];
            }
            $pdf->Ln(8);
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
    }

    private function renderPageFooter(): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $pdf->SetY(-18);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $pdf->GetY(), 15 + $pw, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell($pw * 0.55, 5, 'OralSync Management System  |  Amounts in Philippine Peso (PHP)', 0, 0, 'L');
        $pdf->Cell($pw * 0.45, 5, 'Confidential  |  Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'R');
        $pdf->SetTextColor(30, 41, 59);
    }

    private function renderSectionLabel(string $label): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $y   = $pdf->GetY() + 3;

        // Accent bar
        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect(15, $y, 3, 6, 'F');

        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetTextColor(13, 59, 102);
        $pdf->SetXY(21, $y);
        $pdf->Cell($pw - 6, 6, strtoupper($label), 0, 0, 'L');
        $pdf->Ln(10);
        $pdf->SetTextColor(30, 41, 59);
    }

    // =========================================================================
    // Data helpers
    // =========================================================================

    private function generateChartSVGs(array $data, string $context): array {
        $trendData = $this->aggregateByDate($data);
        $chart1    = [
            'title' => 'Daily Sales Performance',
            'svg'   => $this->createLineChartSVG(array_values($trendData), array_keys($trendData)),
        ];

        if ($context === 'superadmin') {
            $planData = $this->aggregateByField($data, 'plan');
            $chart2   = [
                'title' => 'Revenue by Subscription Plan',
                'svg'   => $this->createBarChartSVG(array_values($planData), array_keys($planData)),
            ];
            return [$chart1, $chart2];
        }

        return [$chart1];
    }

    private function aggregateByDate(array $data): array {
        $agg = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) {
                $key      = date('M d', strtotime($date));
                $agg[$key] = ($agg[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
            }
        }
        return array_slice($agg, -12, null, true);
    }

    private function aggregateByField(array $data, string $field): array {
        $agg = [];
        foreach ($data as $row) {
            $val = $row[$field] ?? $row['service_name'] ?? $row['subscription_tier'] ?? 'Other';
            if (empty(trim((string)$val))) $val = 'Other';
            $agg[$val] = ($agg[$val] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
        }
        return $agg;
    }

    private function calculateKeyMetrics(array $data, string $context): array {
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
                ['label' => 'Total Sales',    'value' => '₱' . number_format($totalRevenue, 2)],
                ['label' => 'Active Tenants', 'value' => count($tenants)],
                ['label' => 'Transactions',   'value' => count($data)],
            ];
        }

        $count = count($data);
        return [
            ['label' => 'Total Sales',     'value' => '₱' . number_format($totalRevenue, 2)],
            ['label' => 'Patient Visits',  'value' => $count],
        ];
    }

    private function prepareTableData(array $data, string $context): array {
        $prepared = [];
        foreach ($data as $row) {
            $rawDate = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            $date    = $rawDate ? date('M d, Y', strtotime($rawDate)) : 'N/A';

            if ($context === 'superadmin') {
                $prepared[] = [
                    $date,
                    $row['tenant_name'] ?? 'N/A',
                    $row['plan'] ?? $row['subscription_tier'] ?? 'N/A',
                    '₱' . number_format((float)($row['amount'] ?? 0), 2),
                    ucfirst($row['status'] ?? 'paid'),
                ];
            } else {
                $patient = $row['patient_name']
                    ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if (!$patient) $patient = 'N/A';

                $type    = 'Full Payment';
                $pType   = strtolower((string)($row['payment_type']   ?? ''));
                $pStatus = strtolower((string)($row['status'] ?? $row['payment_status'] ?? ''));
                $pSource = strtolower((string)($row['source']         ?? ''));
                
                if ($pType === 'deposit' || $pType === 'downpayment' || ($pSource === 'mobile' && $pStatus === 'paid')) {
                    $type = 'Downpayment';
                } elseif ($pStatus === 'partial') {
                    $type = 'Partial Payment';
                }

                $prepared[] = [
                    $date,
                    $patient,
                    '₱' . number_format((float)($row['amount'] ?? $row['amount_paid'] ?? 0), 2),
                    $type,
                ];
            }
        }
        return $prepared;
    }
}
