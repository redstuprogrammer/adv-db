- when a tenant selects trial, still ask for billing info like in startup and professional subscription plans. then inside the tenant's clinic system, like other websites, there should be a page where they could see and manager their subscription plan. a checkbox or something similar, if they still want to cancel the subscription or not. same system as other webs.

- make super admin's sidebar ui and style similar to tenant's sidebar ui and style. super admin's sidebar isnt as wide as tenant's. it doesnt have the same style of how the looks like for the icon and label for OralSync. overall, tenant's sidebar looks more modern than super admin's sidebar. super admin's sidebar doesnt have the same dark blue navy gradience. 


edit_tenant_homepage.php 
- when I edit posts / Pulse Posts, it must not save right away unless Sync to Live Site button is clicked. when apply changes is clicked, it must be a temporary save like the other elements.
- remove the map. do the same for tenant_homepage.php


SUPER ADMIN
2. superadmin_tenant_reports.php (prompted)
    Tenant Activity Report
    - instead of displaying "Admin logged in", display "Tenant logged in".
    - improve tenant activity logs. add more logs rather than just recording log in and log out.


ADMIN / TENANT
    - patients.php, the "Add Patient" button is kinda off in position, align the button perfectly with the searchbar. center the position of the modal form for adding patients.
    - appointments.php, it doesn't have the same styles of receptionist_appointments.php's modal form when scheduling an appointment. use the same modal form. also, the tabs for "All", "Today", and "Upcoming" isnt exactly the same as in dentist_appointments.php, use the same style. and also move the tabs to the right.

subscription.php
    - this is indicated: "Payment method
No saved payment method is available. You can still manage your subscription settings here." but there are no display where user can edit/manage their payment method. plus the payment method is already selected during the registration. fix that. Next renewal should also be already indicated as the 

settings.php
- move 🔌 Integrations & Payments inside subscription.php


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


DENTIST
1. dentist_appointments.php
    - for the list of appointments, dont display appointments that are "Declined". or dont display duplicates. use the same functions as for appointments.php and receptionist_appointments.php

RECEPTIONIST
1. receptionist_appointments.php 
    - same problem as appointments.php, it doesn't have the same styles of receptionist_appointments.php's modal form when scheduling an appointment. use the same modal form. also, the tabs for "All", "Today", and "Upcoming" isnt exactly the same as in dentist_appointments.php, use the same style. and also move the tabs to the right.

    - when I update an appointment to "Ongoing", it marks the status "Pending", which shouldnt happen, status must be Ongoing.

