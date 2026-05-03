SUPERADMIN

1. superadmin_create_superadmin.php
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 

ADMIN
users.php
- send an email to the email address inputted. this is to send their temporary password. like how emailing works for super admin (emailing tenant's email address). 
- when creating a user, remove the temporary password field.

reports.php (Sales Performance tab), billing.php, and receptionist_billing.php
- admin and receptionist could set the downpayment for an appointment booked in the mobile correct? now if they pay a deposit for the appointment (or downpayment), it should be displayed as downpayment. currently, every downpayment made through the mobile app is displayed as 'full payment'. maybe make it that it only display partial / full payment, when paymongo process in mobile is done.


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
    - display the time of the booked appointment

6. receptionist_patients.php
    - when creating a patient's account, remove temporary password field


edit_tenant_homepage.php
- allow announcements to be edited in edit_tenant_homepage.php rather than just bringing them to manage_posts.php which doesnt exist,

make sure when user clicks on announcements in the middle panel/preview, it allows them to conveniently edit without needing to select in the left panel.
- allow images/info for the team to be edited in edit_tenant_homepage.php rather than just bringing them to manage_team.php which doesnt exist