<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Jenssegers\Blade\Blade;
use Illuminate\Container\Container;

class OralSyncPDFGenerator {
    private $blade;
    private $pdf;

    private string $clinicName  = '';
    private string $ownerName   = '';
    private string $contactInfo = '';

    // =========================================================================
    // Bootstrap
    // =========================================================================

    public function __construct() {
        foreach ([
            'PDF_FONT_NAME_MAIN'  => 'dejavusans',
            'PDF_FONT_SIZE_MAIN'  => 10,
            'PDF_FONT_NAME_DATA'  => 'dejavusans',
            'PDF_FONT_SIZE_DATA'  => 8,
            'PDF_FONT_MONOSPACED' => 'courier',
        ] as $k => $v) { if (!defined($k)) define($k, $v); }

        $prev = error_reporting(E_ALL & ~E_DEPRECATED);
        $container = new \Jenssegers\Blade\Container;
        Container::setInstance($container);
        $this->blade = new Blade(__DIR__ . '/views', __DIR__ . '/cache', $container);
        error_reporting($prev);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setupPDF();
    }

    public function setClinicInfo(string $name, string $ownerOrAddress = '', string $contact = ''): void {
        $this->clinicName  = $name;
        $this->ownerName   = $ownerOrAddress;
        $this->contactInfo = $contact;
    }

    private function setupPDF(): void {
        $p = $this->pdf;
        $p->SetCreator('OralSync');
        $p->SetAuthor('OralSync');
        $p->SetTitle('OralSync Report');
        $p->setPrintHeader(false);
        $p->setPrintFooter(false);
        $p->SetDefaultMonospacedFont('courier');
        $p->SetMargins(15, 15, 15);
        $p->SetAutoPageBreak(true, 22);
        if (defined('PDF_IMAGE_SCALE_RATIO')) $p->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $p->SetFont('dejavusans', '', 10);
    }

    // =========================================================================
    // Public entry point
    // =========================================================================

    public function generateSalesReport(
        array  $data,
        string $title   = 'Sales Report',
        string $context = 'superadmin',
        string $period  = 'all'
    ): string {
        // Only fully-paid records
        $paidData = array_values(array_filter($data, function ($row) {
            $pt = strtolower((string)($row['payment_type']   ?? ''));
            $ps = strtolower((string)($row['status'] ?? $row['payment_status'] ?? ''));
            return !in_array($pt, ['deposit', 'downpayment']) && $ps !== 'partial';
        }));

        $periodLabel  = $this->periodLabel($period);
        $keyMetrics   = $this->calculateKeyMetrics($paidData, $context);
        $chartSVGs    = $this->generateChartSVGs($paidData, $context, $period);
        $tableHeaders = $context === 'superadmin'
            ? ['Date', 'Tenant / Clinic', 'Plan', 'Amount (PHP)', 'Status']
            : ['Date', 'Patient', 'Amount (PHP)', 'Payment Method'];
        $tableData    = $this->prepareTableData($paidData, $context);
        $tableTitle   = $context === 'superadmin' ? 'Subscription Transactions' : 'Clinic Transactions';

        $pdf = $this->pdf;
        $pdf->AddPage();

        $this->renderHeader($title, $context, $periodLabel, $paidData);
        $this->renderKeyMetrics($keyMetrics);
        $this->renderCharts($chartSVGs, $context);
        if ($context === 'tenant') $this->renderSummaryBar($paidData);
        $this->renderTable($tableHeaders, $tableData, $tableTitle);
        $this->renderTotalsRow($paidData, count($tableHeaders), $tableTitle);
        $this->renderAllFooters();

        return $pdf->Output('', 'S');
    }

    // =========================================================================
    // Page header — single unified method, no cascading Ln() calls
    // =========================================================================

    private function renderHeader(string $title, string $context, string $periodLabel, array $data): void {
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30;   // usable width (margins = 15 each side)
        $lx   = 15;                            // left edge

        // ── Top band ──────────────────────────────────────────────────────────
        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect($lx, 15, $pw, 26, 'F');
        // Teal accent strip
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect($lx, 15, $pw, 2.5, 'F');

        // Brand name — left, large
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($lx + 4, 18);
        $pdf->Cell($pw * 0.45, 8, 'OralSync', 0, 0, 'L');

        // Generated timestamp — top-right, small
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetXY($lx, 18);
        $pdf->Cell($pw - 4, 8, 'Generated: ' . date('F j, Y  g:i A T'), 0, 0, 'R');

        // Report title — bottom-left of band
        $pdf->SetFont('dejavusans', '', 9.5);
        $pdf->SetXY($lx + 4, 28);
        $pdf->Cell($pw * 0.60, 6, $title, 0, 0, 'L');

        // Context badge — bottom-right of band
        $ctxLabel = match($context) {
            'superadmin' => 'System-Wide Report',
            'tenant'     => 'Clinic Report',
            default      => '',
        };
        if ($ctxLabel) {
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetTextColor(180, 210, 240);
            $pdf->SetXY($lx, 28);
            $pdf->Cell($pw - 4, 6, $ctxLabel, 0, 0, 'R');
        }

        $pdf->SetTextColor(30, 41, 59);
        $curY = 46; // band bottom (15 + 26) + 5 padding

        // ── Info card below band ──────────────────────────────────────────────
        // For tenant reports: clinic name + owner/contact + period
        // For superadmin:     period + coverage range only
        $coverageRange = $this->deriveCoverageRange($data);

        if ($context === 'tenant' && $this->clinicName) {
            $cardH = 22;
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($lx, $curY, $pw, $cardH, 2.5, '1111', 'DF');
            // Left teal accent
            $pdf->SetFillColor(45, 138, 107);
            $pdf->Rect($lx, $curY, 3, $cardH, 'F');

            // Clinic name
            $pdf->SetFont('dejavusans', 'B', 10.5);
            $pdf->SetTextColor(13, 59, 102);
            $pdf->SetXY($lx + 6, $curY + 3);
            $pdf->Cell($pw * 0.58, 6, $this->clinicName, 0, 0, 'L');

            // Period — right column
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColor(45, 138, 107);
            $pdf->SetXY($lx, $curY + 3);
            $pdf->Cell($pw - 4, 6, 'Period: ' . $periodLabel, 0, 0, 'R');

            // Owner / contact second row
            $sub = array_filter([$this->ownerName, $this->contactInfo]);
            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($lx + 6, $curY + 11);
            $pdf->Cell($pw * 0.70, 5, implode('  ·  ', $sub), 0, 0, 'L');

            // Coverage range — right, second row
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetXY($lx, $curY + 11);
            $pdf->Cell($pw - 4, 5, $coverageRange . '  ·  Paid only', 0, 0, 'R');

            $curY += $cardH + 5;

        } else {
            // Superadmin — slim single-line meta bar
            $barH = 11;
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($lx, $curY, $pw, $barH, 2, '1111', 'DF');

            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($lx + 4, $curY + 2.5);
            $pdf->Cell($pw * 0.45, 5, 'PERIOD: ' . strtoupper($periodLabel), 0, 0, 'L');

            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(71, 85, 105);
            $pdf->SetXY($lx, $curY + 2.5);
            $pdf->Cell($pw - 4, 5, $coverageRange . '  ·  Paid only', 0, 0, 'R');

            $curY += $barH + 5;
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($curY);
    }

    // =========================================================================
    // KPI cards
    // =========================================================================

    private function renderKeyMetrics(array $metrics): void {
        if (empty($metrics)) return;
        $pdf  = $this->pdf;
        $pw   = $pdf->getPageWidth() - 30;
        $n    = count($metrics);
        $gap  = 4;
        $colW = ($pw - $gap * ($n - 1)) / $n;

        $this->sectionLabel('Performance Overview');
        $y = $pdf->GetY();

        foreach ($metrics as $i => $m) {
            $x = 15 + $i * ($colW + $gap);

            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, 26, 3, '1111', 'DF');

            // Teal top stripe
            $pdf->SetFillColor(45, 138, 107);
            $pdf->Rect($x, $y, $colW, 2, 'F');

            // Value
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetTextColor(13, 59, 102);
            $pdf->SetXY($x + 2, $y + 6);
            $pdf->Cell($colW - 4, 8, (string)($m['value'] ?? ''), 0, 0, 'C');

            // Label
            $pdf->SetFont('dejavusans', '', 6.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($x + 2, $y + 16);
            $pdf->Cell($colW - 4, 5, strtoupper((string)($m['label'] ?? '')), 0, 0, 'C');
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 31);
    }

    // =========================================================================
    // Charts — 2-column grid
    // =========================================================================

    private function renderCharts(array $chartSVGs, string $context = ''): void {
        if (empty($chartSVGs)) return;
        $pdf   = $this->pdf;
        $pw    = $pdf->getPageWidth() - 30;
        $colW  = ($pw - 6) / 2;
        $cardH = 60;
        $svgH  = 48;

        $this->sectionLabel('Sales Analytics');
        $baseY     = $pdf->GetY();
        $maxY      = $baseY;
        $rendered  = 0;

        foreach ($chartSVGs as $i => $chart) {
            $col = $rendered % 2;
            $row = (int)floor($rendered / 2);
            $x   = 15 + $col * ($colW + 6);
            $y   = $baseY + $row * ($cardH + 5);

            // New page when a left-column chart would overflow
            if ($col === 0 && $rendered > 0 && $y + $cardH > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $this->contBanner('Sales Analytics (continued)');
                $baseY    = $pdf->GetY();
                $maxY     = $baseY;
                $row      = 0;
                $y        = $baseY;
            }

            // Card
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $colW, $cardH, 3, '1111', 'DF');

            // Title
            $pdf->SetFont('dejavusans', 'B', 7.5);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->SetXY($x + 2, $y + 3);
            $pdf->Cell($colW - 4, 5, $chart['title'], 0, 0, 'C');

            // SVG
            if (!empty($chart['svg'])) {
                try {
                    $pdf->ImageSVG('@' . $chart['svg'], $x + 2, $y + 9, $colW - 4, $svgH);
                } catch (\Throwable $e) {
                    $pdf->SetFont('dejavusans', 'I', 7);
                    $pdf->SetTextColor(148, 163, 184);
                    $pdf->SetXY($x + 2, $y + 28);
                    $pdf->Cell($colW - 4, 5, '[Chart unavailable]', 0, 0, 'C');
                }
            }

            $maxY = max($maxY, $y + $cardH);
            $rendered++;
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($maxY + 5);
    }

    // =========================================================================
    // Financial summary bar (tenant only)
    // =========================================================================

    private function renderSummaryBar(array $data): void {
        if (empty($data)) return;
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $total = $unique = $highest = 0;
        $seen  = [];
        foreach ($data as $row) {
            $amt = (float)($row['amount'] ?? $row['amount_paid'] ?? 0);
            $total += $amt;
            if ($amt > $highest) $highest = $amt;
            $nm = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($nm) $seen[$nm] = true;
        }
        $unique = count($seen);
        $avg    = count($data) > 0 ? $total / count($data) : 0;

        $items = [
            ['Total Collected',        '₱' . number_format($total,   2)],
            ['Unique Patients',         (string)$unique               ],
            ['Avg per Transaction',    '₱' . number_format($avg,     2)],
            ['Highest Payment',        '₱' . number_format($highest, 2)],
        ];

        $this->sectionLabel('Financial Summary');
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

            $pdf->SetFont('dejavusans', 'B', 8.5);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x + 2, $y + 3);
            $pdf->Cell($colW - 4, 6, $value, 0, 0, 'C');

            $pdf->SetFont('dejavusans', '', 6.5);
            $pdf->SetTextColor(160, 200, 240);
            $pdf->SetXY($x + 2, $y + 12);
            $pdf->Cell($colW - 4, 5, strtoupper($label), 0, 0, 'C');
        }
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 25);
    }

    // =========================================================================
    // Table
    // =========================================================================

    private function renderTable(array $headers, array $rows, string $title): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $n   = count($headers);
        if ($n === 0) return;

        $this->sectionLabel($title);
        $colW = $this->colWidths($n, $pw);

        $drawHeader = function () use ($pdf, $headers, $colW, $n): void {
            $pdf->SetFillColor(13, 59, 102);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 7.8);
            $pdf->SetLineWidth(0);
            $x = 15;
            $y = $pdf->GetY();
            foreach ($headers as $ci => $h) {
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($colW[$ci], 8.5, strtoupper((string)$h), 0, 'C', true, 0, $x, $y, true, 0, false, true, 8.5, 'M');
                $x += $colW[$ci];
            }
            $pdf->SetY($y + 8.5);
        };

        $drawHeader();

        if (empty($rows)) {
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->Cell($pw, 12, 'No transaction records for this period.', 0, 1, 'C', true);
            $pdf->SetTextColor(30, 41, 59);
            return;
        }

        $pdf->SetFont('dejavusans', '', 8.2);
        $pdf->SetLineWidth(0.15);

        $even = false;
        foreach ($rows as $row) {
            $cells = array_values((array)$row);
            while (count($cells) < $n) $cells[] = '';
            $cells = array_slice($cells, 0, $n);

            // Measure row height
            $lineH    = 4.2;
            $maxLines = 1;
            foreach ($cells as $ci => $cell) {
                $fitted = $this->fitCell((string)$cell, $ci, $n);
                $lines  = max(1, $pdf->getNumLines($fitted, max(8, $colW[$ci] - 3)));
                if ($lines > $maxLines) $maxLines = $lines;
            }
            $rowH = max(8, $maxLines * $lineH + 2);

            // Page break
            if ($pdf->GetY() + $rowH > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
                $this->contBanner($title . ' (continued)');
                $drawHeader();
            }

            $even = !$even;
            $pdf->SetFillColor($even ? 247 : 255, $even ? 249 : 255, $even ? 252 : 255);
            $x = 15;
            $y = $pdf->GetY();

            foreach ($cells as $ci => $cell) {
                $fitted = $this->fitCell((string)$cell, $ci, $n);

                // Color the last column (status / payment method)
                if ($ci === $n - 1) {
                    $l = strtolower($fitted);
                    $pdf->SetTextColor(
                        str_contains($l, 'cash') || str_contains($l, 'gcash') || str_contains($l, 'paid') || str_contains($l, 'card') || str_contains($l, 'online') || str_contains($l, 'transfer')
                            ? 21 : (str_contains($l, 'partial') ? 161 : 30),
                        str_contains($l, 'cash') || str_contains($l, 'gcash') || str_contains($l, 'paid') || str_contains($l, 'card') || str_contains($l, 'online') || str_contains($l, 'transfer')
                            ? 128 : (str_contains($l, 'partial') ? 98 : 41),
                        str_contains($l, 'cash') || str_contains($l, 'gcash') || str_contains($l, 'paid') || str_contains($l, 'card') || str_contains($l, 'online') || str_contains($l, 'transfer')
                            ? 61 : (str_contains($l, 'partial') ? 7 : 59)
                    );
                } else {
                    $pdf->SetTextColor(30, 41, 59);
                }

                // Right-align the amount column (second to last)
                $align = ($ci === $n - 2) ? 'R' : 'L';
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($colW[$ci], $rowH, $fitted, 'B', $align, true, 0, $x, $y, true, 0, false, true, $rowH, 'M');
                $x += $colW[$ci];
            }
            $pdf->SetY($y + $rowH);
        }

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
    }

    // =========================================================================
    // Totals row
    // =========================================================================

    private function renderTotalsRow(array $data, int $n, string $tableTitle): void {
        if (empty($data) || $n < 3) return;
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;

        $total = array_sum(array_map(fn($r) => (float)($r['amount'] ?? $r['amount_paid'] ?? 0), $data));

        if ($pdf->GetY() + 10 > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();
            $this->contBanner($tableTitle . ' (continued)');
        }

        $colW      = $this->colWidths($n, $pw);
        $labelSpan = array_sum(array_slice($colW, 0, $n - 2));

        $pdf->SetFillColor(13, 59, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 8);
        $x = 15;
        $y = $pdf->GetY();

        $pdf->SetXY($x, $y);
        $pdf->Cell($labelSpan, 9, 'TOTAL — ' . count($data) . ' transaction' . (count($data) !== 1 ? 's' : ''), 0, 0, 'L', true);
        $x += $labelSpan;

        $pdf->SetXY($x, $y);
        $pdf->Cell($colW[$n - 2], 9, '₱' . number_format($total, 2), 0, 0, 'R', true);
        $x += $colW[$n - 2];

        $pdf->SetXY($x, $y);
        $pdf->Cell($colW[$n - 1], 9, '', 0, 0, 'L', true);

        $pdf->Ln(9);
        $pdf->SetTextColor(30, 41, 59);
    }

    // =========================================================================
    // Continuation banner (lightweight — used on overflow pages)
    // =========================================================================

    private function contBanner(string $label): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $lx  = 15;

        $pdf->SetFillColor(13, 59, 102);
        $pdf->Rect($lx, 15, $pw, 13, 'F');
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect($lx, 15, $pw, 2, 'F');

        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($lx + 4, 20);
        $pdf->Cell($pw * 0.6, 5, strtoupper($label), 0, 0, 'L');

        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetXY($lx, 20);
        $pdf->Cell($pw - 4, 5, 'OralSync  ·  ' . date('F j, Y  g:i A T'), 0, 0, 'R');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY(33);
    }

    // =========================================================================
    // Footer — stamped on every page after content is laid out
    // =========================================================================

    private function renderAllFooters(): void {
        $pdf   = $this->pdf;
        $pw    = $pdf->getPageWidth() - 30;
        $total = $pdf->getNumPages();

        for ($p = 1; $p <= $total; $p++) {
            $pdf->setPage($p);
            $fy = $pdf->getPageHeight() - 17;

            // Separator line
            $pdf->SetDrawColor(45, 138, 107);
            $pdf->SetLineWidth(0.4);
            $pdf->Line(15, $fy, 15 + $pw, $fy);

            $pdf->SetFont('dejavusans', '', 6.8);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetXY(15, $fy + 2);

            $left = 'OralSync Management System  ·  Amounts in Philippine Peso (PHP)';
            if ($this->clinicName) $left = $this->clinicName . '  ·  ' . $left;
            $pdf->Cell($pw * 0.62, 5, $left, 0, 0, 'L');
            $pdf->Cell($pw * 0.38, 5, 'CONFIDENTIAL  ·  Page ' . $p . ' of ' . $total, 0, 0, 'R');
        }
        $pdf->SetTextColor(30, 41, 59);
    }

    // =========================================================================
    // Watermark — called once per page, uses safe alpha
    // =========================================================================

    private function watermark(): void {
        $pdf = $this->pdf;
        // Use a light gray text instead of alpha (alpha can cause blank pages on some TCPDF builds)
        $pdf->SetFont('dejavusans', 'B', 58);
        $pdf->SetTextColor(236, 239, 244);   // very light gray, no alpha needed
        $cx = $pdf->getPageWidth()  / 2;
        $cy = $pdf->getPageHeight() / 2;
        $pdf->StartTransform();
        $pdf->Rotate(40, $cx, $cy);
        $pdf->SetXY($cx - 58, $cy - 12);
        $pdf->Cell(116, 24, 'CONFIDENTIAL', 0, 0, 'C');
        $pdf->StopTransform();
        $pdf->SetTextColor(30, 41, 59);
    }

    // =========================================================================
    // Section label
    // =========================================================================

    private function sectionLabel(string $label): void {
        $pdf = $this->pdf;
        $pw  = $pdf->getPageWidth() - 30;
        $y   = $pdf->GetY() + 4;

        // Left teal bar
        $pdf->SetFillColor(45, 138, 107);
        $pdf->Rect(15, $y + 1, 3, 5, 'F');

        $pdf->SetFont('dejavusans', 'B', 8.5);
        $pdf->SetTextColor(13, 59, 102);
        $pdf->SetXY(21, $y);
        $pdf->Cell($pw - 6, 7, strtoupper($label), 0, 0, 'L');

        // Thin underline
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $y + 7, 15 + $pw, $y + 7);

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetY($y + 10);
    }

    // =========================================================================
    // Column widths
    // =========================================================================

    private function colWidths(int $n, float $pw): array {
        if ($n === 5) {
            $r = [34, 54, 32, 34, 26]; // Date | Tenant | Plan | Amount | Status
        } elseif ($n === 4) {
            $r = [34, 78, 36, 32];     // Date | Patient | Amount | Method
        } else {
            return array_fill(0, $n, $pw / $n);
        }
        $s = array_sum($r);
        return array_map(fn($c) => $c / $s * $pw, $r);
    }

    // =========================================================================
    // Cell text fitting
    // =========================================================================

    private function fitCell(string $v, int $ci, int $n): string {
        $v = preg_replace('/\s+/', ' ', trim($v)) ?? '';
        $limits = match($n) {
            5 => [14, 36, 18, 14, 14],
            4 => [14, 46, 14, 20],
            default => array_fill(0, $n, 36),
        };
        $max = $limits[$ci] ?? 36;
        return strlen($v) <= $max ? $v : substr($v, 0, $max - 1) . '…';
    }

    // =========================================================================
    // Period label helper
    // =========================================================================

    private function periodLabel(string $period): string {
        return match($period) {
            'daily'   => 'Daily — ' . date('F j, Y'),
            'weekly'  => 'Weekly — Week ' . date('W') . ', ' . date('Y'),
            'monthly' => 'Monthly — ' . date('F Y'),
            'yearly'  => 'Yearly — ' . date('Y'),
            default   => 'All Time',
        };
    }

    // =========================================================================
    // Coverage range
    // =========================================================================

    private function deriveCoverageRange(array $data): string {
        if (empty($data)) return 'No records';
        $ts = [];
        foreach ($data as $r) {
            $raw = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? $r['date'] ?? null;
            if ($raw && ($t = strtotime((string)$raw)) !== false) $ts[] = $t;
        }
        if (empty($ts)) return 'No dated records';
        sort($ts);
        $start = date('M d, Y', $ts[0]);
        $end   = date('M d, Y', end($ts));
        return $start === $end ? $start : $start . ' to ' . $end;
    }

    // =========================================================================
    // SVG chart builders
    // =========================================================================

    private function createLineChartSVG(array $data, array $labels, int $w = 490, int $h = 160): string {
        $max  = max(!empty($data) ? $data : [1]) ?: 1;
        $max  = ceil($max / 100) * 100;
        $cw   = $w - 72; $ch = $h - 44;
        $xO   = 62; $yO = 12;
        $n    = count($data);

        $s  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $s .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
        $s .= "<defs><linearGradient id='lg' x1='0' y1='0' x2='0' y2='1'>"
            . "<stop offset='0%' stop-color='#0d3b66' stop-opacity='0.15'/>"
            . "<stop offset='100%' stop-color='#0d3b66' stop-opacity='0'/>"
            . "</linearGradient></defs>";

        for ($i = 0; $i <= 4; $i++) {
            $y   = $yO + $ch - ($i / 4) * $ch;
            $val = number_format(($max / 4) * $i, 0);
            $s  .= "<line x1='{$xO}' y1='{$y}' x2='" . ($xO + $cw) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='0.8'/>";
            $s  .= "<text x='" . ($xO - 4) . "' y='" . ($y + 3.5) . "' text-anchor='end' font-size='8' fill='#94a3b8' font-family='Arial'>{$val}</text>";
        }
        $s .= "<line x1='{$xO}' y1='{$yO}' x2='{$xO}' y2='" . ($yO + $ch) . "' stroke='#cbd5e1' stroke-width='0.8'/>";
        $s .= "<line x1='{$xO}' y1='" . ($yO + $ch) . "' x2='" . ($xO + $cw) . "' y2='" . ($yO + $ch) . "' stroke='#cbd5e1' stroke-width='0.8'/>";

        if ($n > 0) {
            $pts = [];
            foreach ($data as $i => $v) {
                $f    = $n > 1 ? ($i / ($n - 1)) : 0.5;
                $pts[] = [$xO + $f * $cw, $yO + $ch - ($v / $max) * $ch];
            }
            $area = "M{$pts[0][0]}," . ($yO + $ch);
            foreach ($pts as [$px, $py]) $area .= " L{$px},{$py}";
            $area .= " L{$pts[$n-1][0]}," . ($yO + $ch) . " Z";
            $s .= "<path d='{$area}' fill='url(#lg)'/>";
            $path = "M{$pts[0][0]},{$pts[0][1]}";
            for ($i = 1; $i < $n; $i++) $path .= " L{$pts[$i][0]},{$pts[$i][1]}";
            $s .= "<path d='{$path}' fill='none' stroke='#0d3b66' stroke-width='2' stroke-linejoin='round' stroke-linecap='round'/>";
            foreach ($pts as $i => [$px, $py]) {
                $s .= "<circle cx='{$px}' cy='{$py}' r='3' fill='white' stroke='#0d3b66' stroke-width='1.8'/>";
                if ($n <= 7 || $i % 2 === 0) {
                    $lbl = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
                    $s  .= "<text x='{$px}' y='" . ($yO + $ch + 13) . "' text-anchor='middle' font-size='7.5' fill='#64748b' font-family='Arial'>{$lbl}</text>";
                }
            }
        }
        return $s . "</svg>";
    }

    private function createBarChartSVG(array $data, array $labels, int $w = 490, int $h = 160): string {
        $max     = max(!empty($data) ? $data : [1]) ?: 1;
        $max     = ceil($max / 100) * 100;
        $cw      = $w - 72; $ch = $h - 44;
        $xO      = 62; $yO = 12;
        $n       = count($data);
        $spacing = $n > 0 ? $cw / $n : $cw;
        $barW    = max(10, $spacing * 0.6);
        $colors  = ['#0d3b66', '#1e5f74', '#2d8a6b', '#64748b', '#94a3b8'];

        $s  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $s .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
        for ($i = 0; $i <= 4; $i++) {
            $y   = $yO + $ch - ($i / 4) * $ch;
            $val = number_format(($max / 4) * $i, 0);
            $s  .= "<line x1='{$xO}' y1='{$y}' x2='" . ($xO + $cw) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='0.8'/>";
            $s  .= "<text x='" . ($xO - 4) . "' y='" . ($y + 3.5) . "' text-anchor='end' font-size='8' fill='#94a3b8' font-family='Arial'>{$val}</text>";
        }
        $s .= "<line x1='{$xO}' y1='{$yO}' x2='{$xO}' y2='" . ($yO + $ch) . "' stroke='#cbd5e1' stroke-width='0.8'/>";
        $s .= "<line x1='{$xO}' y1='" . ($yO + $ch) . "' x2='" . ($xO + $cw) . "' y2='" . ($yO + $ch) . "' stroke='#cbd5e1' stroke-width='0.8'/>";
        foreach ($data as $i => $v) {
            $bh   = ($v / $max) * $ch;
            $bx   = $xO + ($i * $spacing) + ($spacing - $barW) / 2;
            $by   = $yO + $ch - $bh;
            $col  = $colors[$i % count($colors)];
            $s   .= "<rect x='{$bx}' y='{$by}' width='{$barW}' height='{$bh}' fill='{$col}' rx='3'/>";
            $lbl  = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
            $s   .= "<text x='" . ($bx + $barW / 2) . "' y='" . ($yO + $ch + 13) . "' text-anchor='middle' font-size='7.5' fill='#64748b' font-family='Arial'>{$lbl}</text>";
        }
        return $s . "</svg>";
    }

    private function createPieChartSVG(array $data, array $labels, int $w = 490, int $h = 160): string {
        $colors = ['#0d3b66', '#1e5f74', '#2d8a6b', '#64748b', '#94a3b8', '#475569'];
        $total  = array_sum($data) ?: 1;
        $cx     = 80; $cy = $h / 2; $r = 60; $angle = -90;

        $s  = "<svg width='{$w}' height='{$h}' xmlns='http://www.w3.org/2000/svg'>";
        $s .= "<rect width='{$w}' height='{$h}' fill='#ffffff'/>";
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
            $s    .= "<path d='M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$lg},1 {$x2},{$y2} Z' fill='{$col}' stroke='white' stroke-width='1.5'/>";
        }
        foreach ($data as $i => $v) {
            $col  = $colors[$i % count($colors)];
            $lbl  = htmlspecialchars($labels[$i] ?? '', ENT_XML1, 'UTF-8');
            $pct  = number_format(($v / $total) * 100, 1);
            $ly   = 26 + $i * 24;
            if ($ly + 12 > $h) break;
            $s   .= "<rect x='170' y='" . ($ly - 9) . "' width='10' height='10' fill='{$col}' rx='2'/>";
            $s   .= "<text x='186' y='{$ly}' font-size='9.5' fill='#334155' font-family='Arial'>{$lbl}</text>";
            $s   .= "<text x='186' y='" . ($ly + 11) . "' font-size='8' fill='#94a3b8' font-family='Arial'>{$pct}%  ·  ₱" . number_format($v, 0) . "</text>";
        }
        return $s . "</svg>";
    }

    // =========================================================================
    // Data helpers
    // =========================================================================

    private function generateChartSVGs(array $data, string $context, string $period): array {
        $charts = [];

        if ($context === 'superadmin') {
            $planData = $this->aggField($data, 'plan');
            if (!empty($planData))
                $charts[] = ['title' => 'Revenue by Subscription Plan', 'svg' => $this->createBarChartSVG(array_values($planData), array_keys($planData))];
        }

        if ($context === 'tenant') {
            $modeData = $this->aggField($data, 'mode');
            if (!empty($modeData))
                $charts[] = ['title' => 'Revenue by Payment Method', 'svg' => $this->createPieChartSVG(array_values($modeData), array_keys($modeData))];
        }

        if (in_array($period, ['all', 'daily'])) {
            $d = $this->aggDate($data);
            if (!empty($d)) $charts[] = ['title' => 'Daily Sales Performance', 'svg' => $this->createLineChartSVG(array_values($d), array_keys($d))];
        }
        if (in_array($period, ['all', 'weekly'])) {
            $d = $this->aggWeek($data);
            if (!empty($d)) $charts[] = ['title' => 'Weekly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($d), array_keys($d))];
        }
        if (in_array($period, ['all', 'monthly'])) {
            $d = $this->aggMonth($data);
            if (!empty($d)) $charts[] = ['title' => 'Monthly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($d), array_keys($d))];
        }
        if (in_array($period, ['all', 'yearly'])) {
            $d = $this->aggYear($data);
            if (!empty($d)) $charts[] = ['title' => 'Yearly Sales Performance', 'svg' => $this->createLineChartSVG(array_values($d), array_keys($d))];
        }

        return $charts;
    }

    private function aggDate(array $data): array {
        $a = [];
        foreach ($data as $r) {
            $dt = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? null;
            if ($dt) { $k = date('M d', strtotime($dt)); $a[$k] = ($a[$k] ?? 0) + (float)($r['amount'] ?? $r['amount_paid'] ?? 0); }
        }
        return array_slice($a, -12, null, true);
    }

    private function aggWeek(array $data): array {
        $a = [];
        foreach ($data as $r) {
            $dt = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? null;
            if ($dt) { $k = 'Wk ' . date('W, Y', strtotime($dt)); $a[$k] = ($a[$k] ?? 0) + (float)($r['amount'] ?? $r['amount_paid'] ?? 0); }
        }
        return array_slice($a, -12, null, true);
    }

    private function aggMonth(array $data): array {
        $a = [];
        foreach ($data as $r) {
            $dt = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? null;
            if ($dt) { $k = date('M Y', strtotime($dt)); $a[$k] = ($a[$k] ?? 0) + (float)($r['amount'] ?? $r['amount_paid'] ?? 0); }
        }
        return array_slice($a, -12, null, true);
    }

    private function aggYear(array $data): array {
        $a = [];
        foreach ($data as $r) {
            $dt = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? null;
            if ($dt) { $k = date('Y', strtotime($dt)); $a[$k] = ($a[$k] ?? 0) + (float)($r['amount'] ?? $r['amount_paid'] ?? 0); }
        }
        return array_slice($a, -10, null, true);
    }

    private function aggField(array $data, string $field): array {
        $a = [];
        foreach ($data as $r) {
            $v = $r[$field] ?? null;
            if (empty(trim((string)$v))) $v = 'Other';
            $a[$v] = ($a[$v] ?? 0) + (float)($r['amount'] ?? $r['amount_paid'] ?? 0);
        }
        return $a;
    }

    private function calculateKeyMetrics(array $data, string $context): array {
        $total = array_sum(array_map(fn($r) => (float)($r['amount'] ?? $r['amount_paid'] ?? 0), $data));

        if ($context === 'superadmin') {
            $tenants = [];
            foreach ($data as $r) if (!empty($r['tenant_name'])) $tenants[$r['tenant_name']] = true;
            return [
                ['label' => 'Total Revenue',   'value' => '₱' . number_format($total, 2)],
                ['label' => 'Active Clinics',  'value' => count($tenants)],
                ['label' => 'Transactions',    'value' => count($data)],
            ];
        }

        $patients = [];
        foreach ($data as $r) {
            $nm = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if ($nm) $patients[$nm] = true;
        }
        return [
            ['label' => 'Total Revenue',   'value' => '₱' . number_format($total, 2)],
            ['label' => 'Unique Patients', 'value' => count($patients)],
            ['label' => 'Transactions',    'value' => count($data)],
        ];
    }

    private function prepareTableData(array $data, string $context): array {
        $out = [];
        foreach ($data as $r) {
            $rawDate = $r['payment_date'] ?? $r['billing_date'] ?? $r['appointment_date'] ?? $r['date'] ?? '';
            $date    = $rawDate ? date('M d, Y', strtotime($rawDate)) : 'N/A';

            if ($context === 'superadmin') {
                $out[] = [
                    $date,
                    $r['tenant_name'] ?? 'N/A',
                    $r['plan'] ?? $r['subscription_tier'] ?? 'N/A',
                    '₱' . number_format((float)($r['amount'] ?? 0), 2),
                    ucfirst($r['status'] ?? 'Paid'),
                ];
            } else {
                $pt = strtolower((string)($r['payment_type'] ?? ''));
                $ps = strtolower((string)($r['status'] ?? $r['payment_status'] ?? ''));
                if (in_array($pt, ['deposit', 'downpayment']) || $ps === 'partial') continue;

                $patient = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                if (!$patient) $patient = 'N/A';

                // Map mode/payment_type to a readable label
                $rawMode = strtolower((string)($r['mode'] ?? $r['payment_type'] ?? $r['source'] ?? ''));
                $mode = match(true) {
                    str_contains($rawMode, 'gcash')                                 => 'GCash',
                    str_contains($rawMode, 'card') || str_contains($rawMode, 'credit') || str_contains($rawMode, 'debit') => 'Card',
                    str_contains($rawMode, 'transfer') || str_contains($rawMode, 'bank') => 'Bank Transfer',
                    str_contains($rawMode, 'online')                                => 'Online',
                    str_contains($rawMode, 'cash')                                  => 'Cash',
                    str_contains($rawMode, 'full')                                  => 'Cash',
                    str_contains($rawMode, 'web')                                   => 'Web',
                    str_contains($rawMode, 'mobile')                                => 'Mobile',
                    !empty($rawMode)                                                 => ucfirst($rawMode),
                    default                                                          => 'Cash',
                };

                $out[] = [
                    $date,
                    $patient,
                    '₱' . number_format((float)($r['amount'] ?? $r['amount_paid'] ?? 0), 2),
                    $mode,
                ];
            }
        }
        return $out;
    }
}
