<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;

    // Clinic info — populated via setClinicInfo() before generateSalesReport()
    private string $clinicName    = '';
    private string $clinicAddress = '';
    private string $clinicPhone   = '';

    public function __construct() {
        if (!defined('PDF_FONT_NAME_MAIN'))  define('PDF_FONT_NAME_MAIN',  'dejavusans');
        if (!defined('PDF_FONT_SIZE_MAIN'))  define('PDF_FONT_SIZE_MAIN',  10);
        if (!defined('PDF_FONT_NAME_DATA'))  define('PDF_FONT_NAME_DATA',  'dejavusans');
        if (!defined('PDF_FONT_SIZE_DATA'))  define('PDF_FONT_SIZE_DATA',  8);
        if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');

        $prev = error_reporting(E_ALL & ~E_DEPRECATED);
        $container = new \Jenssegers\Blade\Container;
        Container::setInstance($container);
        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache', $container);
        error_reporting($prev);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setupPDF();
    }

    public function setClinicInfo(string $name, string $address = '', string $phone = ''): void {
        $this->clinicName    = $name;
        $this->clinicAddress = $address;
        $this->clinicPhone   = $phone;
    }

    private function setupPDF(): void {
        $this->pdf->SetCreator('OralSync');
        $this->pdf->SetAuthor('OralSync');
        $this->pdf->SetTitle('OralSync Report');
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetDefaultMonospacedFont('courier');
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 22);
        if (defined('PDF_IMAGE_SCALE_RATIO')) {
            $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        }
        $this->pdf->SetFont('dejavusans', '', 10);
    }

    // =========================================================================
    // SVG chart builders
    // =========================================================================

    private function createLineChartSVG(array $data, array $labels, int $w = 500, int $h = 170): string {
        $max  = max(!empty($data) ? $data : [1]) ?: 1;
        $max  = ceil($max / 100) * 100;
        $cw   = $w - 80; $ch = $h - 48;
        $xOff = 68; $yOff = 14;
        $n    = count($data);

        $svg  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
        $svg .= "<defs><linearGradient id='lg' x1='0' y1='0' x2='0' y2='1'>"
              . "<stop offset='0%' stop-color='#0d3b66' stop-opacity='0.18'/>"
              . "<stop offset='100%' stop-color='#0d3b66' stop-opacity='0.01'/>"
              . "</linearGradient></defs>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yOff + $ch - ($i / 4) * $ch;
            $val = number_format(($max / 4) * $i, 0);
            $svg .= "<line x1='{$xOff}' y1='{$y}' x2='" . ($xOff + $cw) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $svg .= "<text x='" . ($xOff - 5) . "' y='" . ($y + 4) . "' text-anchor='end' font-size='9' fill='#94a3b8' font-family='Arial'>{$val}</text>";
        }
        $svg .= "<line x1='{$xOff}' y1='{$yOff}' x2='{$xOff}' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";
        $svg .= "<line x1='{$xOff}' y1='" . ($yOff + $ch) . "' x2='" . ($xOff + $cw) . "' y2='" . ($yOff + $ch) . "' stroke='#cbd5e1' stroke-width='1'/>";

        if ($n > 0) {
            $pts = [];
            foreach ($data as $i => $v) {
                $xFrac = ($n > 1) ? ($i / ($n - 1)) : 0.5;
                $pts[] = [$xOff + $xFrac * $cw, $yOff + $ch - ($v / $max) * $ch];
            }
            $area = "M{$pts[0][0]}," . ($yOff + $ch);
            foreach ($pts as [$px, $py]) $area .= " L{$px},{$py}";
            $area .= " L{$pts[$n-1][0]}," . ($yOff + $ch) . " Z";
            $svg .= "<path d='{$area}' fill='url(#lg)'/>";
            $path = "M{$pts[0][0]},{$pts[0][1]}";
            for ($i = 1; $i < $n; $i++) $path .= " L{$pts[$i][0]},{$pts[$i][1]}";
            $svg .= "<path d='{$path}' fill='none' stroke='#0d3b66' stroke-width='2.5' stroke-linejoin='round' stroke-linecap='round'/>";
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
        $max     = max(!empty($data) ? $data : [1]) ?: 1;
        $max     = ceil($max / 100) * 100;
        $cw      = $w - 80; $ch = $h - 48;
        $xOff    = 68; $yOff = 14;
        $n       = count($data);
        $spacing = $n > 0 ? $cw / $n : $cw;
        $barW    = max(8, $spacing * 0.55);
        $colors  = ['#0d3b66', '#1e5f74', '#2d8a6b', '#64748b', '#94a3b8'];

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
        $cx     = 85; $cy = $h / 2; $r = 65; $angle = -90;

        $svg  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
        foreach ($data as $i => $v) {
            $sweep = ($v / $total) * 360;
            if ($sweep >= 360) $sweep = 359.99;
            $x1    = $cx + $r * cos(deg2rad($angle));
            $y1    = $cy + $r * sin(deg2rad($angle));
            $angle += $sweep;
            $x2    = $cx + $r * cos(deg2rad($angle));
            $y2    = $cy + $r * sin(deg2rad($angle));
            $lg    = $sweep > 180 ? 1 : 0;
            $col   = $colors[$i % count($colors)];
            $svg  .= "<path d='M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$lg},1 {$x2},{$y2} Z' fill='{$col}' stroke='white' stroke-width='1.5'/>";
        }
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

    public function generateSalesReport(array $data, string $title = 'Sales Report', string $context = 'superadmin', string $period = 'all'): string {
        // Filter to fully-paid only for display purposes
        $paidData = array_filter($data, function($row) {
            $pType   = strtolower((string)($row['payment_type']   ?? ''));
            $pStatus = strtolower((string)($row['status'] ?? $row['payment_status'] ?? ''));
            return !in_array($pType, ['deposit', 'downpayment']) && $pStatus !== 'partial';
        });
        $paidData = array_values($paidData);

        $keyMetrics   = $this->calculateKeyMetrics($paidData, $context);
        $chartSVGs    = $this->generateChartSVGs($paidData, $context, $period);
        $tableHeaders = ($context === 'superadmin')
            ? ['Date', 'Tenant / Clinic', 'Plan', 'Amount (PHP)', 'Status']
            : ['Date', 'Patient', 'Amount (PHP)', 'Paid Via'];
        $tableData    = $this->prepareTableData($paidData, $context);
        $tableTitle   = $context === 'superadmin' ? 'Subscription Transactions' : 'Clinic Transactions';

        // Derive period label for the info block
        $periodLabel = match($period) {
            'daily'   => 'Daily — ' . date('F j, Y'),
            'weekly'  => 'Weekly — Week ' . date('W') . ', ' . date('Y'),
            'monthly' => 'Monthly — ' . date('F Y'),
            'yearly'  => 'Yearly — ' . date('Y'),
            default   => 'All Time',
        };

        $this->pdf->AddPage();
        $this->renderWatermark();
        $this->renderPageHeader($title, $context);

        if ($context === 'tenant' && $this->clinicName) {
            $this->renderClinicInfoBlock($periodLabel);
        } else {
            $this->renderPeriodBadge($periodLabel);
        }

        $this->renderKeyMetrics($keyMetrics);
        $this->renderCharts($chartSVGs);
        $this->renderSummaryBar($paidData, $context);
        $this->renderTable($tableHeaders, $tableData, $tableTitle);
        $this->renderTableTotals($paidData, count($tableHeaders));
        $this->renderPageFooter();

        return $this->pdf->Output('', 'S');
    }

    public function generateGenericReport(array $data, string $title, ?array $headers = null): string {
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }
        $this->pdf->AddPage();
        $this->renderWatermark();
        $this->renderPageHeader($title, 'generic');
        $this->renderTable($headers ?? [], $data, 'Records');
        $this->renderPageFooter();
        return $this->pdf->Output('', 'S');
    }

    // =========================================================================
    // Rendering helpers
    // =========================================================================

    /** Diagonal "CONFIDENTIAL" watermark — rendered once per page via SetPage loop after generation. */
    private function renderWatermark(): void {
        $pdf = $this->pdf;
        $pdf->SetFont('dejavusans', 'B', 52);
        $pdf->SetTextColor(230, 234, 240);
        $pdf->SetAlpha(0.07);
        $cx = $pdf->getPageWidth()  / 2;
        $cy = $pdf->getPageHeight() / 2;
        $pdf->StartTransform();
        $pdf->Rotate(45, $cx, $cy);
        $pdf->SetXY($cx - 60, $cy - 10);
        $pdf->Cell(120, 20, 'CONFIDENTIAL', 0, 0, 'C');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
        $pdf->SetTextColor(30, 41, 59);
    }

    private function renderPageHeader(string $title, string $context): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        // Header band
        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect(15, 15, $pw, 28, 'F');

        // Thin teal accent strip at top of band
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect(15, 15, $pw, 2, 'F');

        // Brand
        $pdf->SetFont('dejavusans', 'B', 15);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 20);
        $pdf->Cell($pw * 0.55, 8, 'OralSync', 0, 0, 'L');

        // Generated date
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetXY(20, 20);
        $pdf->Cell($pw, 8, 'Generated: ' . date('F j, Y  H:i'), 0, 0, 'R');

        // Report title
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetXY(20, 30);
        $pdf->Cell($pw * 0.65, 7, $title, 0, 0, 'L');

        // Context badge
        if ($context && $context !== 'generic') {
            $ctxLabel = $context === 'superadmin' ? 'System-Wide Report' : 'Clinic Report';
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetXY(20, 30);
            $pdf->Cell($pw, 7, $ctxLabel, 0, 0, 'R');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->Ln(32);
    }

    /**
     * Clinic info block — shown below the header for tenant reports.
     * Shows clinic name, address, phone on the left; report period on the right.
     */
    private function renderClinicInfoBlock(string $periodLabel): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $y   = $pdf->GetY() + 2;

        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect(15, $y, $pw, 20, 2, '1111', 'DF');

        // Teal left accent
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect(15, $y, 3, 20, 'F');

        // Clinic name
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetTextColor(13, 59, 102);
        $pdf->SetXY(21, $y + 3);
        $pdf->Cell($pw * 0.6, 6, $this->clinicName, 0, 0, 'L');

        // Period label (right side)
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetTextColor(45, 138, 107);
        $pdf->SetXY(20, $y + 3);
        $pdf->Cell($pw, 6, 'Period: ' . $periodLabel, 0, 0, 'R');

        // Address / phone
        $details = array_filter([$this->clinicAddress, $this->clinicPhone]);
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY(21, $y + 11);
        $pdf->Cell($pw * 0.75, 5, implode('  |  ', $details), 0, 0, 'L');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 26);
    }

    /** Simpler period badge for superadmin reports (no clinic info). */
    private function renderPeriodBadge(string $periodLabel): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $y   = $pdf->GetY() + 2;

        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect(15, $y, $pw, 10, 2, '1111', 'DF');

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY(20, $y + 2);
        $pdf->Cell($pw * 0.4, 6, 'REPORT PERIOD', 0, 0, 'L');

        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetTextColor(13, 59, 102);
        $pdf->SetXY(20, $y + 2);
        $pdf->Cell($pw, 6, $periodLabel, 0, 0, 'R');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 15);
    }

    private function renderKeyMetrics(array $metrics): void {
        if (empty($metrics)) return;
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30;
        $n    = count($metrics);
        $gap  = 5;
        $colW = ($pw - $gap * ($n - 1)) / $n;

        $this->renderSectionLabel('Performance Overview');
        $y = $pdf->GetY();

        foreach ($metrics as $i => $m) {
            $x = 15 + $i * ($colW + $gap);

            // Card background
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, 28, 3, '1111', 'DF');

            // Teal top accent bar on card
            $pdf->SetFillColor(45, 138, 107);
            $pdf->Rect($x, $y, $colW, 2, 'F');

            // Value
            $pdf->SetFont('dejavusans', 'B', 13);
            $pdf->SetTextColor(13, 59, 102);
            $pdf->SetXY($x, $y + 7);
            $pdf->Cell($colW, 8, (string)($m['value'] ?? ''), 0, 0, 'C');

            // Label
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($x, $y + 17);
            $pdf->Cell($colW, 6, strtoupper((string)($m['label'] ?? '')), 0, 0, 'C');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 34);
    }

    /**
     * Summary bar — sits between charts and the table.
     * Shows: Total Collected | Unique Patients | Avg per Visit | Highest Single Payment
     */
    private function renderSummaryBar(array $data, string $context): void {
        if ($context !== 'tenant' || empty($data)) return;

        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $total    = 0;
        $patients = [];
        $highest  = 0;
        foreach ($data as $row) {
            $amt = (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
            $total += $amt;
            if ($amt > $highest) $highest = $amt;
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($name) $patients[$name] = true;
        }
        $unique = count($patients);
        $avg    = $unique > 0 ? $total / count($data) : 0;

        $items = [
            ['Total Collected',       '₱' . number_format($total,   2)],
            ['Unique Patients',        (string)$unique                  ],
            ['Avg per Transaction',   '₱' . number_format($avg,     2)],
            ['Highest Single Payment','₱' . number_format($highest,  2)],
        ];

        $this->renderSectionLabel('Financial Summary');
        $y    = $pdf->GetY();
        $n    = count($items);
        $gap  = 4;
        $colW = ($pw - $gap * ($n - 1)) / $n;

        foreach ($items as $i => [$label, $value]) {
            $x = 15 + $i * ($colW + $gap);
            $pdf->SetFillColor(13, 59, 102);
            $pdf->SetDrawColor(13, 59, 102);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, 20, 3, '1111', 'DF');

            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x, $y + 3);
            $pdf->Cell($colW, 6, $value, 0, 0, 'C');

            $pdf->SetFont('dejavusans', '', 6.5);
            $pdf->SetTextColor(148, 196, 240);
            $pdf->SetXY($x, $y + 11);
            $pdf->Cell($colW, 5, strtoupper($label), 0, 0, 'C');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 26);
    }

    private function renderCharts(array $chartSVGs): void {
        if (empty($chartSVGs)) return;
        $pdf    = $this->pdf;
        $pw     = $pdf->getPageWidth() - 30;
        $colW   = ($pw - 8) / 2;
        $cardH  = 62;
        $svgH   = 50;

        $this->renderSectionLabel('Sales Analytics');
        $startY    = $pdf->GetY();
        $maxY      = $startY;
        $rowOnPage = 0;

        foreach ($chartSVGs as $i => $chart) {
            $col = $i % 2;
            $row = (int)floor($rowOnPage / 2);
            $x   = 15 + $col * ($colW + 8);
            $y   = $startY + $row * ($cardH + 8);

            if ($col === 0 && $i > 0 && $y + $cardH > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $this->renderWatermark();
                $this->renderContinuationBanner('Sales Analytics (continued)');
                $startY    = $pdf->GetY();
                $maxY      = $startY;
                $rowOnPage = 0;
                $row       = 0;
                $y         = $startY;
            }

            $rowOnPage++;

            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, $cardH, 3, '1111', 'DF');

            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->SetXY($x + 3, $y + 3);
            $pdf->Cell($colW - 6, 5, $chart['title'], 0, 0, 'C');

            if (!empty($chart['svg'])) {
                try {
                    $pdf->ImageSVG('@' . $chart['svg'], $x + 3, $y + 9, $colW - 6, $svgH, '', '', 0, false);
                } catch (\Exception $e) {
                    $pdf->SetFont('dejavusans', 'I', 7);
                    $pdf->SetTextColor(148, 163, 184);
                    $pdf->SetXY($x + 3, $y + 28);
                    $pdf->Cell($colW - 6, 5, '[Chart unavailable]', 0, 0, 'C');
                }
            }
            $maxY = max($maxY, $y + $cardH);
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($maxY + 6);
    }

    private function renderTable(array $headers, array $rows, string $title): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $n   = count($headers);
        if ($n === 0) return;

        $this->renderSectionLabel($title);

        $colW = array_fill(0, $n, $pw / $n);
        if ($n === 5) {
            $raw  = [38, 45, 40, 35, 22];
            $sum  = array_sum($raw);
            $colW = array_map(fn($c) => $c / $sum * $pw, $raw);
        } elseif ($n === 4) {
            $raw  = [38, 68, 42, 32];
            $sum  = array_sum($raw);
            $colW = array_map(fn($c) => $c / $sum * $pw, $raw);
        }

        $drawHeader = function() use ($pdf, $headers, $colW, $n) {
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
        };

        $drawHeader();

        if (empty($rows)) {
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->Cell($pw, 12, 'No transaction data available for this period.', 0, 1, 'C', true);
            $pdf->SetTextColor(30, 41, 59);
            return;
        }

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.2);

        $even = false;
        foreach ($rows as $row) {
            if ($pdf->GetY() + 8 > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $this->renderWatermark();
                $this->renderContinuationBanner('Clinic Transactions (continued)');
                $drawHeader();
            }

            $even = !$even;
            $pdf->SetFillColor($even ? 248 : 255, $even ? 250 : 255, $even ? 252 : 255);

            $cells = array_values((array)$row);
            while (count($cells) < $n) $cells[] = '';
            $cells = array_slice($cells, 0, $n);
            $x     = 15;
            $y     = $pdf->GetY();

            foreach ($cells as $ci => $cell) {
                $cellStr = (string)$cell;
                if ($ci === $n - 1) {
                    $lower = strtolower($cellStr);
                    if (in_array($lower, ['paid', 'full payment', 'cash', 'gcash', 'card', 'online'])) {
                        $pdf->SetTextColor(21, 128, 61);
                    } elseif (in_array($lower, ['partial', 'partial payment', 'downpayment'])) {
                        $pdf->SetTextColor(161, 98, 7);
                    } else {
                        $pdf->SetTextColor(30, 41, 59);
                    }
                } else {
                    $pdf->SetTextColor(30, 41, 59);
                }
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

    /** Grand total row pinned immediately after the last data row. */
    private function renderTableTotals(array $data, int $colCount): void {
        if (empty($data) || $colCount < 3) return;
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $total = 0;
        foreach ($data as $row) {
            $total += (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
        }

        // Make sure the total row fits on the current page
        if ($pdf->GetY() + 10 > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();
            $this->renderWatermark();
            $this->renderContinuationBanner('Clinic Transactions (continued)');
        }

        // Column widths match renderTable for n=4
        $colW = array_fill(0, $colCount, $pw / $colCount);
        if ($colCount === 4) {
            $raw  = [38, 68, 42, 32];
            $sum  = array_sum($raw);
            $colW = array_map(fn($c) => $c / $sum * $pw, $raw);
        } elseif ($colCount === 5) {
            $raw  = [38, 45, 40, 35, 22];
            $sum  = array_sum($raw);
            $colW = array_map(fn($c) => $c / $sum * $pw, $raw);
        }

        $pdf->SetFillColor(13, 59, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 8);
        $x = 15;
        $y = $pdf->GetY();

        // Label spans all but last two columns
        $labelSpan = array_sum(array_slice($colW, 0, $colCount - 2));
        $pdf->SetXY($x, $y);
        $pdf->Cell($labelSpan, 9, 'TOTAL (' . count($data) . ' transactions)', 0, 0, 'L', true);
        $x += $labelSpan;

        // Total amount
        $pdf->SetXY($x, $y);
        $pdf->Cell($colW[$colCount - 2], 9, '₱' . number_format($total, 2), 0, 0, 'R', true);
        $x += $colW[$colCount - 2];

        // Last column blank
        $pdf->SetXY($x, $y);
        $pdf->Cell($colW[$colCount - 1], 9, '', 0, 0, 'L', true);

        $pdf->Ln(9);
        $pdf->SetTextColor(30, 41, 59);
    }

    /**
     * Lightweight banner for continuation pages.
     * Much shorter than renderPageHeader — avoids the large gap.
     */
    private function renderContinuationBanner(string $label): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect(15, 15, $pw, 14, 'F');
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect(15, 15, $pw, 2, 'F');

        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 20);
        $pdf->Cell($pw * 0.6, 6, strtoupper($label), 0, 0, 'L');

        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetXY(20, 20);
        $pdf->Cell($pw, 6, 'OralSync  |  Generated: ' . date('F j, Y'), 0, 0, 'R');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY(34);
    }

    private function renderPageFooter(): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        // Apply footer to every page
        $totalPages = $pdf->getNumPages();
        for ($p = 1; $p <= $totalPages; $p++) {
            $pdf->setPage($p);
            $pdf->SetY(-20);
            $pdf->SetDrawColor(45, 138, 107);
            $pdf->SetLineWidth(0.5);
            $pdf->Line(15, $pdf->GetY(), 15 + $pw, $pdf->GetY());
            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(148, 163, 184);

            $left = 'OralSync Management System  |  Amounts in Philippine Peso (PHP)';
            if ($this->clinicName) $left = $this->clinicName . '  |  ' . $left;

            $pdf->Cell($pw * 0.6, 5, $left, 0, 0, 'L');
            $pdf->Cell($pw * 0.4, 5, 'Confidential  |  Page ' . $p . ' of ' . $totalPages, 0, 0, 'R');
        }
        $pdf->SetTextColor(30, 41, 59);
    }

    private function renderSectionLabel(string $label): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $y   = $pdf->GetY() + 3;

        $pdf->SetFillColor(45, 138, 107);
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

    private function generateChartSVGs(array $data, string $context, string $period = 'all'): array {
        $charts = [];

        if ($context === 'superadmin') {
            $planData = $this->aggregateByField($data, 'plan');
            $charts[] = ['title' => 'Sales by Subscription Plan', 'svg' => $this->createBarChartSVG(array_values($planData), array_keys($planData))];
        }

        if ($context === 'tenant') {
            $modeData = $this->aggregateByField($data, 'mode');
            if (!empty($modeData)) {
                $charts[] = ['title' => 'Revenue by Payment Method', 'svg' => $this->createPieChartSVG(array_values($modeData), array_keys($modeData))];
            }
        }

        if ($period === 'all' || $period === 'daily') {
            $daily = $this->aggregateByDate($data);
            if (!empty($daily)) $charts[] = ['title' => 'Daily Sales Performance', 'svg' => $this->createLineChartSVG(array_values($daily), array_keys($daily))];
        }
        if ($period === 'all' || $period === 'weekly') {
            $weekly = $this->aggregateByWeek($data);
            if (!empty($weekly)) $charts[] = ['title' => 'Weekly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($weekly), array_keys($weekly))];
        }
        if ($period === 'all' || $period === 'monthly') {
            $monthly = $this->aggregateByMonth($data);
            if (!empty($monthly)) $charts[] = ['title' => 'Monthly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($monthly), array_keys($monthly))];
        }
        if ($period === 'all' || $period === 'yearly') {
            $yearly = $this->aggregateByYear($data);
            if (!empty($yearly)) $charts[] = ['title' => 'Yearly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($yearly), array_keys($yearly))];
        }

        return $charts;
    }

    private function aggregateByWeek(array $data): array {
        $agg = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) { $key = 'Wk ' . date('W, Y', strtotime($date)); $agg[$key] = ($agg[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0); }
        }
        return array_slice($agg, -12, null, true);
    }

    private function aggregateByMonth(array $data): array {
        $agg = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) { $key = date('M Y', strtotime($date)); $agg[$key] = ($agg[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0); }
        }
        return array_slice($agg, -12, null, true);
    }

    private function aggregateByYear(array $data): array {
        $agg = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) { $key = date('Y', strtotime($date)); $agg[$key] = ($agg[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0); }
        }
        return array_slice($agg, -10, null, true);
    }

    private function aggregateByDate(array $data): array {
        $agg = [];
        foreach ($data as $row) {
            $date = $row['payment_date'] ?? $row['billing_date'] ?? $row['appointment_date'] ?? $row['date'] ?? '';
            if ($date) { $key = date('M d', strtotime($date)); $agg[$key] = ($agg[$key] ?? 0) + (float)($row['amount'] ?? $row['amount_paid'] ?? 0); }
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
        foreach ($data as $row) $totalRevenue += (float)($row['amount'] ?? $row['amount_paid'] ?? 0);

        if ($context === 'superadmin') {
            $tenants = [];
            foreach ($data as $row) if (!empty($row['tenant_name'])) $tenants[$row['tenant_name']] = true;
            return [
                ['label' => 'Total Sales',    'value' => '₱' . number_format($totalRevenue, 2)],
                ['label' => 'Active Clinics', 'value' => count($tenants)],
                ['label' => 'Transactions',   'value' => count($data)],
            ];
        }

        $patients = [];
        foreach ($data as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($name) $patients[$name] = true;
        }
        return [
            ['label' => 'Total Revenue',    'value' => '₱' . number_format($totalRevenue, 2)],
            ['label' => 'Unique Patients',  'value' => count($patients)],
            ['label' => 'Transactions',     'value' => count($data)],
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
                $pType   = strtolower((string)($row['payment_type'] ?? ''));
                $pStatus = strtolower((string)($row['status'] ?? $row['payment_status'] ?? ''));

                // Skip non-full-payment rows (safety net)
                if (in_array($pType, ['deposit', 'downpayment']) || $pStatus === 'partial') continue;

                $patient = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if (!$patient) $patient = 'N/A';

                // Payment method — use mode if available, fallback to source
                $mode = ucfirst($row['mode'] ?? $row['payment_type'] ?? $row['source'] ?? 'N/A');

                $prepared[] = [
                    $date,
                    $patient,
                    '₱' . number_format((float)($row['amount'] ?? $row['amount_paid'] ?? 0), 2),
                    $mode,
                ];
            }
        }
        return $prepared;
    }
}
