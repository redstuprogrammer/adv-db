- make super admin's sidebar ui and style similar to tenant's sidebar ui and style.
- remove  super admin's abiliy to register for a new tenant. transfer it to the main hompage where clients register themselves. then also transfer payment process there. payment process should occur after client fills up the registration form.
- make headers consistent.


tenant_login.php (prompted)
- remove the green glow when clicking on username and password fields. use the same style as superadmin_login.php 


superadmin_login.php, tenant_login.php, and buttons in emailing in email address' inbox. 
- change the color of sign in buttons and other buttons that are currently green to a more suitable color.


SUPER ADMIN
1. Tenant List
    - when viewing a tenant's info, there is a green circle at the bottom right beside the "Close" button. that green circle looks like a livechat icon. remove it. I think its a css button. it does nothing. change the color of the green buttons to a more suitable colors. 
    - under actions column, change the color of "Activate" green button to a more suitable color. (prompted)

2. superadmin_tenant_reports.php
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.

    Usage Statistics Report
    - remove columns: patients, appointments, staff, dentists, notes, 
    - change column "Revenue" to "Sales".
    - 

3. superadmin_audit_logs.php
    - improve audit logs. add more logs rather than just recording log in and log out.
    - remove tenant activity logs or any actions done by tenant. audit logs should only contain activities done by super admin.


tenant_homepage.php (prompted)
- adda a section that displays the services that the clinic does. and this should detect what services are in the service table in the database.


ADMIN / TENANT
- admin/tenant should have the same abilities as dentist and receptionist. so admin/tenant should have the same pages and features as dentist and receptionist. (manage button in appointments, adding of patients, etc) but dont remove the pages that are already there for admin/tenant. just update some of the pages. 

1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 

    Sales Performance tab/section
    - Generate pdf button doesn't work:
    reports.php?tenant=toothfairy-73d1:746 Fetching revenue: /get_filtered_reports.php?type=revenue&date_from=&date_to=&page=1&per_page=10
    reports.php?tenant=toothfairy-73d1:750 Response status: 200
    /generate_pdf.php?tenant=toothfairy-73d1:1  Failed to load resource: the server responded with a status of 500 (Internal Server Error)
    reports.php?tenant=toothfairy-73d1:868 Error: PDF generation failed
        at reports.php?tenant=toothfairy-73d1:855:19
    (anonymous) @ reports.php?tenant=toothfairy-73d1:868

2. settings.php (prompted)
    - there should be an announcement section where admin can add announcements to be displayed for dentist and receptionist. the announcement should be displayed in the dashboard.

    

DENTIST
1. dentist_appointments.php (prompted)
    - remove the manage button and its function for updating an appointment's status.


RECEPTIONIST
1. receptionist_appointments.php (prompted)
    - remove appointment requests. patients who booked in the mobile should automatically appear in the list of appointments as they already paid in the mobile app. so there is no need to approve or disapprove.

    - appointments should be reschedulable.

edit_tenant_homepage.php (prompted)
- when a user edits the clinic name, it should also update labels that mention the clinic name. for example for 'ToothFairy' clinic, the user edits the clinic name to "ToothFairy2", it should also automatically update the headline, the welcome label "Welcome to ToothFairy. Professional care for your dental health." (should be "Welcome to ToothFairy2. Professional care for your dental health."), the footer/copyright label, and etc.
- when I click a specific element on the left panel, it should pan or bring the user to that specific element in the live preview.
- make "The Architects of Your Smile
Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection." editable too.
(except for this)
- map


sidebar
- make new pages have similar icons from pages that already exist.


