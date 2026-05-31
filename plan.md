SUPER ADMIN
2. superadmin_tenant_reports.php 
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
    - appointments.php, still doesn't have the same modal form of receptionist_appointments.php when scheduling an appointment. use the same modal form.


subscription.php
    - this is indicated: 
    
    "Payment method No saved payment method is available. You can still manage your subscription settings here." but there are no display or form where user can edit/manage their payment method. fix that. add a button where the user can be lead back to the paymongo page to assess their payment method. also, add a "save payment method" checkbox in the paymongo payment page (after registration). so if that checkbox is checked, that will be the user;s saved method in subscription.php.

    tell me if my plan is good. suggest better ways to improve subscription.php page.


In subscription.php page, add an "Auto-Renewal" checkbox, if that checkbox is checked, next renewal should be displayed. if auto-renewal is checkbox is checked, its the renewal after the selected duration for the tenant's subscription plan (selected in registration)

and there must be a function where it lets the user know if their subscription plan will end. so I think display the ending date of their subscription in subscription.php page. and if ever the date for the due date is close, let the user know.

in code.html (main landing page)
after registration, instead of displaying the paymongo link. bring the user to the paymongo page right away.


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


I added a new patient but superadmin_tenant_reports.php and reports.php didnt show any action recorded.

when I register a tenant, the email is automatically displayed as the username in the sidebar even though there is already a username field in the registration. only username should be displayed in the sidebar.


transfer the tenant's clinic code from the dashboard to the "Book Appointment" modal form in their tenant's homepage

2026-05-29T02:40:16.3698092Z NOTICE: PHP message: PHP Parse error:  syntax error, unexpected end of file in /home/site/wwwroot/services.php on line 624
2026-05-29T02:40:16.369847Z 127.0.0.1 -  29/May/2026:02:40:16 +0000 "GET /services.php" 500

for every filter in reports.php - activity audit trail
