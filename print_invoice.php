<?php
include "includes/connect.php";
date_default_timezone_set('Asia/Manila');

if (!isset($_GET['id'])) {
    die("Invoice ID is missing.");
}

$id = $_GET['id'];

// Fetch detailed billing info
// Revised Query in print_invoice.php
$query = "SELECT 
            py.payment_id, 
            py.amount, 
            py.mode, 
            py.status, 
            py.date_created,
            p.first_name, 
            p.last_name, 
            p.address, 
            p.contact_number, -- Fixed name
            s.service_name,
            d.last_name AS dentist_name
          FROM payment py
          LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
          LEFT JOIN patient p ON a.patient_id = p.patient_id
          LEFT JOIN service s ON a.service_id = s.service_id
          LEFT JOIN dentist d ON a.dentist_id = d.dentist_id
          WHERE py.payment_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Invoice not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice_#<?= $data['payment_id'] ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; padding: 40px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0d3b66; padding-bottom: 20px; }
        .logo { font-size: 28px; font-weight: bold; color: #0d3b66; text-transform: uppercase; }
        .clinic-info { text-align: right; font-size: 13px; color: #666; }
        
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin: 40px 0; }
        .section-title { font-size: 12px; font-weight: bold; color: #0d3b66; text-transform: uppercase; border-bottom: 1px solid #eee; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; color: #0d3b66; text-align: left; padding: 12px; border-bottom: 2px solid #0d3b66; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        
        .totals { margin-top: 30px; text-align: right; }
        .total-amount { font-size: 22px; font-weight: bold; color: #0d3b66; }
        
        .footer { margin-top: 50px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .invoice-box { box-shadow: none; border: none; }
        }
        .btn-print { background: #0d3b66; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: right; max-width: 800px; margin: 0 auto 20px;">
        <button class="btn-print" onclick="window.print()">Print Invoice</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div class="logo">OralSync Dental</div>
            <div class="clinic-info">
                <strong>ABD Dental Clinic</strong><br>
                 ABD Dental Clinic Miranda Banting Blgd,M. De Leon Ave, Cabanatuan City, Philippines<br>
                Contact: 0995 986 1620<br>
                Email: support@oralsync.com
            </div>
        </div>

        <div class="details-grid">
            <div>
    <div class="section-title">Patient Information</div>
    <strong><?= htmlspecialchars($data['first_name'] . " " . $data['last_name']) ?></strong><br>
    <?= htmlspecialchars($data['address']) ?><br>
    <?= htmlspecialchars($data['contact_number']) ?> </div>
            <div style="text-align: right;">
                <div class="section-title">Invoice Details</div>
                Invoice #: <strong>INV-<?= str_pad($data['payment_id'], 4, '0', STR_PAD_LEFT) ?></strong><br>
                Date: <?= date('M d, Y', strtotime($data['date_created'])) ?><br>
                Status: <strong><?= strtoupper($data['status']) ?></strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Service Description</th>
                    <th>Dentist</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($data['service_name']) ?></td>
                    <td>Dr. <?= htmlspecialchars($data['dentist_name']) ?></td>
                    <td style="text-align: right;">₱<?= number_format($data['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <p>Subtotal: ₱<?= number_format($data['amount'], 2) ?></p>
            <p>Payment Method: <?= $data['mode'] ?></p>
            <div class="total-amount">Total Paid: ₱<?= number_format($data['amount'], 2) ?></div>
        </div>

        <div class="footer">
            Thank you for choosing OralSync Dental Clinic.<br>
            This is a computer-generated invoice and no signature is required.
        </div>
    </div>

</body>
</html>