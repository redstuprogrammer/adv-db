1. generate_pdf.php
    - generate_pdf.php in billing.php displays a blank white page. whats wrong, why isnt it displaying anything
    
2. billing.php
    - remove treatment column, and make sure it displays the services done to the patient inside the pdf.

3. receptionist_appointments.php
    - make sure same appointment details such as date, time, dentist, etc. arent allowed. it should notify the user something like "booking is already exists".


TENANT HOMEPAGE 
1. <section class="py-24 bg-surface-container-low" id="schedule">
    - should display the tenant's corresponding weekly schedule set in 'clinic_schedule.php'.
    - make time slots display in 12-hour format too.

2. <section class="py-24" id="location">
    - remove display for "Valet Parking"
    - location and contacts should match the tenant's clinic details.

TENANT LOGIN
1. forgot_password_tenant.php
    - 2026-05-01T15:21:57.4400347Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Table 'oral.password_resets' doesn't exist in /home/site/wwwroot/forgot_password_tenant.php:17
2026-05-01T15:21:57.4400946Z Stack trace:
2026-05-01T15:21:57.4401048Z #0 /home/site/wwwroot/forgot_password_tenant.php(17): mysqli_query(Object(mysqli), 'SHOW COLUMNS FR...')
2026-05-01T15:21:57.4401088Z #1 /home/site/wwwroot/forgot_password_tenant.php(116): tableHasColumns(Object(mysqli), 'password_resets', Array)
2026-05-01T15:21:57.4401116Z #2 {main}
2026-05-01T15:21:57.4401145Z   thrown in /home/site/wwwroot/forgot_password_tenant.php on line 17
2026-05-01T15:21:57.4401178Z 127.0.0.1 -  01/May/2026:15:21:57 +0000 "POST /forgot_password_tenant.php" 500


SUPERADMIN

superadmin_create_superadmin.php
- remove username field
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 
- below, add a feature where users can change their username and password (current password, password, confirm password)


ADMIN
users.php
when creating an account for new users:
- remove username field
- between Last Name and Email, add a field for "Phone Number".
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 

settings.php
- above the fields for password, add a username field. make sure if user only wants username to be changed, allow. and if they only want password, then allow changes. but for password, they must enter current password, new password, and confirm password.
- remove Brand Panel Background Color
- remove Brand Text Color
- allow color for <section class="t-card"> to be modified also
- when a clinic logo is uploaded, in tenant_login.php,hide the <div class="t-wrap">. and also clinic logo along with its name should have a transparent background. currently, it has a dark blue background.
- when I upload a Background Image, it doesnt display the image in live preview.
- when I upload a Clinic Logo, hide the <div class="t-wrap"> in live preview and display the clinic logo on the top of left. like how it is in tenant_login.php

staff.php
- remove button "Manage System Users"

view_staff_profile.php
- Update Details doesn't work, it just brings me back to staff.php

admin_audit.php
- add this page to super admin side

subscription_checkout.php
- add this page to the tenant/admin side.


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
    
3. receptionist_account_settings.php
    - add a header like the other pages for dentist. transfer label 'Account Settings', the date, and live clock there. meaning just match how the page look likes for the other pages for dentists. 
    - make sure if user only wants username to be changed, allow. and if they only want password, then allow changes. but for password, they must enter current password, new password, and confirm password.
    - make Save Account Changes button color to dark blue like the others.

4. receptionist_patients.php
    - after creating an account for patient, send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 
