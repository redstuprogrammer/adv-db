SUPER ADMIN
2. superadmin_tenant_reports.php 
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
    - appointments.php, still doesn't have the same modal form of receptionist_appointments.php when scheduling an appointment. use the same modal form.

    - when clicking the navigation link for patients.php, it immediately pops up the modal form for adding a patient, which shouldnt happen, fix that please.

subscription.php
    - this is indicated: "Payment method
No saved payment method is available. You can still manage your subscription settings here." but there are no display where user can edit/manage their payment method. plus the payment method is already selected during the registration. fix that. Next renewal should also be already indicated as the 


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


RECEPTIONIST
1. receptionist_appointments.php 
    - same problem as appointments.php, it doesn't have the same styles of receptionist_appointments.php's modal form when scheduling an appointment. use the same modal form. also, the tabs for "All", "Today", and "Upcoming" isnt exactly the same as in dentist_appointments.php, use the same style. and also move the tabs to the right.

    - when I update an appointment to "Ongoing", it marks the status "Pending", which shouldnt happen, status must be Ongoing.


I still see that the ui across every page for ever user isnt consistent. I want consistent ui and styles across every page for admin, dentist, and receptionist. the pagination buttons, any other buttons, searchbar, placement/position things in the similar pages for patients, appointments, and billing. please fix these.