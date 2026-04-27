SUPER ADMIN 
1. Tenant list
    - I want you to make the searchbar borderline more visible. I did tell you that before, but maybe the borderline is only thicker when the searchbar are clicked. I want you make the display of borderline for searchbar more visible. so users can tell it apart from the rest of the UI elements.

2. superadmin_dash.php:1733  GET https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/get_sales_data.php 500 (Internal Server Error)
(anonymous) @ superadmin_dash.php:1733
(anonymous) @ superadmin_dash.php:1789
superadmin_dash.php:1787 Error loading sales data: Error: This method is not implemented: Check that a complete date adapter is provided.
    at En (chart.js:13:103816)
    at Rn.formats (chart.js:13:104034)
    at Ho.init (chart.js:13:155335)
    at chart.js:13:93539
    at u (chart.js:7:1237)
    at Tn.buildOrUpdateScales (chart.js:13:93264)
    at Tn._updateScales (chart.js:13:95998)
    at Tn.update (chart.js:13:95161)
    at new Tn (chart.js:13:91705)
    at superadmin_dash.php:1737:17
(anonymous) @ superadmin_dash.php:1787
Promise.catch
(anonymous) @ superadmin_dash.php:1787
(anonymous) @ superadmin_dash.php:1789


get_sales_data.php:1  Failed to load resource: the server responded with a status of 500 (Internal Server Error)
superadmin_dash.php:1787 Error loading sales data: Error: This method is not implemented: Check that a complete date adapter is provided.
    at En (chart.js:13:103816)
    at Rn.formats (chart.js:13:104034)
    at Ho.init (chart.js:13:155335)
    at chart.js:13:93539
    at u (chart.js:7:1237)
    at Tn.buildOrUpdateScales (chart.js:13:93264)
    at Tn._updateScales (chart.js:13:95998)
    at Tn.update (chart.js:13:95161)
    at new Tn (chart.js:13:91705)
    at superadmin_dash.php:1737:17
(anonymous) @ superadmin_dash.php:1787
chart.js:13 Uncaught Error: This method is not implemented: Check that a complete date adapter is provided.
chart.js:13 Uncaught Error: This method is not implemented: Check that a complete date adapter is provided.





TENANT LOGIN
1. tenant_login.php 
    - it still doesnt accept tenant's username.
    
    2026-04-23T13:39:19.7678577Z NOTICE: PHP message: PHP Warning:  Undefined array key "username" in /home/site/wwwroot/tenant_login.php on line 170
    2026-04-23T13:39:19.7692842Z 127.0.0.1 -  23/Apr/2026:13:39:19 +0000 "POST /tenant_login.php" 422

2. forgot_password_tenant.php
    - mailer doesn't work. I've tested a temporary email and a real email, nothing gets send to the inbox. its supposed to send a similar email like in forgot_password_superadmin.php. fix that.


ADMIN
1. dashboard.php
    - live clock isn't working, its displaying 00:00:00 AM
    - calendar is not displaying correctly, please fix.
    - Sales Overview doesnt display anything. bland white. tell me why.
    - dashboard.php?tenant=toothfairy-bb24:655 Uncaught SyntaxError: Unexpected token '<' (at dashboard.php?tenant=toothfairy-bb24:655:5)


2. patients.php
    - live clock isn't working, its displaying 00:00:00 AM
    - patients.php?tenant=toothfairy-bb24:474 Uncaught SyntaxError: Unexpected token '<' (at patients.php?tenant=toothfairy-bb24:474:5)


3. staff.php
    - I want staff.php to be the same as in 'Previous OralSync folder' - to use staff.php, view_staff_profile.php and edit_staff_details.php, but keep the UI consistent with our UI. I think it uses table 'staff_details' in the db (use db dump folder as a reference).

4. reports.php
    - when I click Revenue Performance button, it still doesnt do anything, it doesnt work. It doesnt display Revenue Performance tab -> <div class="tab-content" id="revenue">
    - live clock isn't working, its displaying 00:00:00 AM



DENTIST
1. live clock doesnt work for every page of dentist. fix.

2. dentist_patients.php
    - View button doesnt work
    dentist_patients.php…toothfairy-bb24:162 Uncaught SyntaxError: Unexpected token '<'
    dentist_patients.php?tenant=toothfairy-bb24:134 Uncaught ReferenceError: openPatientModal is not defined
    - clinical_record.php displays a white blank page:
    clinical_record.php?tenant=toothfairy-bb24&patient_id=1:1 Uncaught ReferenceError: showCustomAlert is not defined
    at clinical_record.php?tenant=toothfairy-bb24&patient_id=1:1:9
    (anonymous) @ clinical_record.php?tenant=toothfairy-bb24&patient_id=1:1

3. dentist_dashboard.php
    - dentist_dashboard.php?tenant=toothfairy-bb24:415 Uncaught SyntaxError: Unexpected token '<' (at dentist_dashboard.php?tenant=toothfairy-bb24:415:3)

4. dentist_appointments.php
    - dentist_appointments.php?tenant=toothfairy-bb24&filter=all:247 Uncaught SyntaxError: Unexpected token '<' (at dentist_appointments.php?tenant=toothfairy-bb24&filter=all:247:5)

  

RECEPTIONIST
1. receptionist_dashboard.php 
    - receptionist_dashboard.php?tenant=toothfairy-bb24:303 Uncaught SyntaxError: Unexpected token '<' (at receptionist_dashboard.php?tenant=toothfairy-bb24:303:3)

2. receptionist_patients.php
    - receptionist_patients.php?tenant=toothfairy-bb24:444 Uncaught SyntaxError: Unexpected token '<' (at receptionist_patients.php?tenant=toothfairy-bb24:444:5)
    receptionist_patients.php?tenant=toothfairy-bb24:343 Uncaught ReferenceError: openAddPatientModal is not defined
        at HTMLButtonElement.onclick (receptionist_patients.php?tenant=toothfairy-bb24:343:85)
    onclick @ receptionist_patients.php?tenant=toothfairy-bb24:343

3. receptionist_appointments.php
    - receptionist_appointments.php?tenant=toothfairy-bb24:452 Uncaught SyntaxError: Unexpected token '<' (at receptionist_appointments.php?tenant=toothfairy-bb24:452:5)
    receptionist_appointments.php?tenant=toothfairy-bb24:261 Uncaught ReferenceError: openScheduleModal is not defined
        at HTMLButtonElement.onclick (receptionist_appointments.php?tenant=toothfairy-bb24:261:79)
    onclick @ receptionist_appointments.php?tenant=toothfairy-bb24:261
    receptionist_appointments.php?tenant=toothfairy-bb24:258 Uncaught ReferenceError: showRequestsTab is not defined
        at HTMLButtonElement.onclick (receptionist_appointments.php?tenant=toothfairy-bb24:258:84)
    onclick @ receptionist_appointments.php?tenant=toothfairy-bb24:258
    
4. receptionist_billing.php
    - receptionist_billing.php?tenant=toothfairy-bb24:279 Uncaught SyntaxError: Unexpected token '<' (at receptionist_billing.php?tenant=toothfairy-bb24:279:5)
    receptionist_billing.php?tenant=toothfairy-bb24:415 Uncaught SyntaxError: Identifier 'cart' has already been declared (at receptionist_billing.php?tenant=toothfairy-bb24:415:9)
    receptionist_billing.php?tenant=toothfairy-bb24:175 Uncaught ReferenceError: openAddModal is not defined
        at HTMLButtonElement.onclick (receptionist_billing.php?tenant=toothfairy-bb24:175:71)
    onclick @ receptionist_billing.php?tenant=toothfairy-bb24:175
    receptionist_billing.php?tenant=toothfairy-bb24:174 Uncaught ReferenceError: openDepositModal is not defined
        at HTMLButtonElement.onclick (receptionist_billing.php?tenant=toothfairy-bb24:174:104)
    onclick @ receptionist_billing.php?tenant=toothfairy-bb24:174

5. live clock doesnt work for every page of receptionist. fix.