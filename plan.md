SUPERADMIN

1. superadmin_create_superadmin.php
- remove username field
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 
- below, add a feature where users can change their username and password (current password, password, confirm password)

2. superadmin_dash.php
- New This Month card doesnt display accurate users within the present month.

ADMIN
users.php
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 

edit_staff_details.php
- clicking save changes should bring the user back to view_staff_profile.php

admin_audit.php
- transfer Subscription Transactions inside superadmin_sales_reports.php. and remove Subscription Audit page/navigation link.

subscription_checkout.php
- code.html displays the three subscription plans. and subscription_checkout.php brings you to the respective page where you process the payment for the plan that you selected from the dropdown. so, instead of that. create buttons for subscription plans Trial, Startup, and Professional. and whichever plan the user clicks, it brings them to the respective page to process the payment.

reports.php - Sales Performance tab
- make the chart a bit smaller.
- remove "Service Rendered" column
- total sales dispaly label doesnt match with total sales from dashboard.php

billing.php and receptionist_billing.php
- for both those pages, display whether its a downpayment for the appointment, partial, or full payment from the billing/invoice. indicate time and date.

appointments.php
- remove details button

DENTIST
dentist_dashboard.php
- appointments that have status 'cancelled' should be not displayed in "Your Schedule For Today"

dentist_account_settings.php
- add a header like the other pages for dentist. transfer label 'Account Settings', the date, and live clock there. meaning just match how the page look likes for the other pages for dentists. 
- make sure if user only wants username to be changed, allow. and if they only want password, then allow changes. but for password, they must enter current password, new password, and confirm password.
- make Save Account Changes button color to dark blue like the others.

dentist_appointments.php
- make sure to not display duplicates. when status changes, it's still there instead of disappearing or duplicating.
- remove labels like "General Consultation"

clinical_record.php
- add a display for patient's age 
- after inputting changes and saving, this happens:
 2026-05-01T14:33:25.1425688Z NOTICE: PHP message: PHP Fatal error:  Uncaught ArgumentCountError: The number of elements in the type definition string must match the number of bind variables in /home/site/wwwroot/clinical_record.php:74
2026-05-01T14:33:25.1427699Z Stack trace:
2026-05-01T14:33:25.1427827Z #0 /home/site/wwwroot/clinical_record.php(74): mysqli_stmt->bind_param('iis', 1, 1, 2, 'Diagnosis: \nTre...')
2026-05-01T14:33:25.1427859Z #1 {main}
2026-05-01T14:33:25.1427888Z   thrown in /home/site/wwwroot/clinical_record.php on line 74
2026-05-01T14:33:25.142792Z 127.0.0.1 -  01/May/2026:14:33:25 +0000 "POST /clinical_record.php" 500


RECEPTIONIST
1. receptionist_dashboard.php
    - Waiting/Pending card should display appointments that have status 'pending'.
    - Check-outs done card should display appointments that have status 'completed'.
    - "Today's Patient Appointment Queue" should display appointments that are upcoming. and dont make it display all of the data, limit it to 8 rows.

2. receptionist_billing.php
    - list should not display duplicates. when status changes, it's still there instead of disappearing or duplicating.
    - Print button is a hyperlink text. change it to dark blue button like the others. make sure it works too. I think there is an invoice generator already in "Previous Oralsync' folder let's reuse that.
    - also, for receptionist_billing.php, place Related-Appointment dropdown as the 2nd
    - then, I like that there is a searchbar for looking up services, but it doesn't display the available services, its hidden.
    
3. receptionist_account_settings.php
    - add a header like the other pages for dentist. transfer label 'Account Settings', the date, and live clock there. meaning just match how the page look likes for the other pages for dentists. 
    - make sure if user only wants username to be changed, allow. and if they only want password, then allow changes. but for password, they must enter current password, new password, and confirm password.
    - make Save Account Changes button color to dark blue like the others.

4. receptionist_patients.php
    - after creating an account for patient, send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 

5. receptionist_appointments.php
    - in Appointment Requests, when I approve or disapprove an appointment, i shouldnt be brought back to appointments, instead just let me stay there.
    - view button doesnt work. why?
