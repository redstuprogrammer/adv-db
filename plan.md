- when a tenant selects trial, still ask for billing info like in startup and professional subscription plans. then inside the tenant's clinic system, like other websites, there should be a page where they could see and manager their subscription plan. a checkbox or something similar, if they still want to cancel the subscription or not. same system as other webs.

- make super admin's sidebar ui and style similar to tenant's sidebar ui and style. super admin's sidebar isnt as wide as tenant's. it doesnt have the same style of how the looks like for the icon and label for OralSync. overall, tenant's sidebar looks more modern than super admin's sidebar. super admin's sidebar doesnt have the same dark blue navy gradience. 


edit_tenant_homepage.php 
- (prompted) when I edit posts / Pulse Posts, I dont want it showing the page refreshing every time a change happens. I want it to smoothly show the changes.
- map


SUPER ADMIN
2. superadmin_tenant_reports.php (prompted)
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
- admin/tenant should have the same abilities as dentist and receptionist. so admin/tenant should have the same pages and features as dentist and receptionist. (manage button in appointments, adding of patients, etc) but dont remove the pages that are already there for admin/tenant. just update some of the pages. 
    - patients.php, use the same "Add Patient" button and its functions as in receptionist_patients.php. use the same button "View" and "Records" too.
    - appointments.php, as same in receptionist_appointments.php where you could schedule an appointment, manage an appointment, and reschedule.

- put Subscription navigation link below Settings.


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


RECEPTIONIST
1. receptionist_appointments.php 
    - like in dentist_appointments.php, there should be filter tabs to see the appointments for all, today, and upcoming.

    - upon rescheduling an appointment. the fields must be automatically filled. the patient's name, the previously selected dentist, and the original scheduled date must be also displayed.

    - change "In Progress" status to "Ongoing"
    2026-05-23T15:59:42.489729Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Data truncated for column 'status' at row 1 in /home/site/wwwroot/receptionist_appointments.php:155
2026-05-23T15:59:42.4897696Z Stack trace:
2026-05-23T15:59:42.4897751Z #0 /home/site/wwwroot/receptionist_appointments.php(155): mysqli_stmt_execute(Object(mysqli_stmt))
2026-05-23T15:59:42.4897788Z #1 {main}
2026-05-23T15:59:42.4897827Z   thrown in /home/site/wwwroot/receptionist_appointments.php on line 155
2026-05-23T15:59:42.4897934Z 127.0.0.1 -  23/May/2026:15:59:42 +0000 "POST /receptionist_appointments.php" 500

3. receptionist_patients.php 
    - records button doesnt work


4. receptionist_billing.php 
    - make the container for the list of billing records look similar to billing.php. same searchbar and button positions. 
