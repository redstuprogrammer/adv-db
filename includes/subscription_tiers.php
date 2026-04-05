<?php
/**
 * Subscription Tier Definitions
 * 
 * Defines the features, limits, and pricing for each subscription tier
 */

const SUBSCRIPTION_TIERS = [
    'trial' => [
        'name' => 'Trial',
        'display_name' => 'TRIAL (14 days free)',
        'price_min' => 0,
        'price_max' => 0,
        'trial_days' => 14,
        'description' => 'Perfect for testing OralSync',
        'features' => [
            'max_dentists' => 1,
            'max_receptionists' => 0,
            'max_patients' => 50,
            'max_storage_gb' => 2,
            'appointment_scheduling' => true,
            'patient_records' => true,
            'basic_clinical_notes' => true,
            'payment_tracking' => false,
            'invoice_generation' => false,
            'email_reminders' => true,
            'daily_email_limit' => 10,
            'basic_reporting' => false,
            'mobile_dashboard' => true,
            'dental_chart_tracking' => false,
            'staff_credential_management' => false,
            'multiple_payment_methods' => false,
            'sms_notifications' => false,
        ],
        'restrictions' => [
            'Free trial for 14 days only',
            'Upgrade required after trial ends',
            'Limited to 1 dentist account',
            'No receptionists allowed',
            'Max 50 patient records',
            'Email reminders limited to 10/day',
            'No payment processing',
            'Basic reporting disabled',
            'Community support only',
            'No staff management',
            'No dental chart tracking',
        ]
    ],
    'startup' => [
        'name' => 'Startup',
        'display_name' => 'STARTUP (₱4,950-₱7,450/month)',
        'price_min' => 4950,
        'price_max' => 7450,
        'description' => 'For solo practitioners or small clinics',
        'features' => [
            'max_dentists' => 2,
            'max_receptionists' => 1,
            'max_patients' => 200,
            'max_storage_gb' => 5,
            'appointment_scheduling' => true,
            'patient_records' => true,
            'basic_clinical_notes' => true,
            'payment_tracking' => true,
            'invoice_generation' => true,
            'email_reminders' => true,
            'basic_reporting' => true,
            'mobile_dashboard' => true,
            'dental_chart_tracking' => false,
            'staff_credential_management' => false,
            'multiple_payment_methods' => false,
            'sms_notifications' => false,
        ],
        'restrictions' => [
            'No staff/credential management',
            'No dental chart tracking',
            'Limited payment methods (cash only)',
            'No SMS notifications',
            'Community support only',
        ]
    ],
    'professional' => [
        'name' => 'Professional',
        'display_name' => 'PROFESSIONAL (₱14,950-₱19,950/month)',
        'price_min' => 14950,
        'price_max' => 19950,
        'description' => 'For established clinics',
        'features' => [
            'max_dentists' => 5,
            'max_receptionists' => 3,
            'max_patients' => 2000,
            'max_storage_gb' => 50,
            'appointment_scheduling' => true,
            'patient_records' => true,
            'basic_clinical_notes' => true,
            'advanced_clinical_notes' => true,
            'payment_tracking' => true,
            'invoice_generation' => true,
            'email_reminders' => true,
            'sms_reminders' => true,
            'basic_reporting' => true,
            'advanced_reporting' => true,
            'mobile_dashboard' => true,
            'dental_chart_tracking' => true,
            'staff_credential_management' => true,
            'multiple_payment_methods' => true,
            'sms_notifications' => true,
            'prescription_management' => true,
            'activity_audit_logs' => true,
            'custom_service_categories' => true,
        ],
        'restrictions' => [
            'Standard support via email/phone',
        ]
    ]
];

/**
 * Get all available tiers
 * @return array Tier definitions
 */
function getAllTiers(): array {
    return SUBSCRIPTION_TIERS;
}

/**
 * Get a specific tier by key
 * @param string $tierKey Tier identifier (e.g., 'startup', 'professional')
 * @return array|null Tier definition or null if not found
 */
function getTierByKey(string $tierKey): ?array {
    return SUBSCRIPTION_TIERS[$tierKey] ?? null;
}

/**
 * Get tier names as array (for dropdowns)
 * @return array Associative array of tier keys and display names
 */
function getTierOptions(): array {
    $options = [];
    foreach (SUBSCRIPTION_TIERS as $key => $tier) {
        $options[$key] = $tier['display_name'];
    }
    return $options;
}

/**
 * Check if a feature is available in a tier
 * @param string $tierKey Tier identifier
 * @param string $feature Feature name
 * @return bool True if feature is available
 */
function tierHasFeature(string $tierKey, string $feature): bool {
    $tier = getTierByKey($tierKey);
    if (!$tier) return false;
    return (bool)($tier['features'][$feature] ?? false);
}

/**
 * Get a specific limit for a tier
 * @param string $tierKey Tier identifier
 * @param string $limit Limit key (e.g., 'max_patients', 'max_dentists')
 * @return int|null The limit value or null if not found
 */
function getTierLimit(string $tierKey, string $limit): ?int {
    $tier = getTierByKey($tierKey);
    if (!$tier) return null;
    return $tier['features'][$limit] ?? null;
}

/**
 * Validate if a tier key exists
 * @param string $tierKey Tier identifier to validate
 * @return bool True if tier is valid
 */
function isValidTier(string $tierKey): bool {
    return isset(SUBSCRIPTION_TIERS[$tierKey]);
}

/**
 * Get default tier (startup)
 * @return string Default tier key
 */
function getDefaultTier(): string {
    return 'startup';
}
?>
