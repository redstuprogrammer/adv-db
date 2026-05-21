- make super admin's sidebar ui and style similar to tenant's sidebar ui and style. super admin's sidebar isnt as wide as tenant's. it doesnt have the same style of how the looks like for the icon and label for OralSync. overall, tenant's sidebar looks more modern than super admin's sidebar. super admin's sidebar doesnt have the same dark blue navy gradience. 

- for tenant's sidebar remove the label MAIN, CORE FEATURES, and MANAGEMENT. do this for dentist and receptionist too.

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


tenant_homepage.php (prompted)
- make another page for displaying the services. so, transfer the services section there. add a back button where the user can come back to the tenant homepage.


edit_tenant_homepage.php (prompted)
- when a user edits the clinic name, it should also update labels that mention the clinic name. for example for 'ToothFairy' clinic, the user edits the clinic name to "ToothFairy2", it should also automatically update the headline, the welcome label "Welcome to ToothFairy. Professional care for your dental health." (should be "Welcome to ToothFairy2. Professional care for your dental health."), the footer/copyright label, and etc.
- when I click a specific element on the left panel, it should pan or bring the user to that specific element in the live preview.
- make "The Architects of Your Smile
Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection." editable too.
(except for this)
- map


SUPER ADMIN
1. Tenant List (prompted)
    - when viewing a tenant's info, there is a green circle at the bottom right beside the "Close" button. that green circle looks like a livechat icon. remove it. I think its a css button. it does nothing. change the color of the green buttons to a more suitable colors. 
    - under actions column, change the color of "Activate" green button to a more suitable color. 

2. superadmin_tenant_reports.php (prompted)
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.

    Usage Statistics Report (prompted)
    - remove columns: patients, appointments, staff, dentists, notes, 
    - change column "Revenue" to "Sales".
    - 

3. superadmin_audit_logs.php (prompted)
    - improve audit logs. add more logs rather than just recording log in and log out.
    - remove tenant activity logs or any actions done by tenant. audit logs should only contain activities done by super admin.


tenant_homepage.php (prompted)
- add a section that displays the services that the clinic does. and this should detect what services are in the service table in the database.


ADMIN / TENANT
- admin/tenant should have the same abilities as dentist and receptionist. so admin/tenant should have the same pages and features as dentist and receptionist. (manage button in appointments, adding of patients, etc) but dont remove the pages that are already there for admin/tenant. just update some of the pages. 

(prompted)
- patients.php, remove "Patient Directory" label. remove the icon in the header too
- appointments.php remove "Appointment Management" label and the icon on the header too.
- billing.php, remove the icon in the header. move the searchbar inside the table of billing records and align "Current Booking Downpayment:" the same level with the button for "Set Bookindg Downpayment". the same as how it is in receptionist_billing.php (instructions below receptionist_billing.php). add a button for creating an invoice too, same as receptionist_billing.php. remove "Transaction Audit" label.
- users.php, remove the icon in the header.
- clinic_schedule.php's label in the header isnt the same font color as the page's headers.
- reports.php, remove the icon in the header.
- settings.php remove the icon in the header.

1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 

    (prompted)
    Sales Performance tab/section
    - Generate pdf button doesn't work:
    reports.php?tenant=toothfairy-73d1:746 Fetching revenue: /get_filtered_reports.php?type=revenue&date_from=&date_to=&page=1&per_page=10
    reports.php?tenant=toothfairy-73d1:750 Response status: 200
    /generate_pdf.php?tenant=toothfairy-73d1:1  Failed to load resource: the server responded with a status of 500 (Internal Server Error)
    reports.php?tenant=toothfairy-73d1:868 Error: PDF generation failed
        at reports.php?tenant=toothfairy-73d1:855:19
    (anonymous) @ reports.php?tenant=toothfairy-73d1:868


DENTIST
1. dentist_appointments.php (prompted)
    - make sure the list of appointments has a pagination.
    - change the look of the list of appointments. make the list of appointments identical to appointments.php and receptionist_appointments.php.

2. dentist_dashbord.php
    - fix the cards, calendar, everything; the layout.


RECEPTIONIST
1. receptionist_appointments.php (prompted)
    - appointments should be reschedulable.
    - change "In Progress" status to "Ongoing"

2. headers
    - receptionist_patients.php, remove "Patient Directory" label.
    - receptionist_apppointments.php, remove "Front Desk Appointments" label. and change the header's label to "Appointments".
    - receptionist_billing.php's header isnt the same as the other headers of other pages for receptionist. fix that, specifically talking about the height of the header. its bigger than the rest. remove "Manage invoices and transaction records" label.

3. receptionist_patients.php
    - patient list should contain patient records (clinical_record.php?) the same as records in dentist and admin/tenant(?)
    - patient list should also include the same View button and functions of dentist_patients.php.

4. receptionist_billing.php (prompted)
    - put searchbar inside the table above the columns. make it look the same as searchbar for receptionist_patients.php.
    - align "Current booking downpayment:" with the "Set Booking Downpayment" and "Create Invoice button" at the same level.

