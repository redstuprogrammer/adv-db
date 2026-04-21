<?php
class OralSyncCSVGenerator {
    public function generateCSV($data, $headers = null, $filename = 'oralsync_report.csv') {
        // Clear any output buffers to prevent HTML leakage
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // If no headers provided, use keys from first data row
        if (!$headers && !empty($data)) {
            $headers = array_keys($data[0]);
        }

        // Standardize headers for SEO-friendly column names
        $standardHeaders = $this->standardizeHeaders($headers);

        // Write UTF-8 BOM for proper encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($output, $standardHeaders);

        // Write data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($standardHeaders as $header) {
                $originalKey = $this->getOriginalKey($header, $headers);
                $value = $row[$originalKey] ?? '';

                // Sanitize and format the value
                $csvRow[] = $this->sanitizeValue($value);
            }
            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }

    private function standardizeHeaders($headers) {
        $standardMap = [
            'Date' => 'transaction_date',
            'Activity Type' => 'activity_type',
            'Description' => 'description',
            'Count' => 'activity_count',
            'Tenant' => 'clinic_name',
            'Registration Date' => 'registration_date',
            'Clinic Name' => 'clinic_name',
            'Owner' => 'owner_name',
            'Email' => 'contact_email',
            'Status' => 'status',
            'appointment_date' => 'transaction_date',
            'first_name' => 'patient_first_name',
            'last_name' => 'patient_last_name',
            'service' => 'service_name',
            'amount' => 'amount_php',
            'clinic_name' => 'clinic_name'
        ];

        $standardHeaders = [];
        foreach ($headers as $header) {
            $standardHeaders[] = $standardMap[$header] ?? $this->slugify($header);
        }

        return $standardHeaders;
    }

    private function getOriginalKey($standardHeader, $originalHeaders) {
        $reverseMap = [
            'transaction_date' => ['Date', 'appointment_date'],
            'activity_type' => ['Activity Type'],
            'description' => ['Description'],
            'activity_count' => ['Count'],
            'clinic_name' => ['Tenant', 'Clinic Name', 'clinic_name'],
            'registration_date' => ['Registration Date'],
            'owner_name' => ['Owner'],
            'contact_email' => ['Email'],
            'status' => ['Status'],
            'patient_first_name' => ['first_name'],
            'patient_last_name' => ['last_name'],
            'service_name' => ['service'],
            'amount_php' => ['amount']
        ];

        if (isset($reverseMap[$standardHeader])) {
            foreach ($reverseMap[$standardHeader] as $possibleKey) {
                if (in_array($possibleKey, $originalHeaders)) {
                    return $possibleKey;
                }
            }
        }

        // Fallback: try to find by slugified match
        foreach ($originalHeaders as $orig) {
            if ($this->slugify($orig) === $standardHeader) {
                return $orig;
            }
        }

        return $standardHeader;
    }

    private function sanitizeValue($value) {
        // Convert to string
        $value = (string)$value;

        // Handle special characters and encoding
        $value = mb_convert_encoding($value, 'UTF-8', 'auto');

        // Replace problematic characters
        $value = str_replace(['"', '₱'], ['""', 'PHP'], $value);

        // Handle escaped quotes that might already exist
        $value = preg_replace('/(?<!")"(?!")/', '""', $value);

        return $value;
    }

    private function slugify($text) {
        // Convert to lowercase and replace spaces/special chars with underscores
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        $text = trim($text, '_');
        return $text;
    }
}