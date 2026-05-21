- when a tenant selects trial, still ask for billing info like in startup and professional subscription plans. then like other websites, there should be a checkbox if they still want to cancel the subscription or not. same system as other webs.

- make super admin's sidebar ui and style similar to tenant's sidebar ui and style. super admin's sidebar isnt as wide as tenant's. it doesnt have the same style of how the looks like for the icon and label for OralSync. overall, tenant's sidebar looks more modern than super admin's sidebar. super admin's sidebar doesnt have the same dark blue navy gradience. 

- for tenant's sidebar remove the label MAIN, CORE FEATURES, and MANAGEMENT. do this for dentist and receptionist too. (prompted)

(prompted)
- remove  super admin's abiliy to register for a new tenant. transfer it to the main homepage where clients register themselves. then also transfer payment process there. payment process should occur after client fills up the registration form. after successfully registration, display a message to the user to check their email for more information. their information should be displayed in the tenant list after.

- remove icons in headers for billing.php, appointments.php, patients.php, reports.php, receptionist_patients.php, dentist_patients.php
- make headers consistent. 
    
    - dentist_schedule.php doesnt have the same header style as the other pages for dentist fix that.
    - receptionist_billing.php doesnt have the same header style as the other pages for receptionist fix that.


tenant_login.php (prompted)
- remove the green glow when clicking on username and password fields. use the same style as superadmin_login.php 


superadmin_login.php, tenant_login.php, and buttons in emailing in email address' inbox. (prompted)
- change the color of sign in buttons and other buttons that are currently green to a more suitable color.


edit_tenant_homepage.php 
- (prompted) when I edit posts / Pulse Posts, it doesnt show the preview of the changes right away. fix that.
- map


SUPER ADMIN
2. superadmin_tenant_reports.php (prompted)
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
- admin/tenant should have the same abilities as dentist and receptionist. so admin/tenant should have the same pages and features as dentist and receptionist. (manage button in appointments, adding of patients, etc) but dont remove the pages that are already there for admin/tenant. just update some of the pages. 

headers
- patients.php, remove icon beside "Patients" label in the header.
- appointments.php, remove icon beside "Appointments" label in the header.
- move Current Booking Downpayment, Set Booking Downpayment and Create Invoice buttons to the right the same as receptionist_billing.php
- clinic_schedule.php, "Clinic Schedule" label should have the same font thickness or boldness as the other headers from other pages too.
- reports.php, remove icon beside "Reports & Analytics" label in the header.

1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 

    (prompted)
    Sales Performance tab/section
    - Generate pdf button doesn't work


DENTIST
2. headers
    - dentist_appointments.php, change "ToothFairy Dentist Portal" label to "Appointments"
    - dentist_patients.php, remove the icon in the header beside "My Patients" label.
    - dentist_schedule.php, use the same header format as the other pages for dentist. the header is taller and "My Schedule" font color is not the same as others - dark navy blue.
    - dentist_account_settings.php, change "ToothFairy Account Settings" label to just "Account Settings"



RECEPTIONIST
1. receptionist_appointments.php
    - appointments should be reschedulable. allow the receptionist and admin/tenant to reschedule a patient's appointment date and time. use the same modal form in creating/adding a new appointment.
    - change "In Progress" status to "Ongoing"

2. headers
    - receptionist_patients.php, remove "Patient Directory" label.
    - receptionist_apppointments.php, remove "Front Desk Appointments" label. and change the header's label to "Appointments".
    - receptionist_billing.php's header isnt the same as the other headers of other pages for receptionist. fix that, specifically talking about the height of the header. its bigger than the rest. remove "Manage invoices and transaction records" label.

3. receptionist_patients.php (prompted)
    - patient list should contain patient records (clinical_record.php?) the same as records in dentist and admin/tenant(?)
    - patient list should also include the same View button and functions of dentist_patients.php.

4. receptionist_billing.php (prompted)
    - put searchbar inside the same container where the table is. place the searchbar above the columns like how it is in receptionist_patients.php make it look the same as searchbar for receptionist_patients.php.

make the username in the sidebar more visible. the font color is making it hard to notice.