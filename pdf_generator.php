<?php
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

class OralSyncPDF extends TCPDF {
    public function Header() {
        // Logo
        $this->Image(__DIR__ . '/logo.png', 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'OralSync - System Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

function generatePDF($data, $title, $filename) {
    $pdf = new OralSyncPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator('OralSync');
    $pdf->SetAuthor('Super Admin');
    $pdf->SetTitle($title);
    $pdf->SetSubject('System Report');

    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    $pdf->AddPage();

    $pdf->SetFont('helvetica', '', 12);

    // Add content based on data type
    if (is_array($data) && isset($data[0])) {
        // Table data
        $html = '<h2>' . $title . '</h2>';
        $html .= '<table border="1" cellpadding="4">';
        $html .= '<thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        // Simple text content
        $pdf->Write(0, $title, '', 0, 'L', true, 0, false, false, 0);
        $pdf->Write(0, $data, '', 0, 'L', true, 0, false, false, 0);
    }

    $pdf->Output($filename, 'D'); // Download
}
?>