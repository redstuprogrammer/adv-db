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


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 


RECEPTIONIST
1. receptionist_appointments.php ✅ COMPLETED
    - appointments should be reschedulable. allow the receptionist and admin/tenant to reschedule a patient's appointment date and time. use the same modal form in creating/adding a new appointment. ✅
    - change "In Progress" status to "Ongoing" ✅

3. receptionist_patients.php ✅ COMPLETED
    - patient list should contain patient records (clinical_record.php?) the same as records in dentist and admin/tenant. records button now works. ✅
    - when viewing a patient's info by the modal form, it looks the same as the dentist_patients.php but the way its displayed is not. fix the layout, make it look exactly the same as the modal form in dentist_patients.php
    - when adding a patient in the modal form, remove "Welcome email will be sent here" under email address input field. and make the borderline of the input fields more noticeable, it makes it kinda hard to notice since the borderline kinda transparent that it blends with the white color of the modal form. do this for the other modal forms too - admin/tenant, dentist, and other modal forms of receptionist. 


4. receptionist_billing.php 
    - make the container for the list of billing records look similar to billing.php. same searchbar and button positions. 
