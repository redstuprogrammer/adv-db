

superadmin_sales_reports.php
- pdf file display does not look good.

reports.php
- pdf file display does not look good.

reports.php
- when I click Sales Performance tab, and click Activity Audit Trail again, the list displays undefined values in all of the columns. fix that.


dashboard.php
- total sales (₱13,300.00) doesnt match with total sales (₱12,740.00) in reports.php and chart displays 225,640 pesos but total sales displays ₱12,740.00 (in reports.php) 

move "reset to default" modal form to the center. for both superadmin_settings.php and settings.php

Fix Color Picker Appearance

The .color-swatch and .swatch-box elements are currently rendering as vertical lines.

Adjust the CSS so that .swatch-box is a clearly visible 24x24px square with the background color of the current selection.

Ensure the .color-swatch wrapper has a fixed height and proper padding so it looks like a clickable button.

test creating an appointment for recept

Please select a patient, dentist, date, and time for the appointment.

receptionist_appointments.php
- instead of displaying "Please select a patient, dentist, date, and time for the appointment." when there is something missing, display it inside the modal form for booking an appointment.
- appointments by the receptionist should not be inserted in appointment requests, but on the appointment list. only appointment bookings from the mobile app should be in appointment requests