# Plan: Convert Appointment Time to 12-Hour Format in receptionist_appointments.php

## Information Gathered:
- **File**: `receptionist_appointments.php`
- **Task**: Make "appointment requests" column for time in 12 hour format
- **Current behavior**: Time is displayed in 24-hour format (e.g., "14:30:00")
- **Target behavior**: Time should be displayed in 12-hour format (e.g., "2:30 PM")

## Key Location Found:
In the Appointment Requests table section, the time is displayed at:
```php
<td><?php echo h($request['appointment_time'] ?: 'TBD'); ?></td>
```

This displays raw time from database which is in 24-hour format.

## Plan:
1. Add a helper function `formatTime12Hour($time)` at the top of the file (after existing helper functions) to convert 24-hour time to 12-hour format
2. Apply the helper function to the appointment requests table time display:
   - Change: `<?php echo h($request['appointment_time'] ?: 'TBD'); ?>`
   - To: `<?php echo h(formatTime12Hour($request['appointment_time'])); ?>`

## Dependent Files:
None - this is a standalone change in a single file.

## Followup Steps:
- Test the change by viewing the Appointment Requests tab
- Verify time displays correctly in 12-hour format (e.g., "2:30 PM" instead of "14:30:00")
