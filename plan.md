SUPER ADMIN
2. superadmin_tenant_reports.php 
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
    - appointments.php, still doesn't have the same modal form of receptionist_appointments.php when scheduling an appointment. use the same modal form.


subscription.php
    - this is indicated: "Payment method
No saved payment method is available. You can still manage your subscription settings here." but there are no display where user can edit/manage their payment method. plus the payment method is already selected during the registration. fix that. Next renewal should also be already indicated as the 


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


I added a new patient but superadmin_tenant_reports.php and reports.php didnt show any action recorded.

when I register a tenant, the email is automatically displayed as the username in the sidebar even though there is already a username field in the registration. only username should be displayed in the sidebar.


transfer the tenant code from the dashboard to the Book Appointment modal form in tenant's homepage