- when a tenant selects trial, still ask for billing info like in startup and professional subscription plans. then like other websites, there should be a checkbox if they still want to cancel the subscription or not. same system as other webs.

- make super admin's sidebar ui and style similar to tenant's sidebar ui and style. super admin's sidebar isnt as wide as tenant's. it doesnt have the same style of how the looks like for the icon and label for OralSync. overall, tenant's sidebar looks more modern than super admin's sidebar. super admin's sidebar doesnt have the same dark blue navy gradience. 


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


1. reports.php
    Activity Audit Trail tab/section
    - improve audit logs. record other actions done by admin, dentist, and receptionist other than just log in and log out. 

    (prompted)
    Sales Performance tab/section
    - Generate pdf button doesn't work


RECEPTIONIST
1. receptionist_appointments.php ✅ COMPLETED
    - appointments should be reschedulable. allow the receptionist and admin/tenant to reschedule a patient's appointment date and time. use the same modal form in creating/adding a new appointment. ✅
    - change "In Progress" status to "Ongoing" ✅

2. headers ✅ COMPLETED
    - receptionist_patients.php, move "Add Patient" button to the right. and remove the icon beside the "Patient Records" label in the header. ✅
    - receptionist_appointments.php change "ToothFairy Front Desk" label in the header to just "Appointments". remove the "Appointments" label under the header. make sure the list of appointments has a pagination. ✅

3. receptionist_patients.php ✅ COMPLETED
    - patient list should contain patient records (clinical_record.php?) the same as records in dentist and admin/tenant. records button now works. ✅
    - patient list should also include the same View button and functions of dentist_patients.php. Fixed view button error - now uses client-side data instead of broken API call. ✅


4. receptionist_billing.php ✅ COMPLETED
    - make the container for the list of billing records look similar to billing.php. same searchbar and button positions. ✅

make the username in the sidebars more visible. the font color is making it hard to notice. ✅ COMPLETED