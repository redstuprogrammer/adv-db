SCREEN SIZES
1. make the whole web app flexible for various screen sizes for computers and phones.

ANY SALES/FINANCE-RELATED DISPLAY
1. use philippine peso sign instead of dollar sign


SUPER ADMIN
1. superadmin_register.php
    - add an email address field
    (super_admins table in db):
    id	int	NO	PRI		auto_increment
    username	varchar(50)	NO	UNI		
    password_hash	varchar(255)	NO			
    password_reset_token	varchar(255)	YES	MUL		
    password_reset_expires	datetime	YES			
    last_login	datetime	YES			
    created_at	datetime	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED
    email	varchar(255)	YES	UNI		

2. add a page where new super admin accounts can be created. 
    - so remove Create An Account link in superadmin_login.php and create a new page to use it inside super admin's portal.

3. forgot_password_superadmin.php
    - I don't it works yet. so, use the email address the username of the user uses. email a verification to the email address and a link and page where they can change their password.

4. Register Clinic page
    - make sure used email can't be used again
    - make sure used username can't be used again
    - for City/Municipality field, change it into a dropdown and its elements should correlate with the specific Province/Area selected. it should correlate with the province/area we have in the dropdown for Province/Area.
    - alongside Subscription Tier, add a start date (date picker) and a custom duration on how long the tenant wants to use the system.
    - I don't know if 'Resend Email' button works. check if it works.

5. superadmin_tenant_reports.php
    - remove any Export CSV and Export PDF buttons and functions.
    - remove Reports Dashboard

6. superadmin_aduit_logs.php
    - remove any Export CSV button and its functions.

7. superadmin_settings.php
    - when Reset to Default is confirmed, it should automatically save.



TENANT LOGIN PAGE
1. I don't think tenant's login page recognizes the username of the tenant. make sure it accepts tenant's username. use db dump folder and table 'tenants' as a reference.

2. forgot_password_tenant.php
    - I don't think it works. so, use the email address. email a verification to the email address and a link and page where they can change their password.


SUPER ADMIN AND TENANT LOGIN PAGE
1. for every log out or when a user a logs out, and back button on browser is clicked, it shouldn't load the previous page. 
    write a middleware or a script to prevent 'Back Button' access after logout. I need to:
    - Set HTTP headers to disable browser caching (No-Cache, No-Store).
    - Use JavaScript to manipulate the browser history (e.g., window.history.replaceState) so the previous protected page is removed from the stack.
    - Ensure that if a user clicks 'Back', the browser is forced to re-verify the session with the server.



ADMIN
1. users.php
    - joined date column should display created_at field in the db. the date when the user was created. use db dump folder and 'users' table as a reference.

    when creating a user's account:
    - make sure used email can't be used again
    - make sure used username can't be used again

2. dashboard.php
    - make 'Sales Overview' display real data from db.
    - fix patients.php - "This page isn't working HTTP Error 500"

    Log Stream:
    2026-04-18T06:58:11.409279Z NOTICE: PHP message: tenant_dashboard.php accessed with tenant: toothfairy-bb24
    2026-04-18T06:58:11.428773Z 127.0.0.1 -  18/Apr/2026:06:58:11 +0000 "GET /dashboard.php" 200
    2026-04-18T06:58:11.8036865Z 127.0.0.1 -  18/Apr/2026:06:58:11 +0000 "GET /index.php" 302
    2026-04-18T06:58:11.8820386Z 127.0.0.1 -  18/Apr/2026:06:58:11 +0000 "GET /superadmin_dash.php" 200
    2026-04-18T06:58:23.1390393Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'p.status' in 'field list' in /home/site/wwwroot/patients.php:66
    2026-04-18T06:58:23.1390826Z Stack trace:
    2026-04-18T06:58:23.1390874Z #0 /home/site/wwwroot/patients.php(66): mysqli->prepare('SELECT p.patien...')
    2026-04-18T06:58:23.1390908Z #1 {main}
    2026-04-18T06:58:23.1390943Z   thrown in /home/site/wwwroot/patients.php on line 66
    2026-04-18T06:58:23.1390977Z 127.0.0.1 -  18/Apr/2026:06:58:22 +0000 "GET /patients.php" 500

3. settings.php
    - when password field is filled or changed. use user's email address to verify and change password. email a verification to the email address and a link where they can change their password. use the same logic for forgot_password_superadmin.php.
    - for the live preview, don't display hospital icon as the default icon. instead use the "OS" label, icon, or whatever it is in the tenant's default login page.
    - I still want you to try and replicate how the real tenant's login page looks like in the live preview. like display the whole login page there in the settings.php. this way the user/admin knows what the real tenant login page looks like.
    - make sure that the image uploaded for background image is high quality.
    - make sure that the image uploaded for clinic logo is high quality.
    - when Reset to Default is confirmed, it should automatically save.
    - "Brand Text Color" doesn't totally display the color code of the selected color from color picker. fix that.
    - the place holder for email/username and password should be editable too. i think its '.t-brandPanel'?

4. staff.php
    - when clicked, "This page isn't working HTTP Error 500"
     
    Log Stream:
    2026-04-18T07:15:21.5519493Z 127.0.0.1 -  18/Apr/2026:07:15:21 +0000 "GET /staff.php" 200
    2026-04-18T07:15:21.7184855Z 127.0.0.1 -  18/Apr/2026:07:15:21 +0000 "GET /index.php" 302
    2026-04-18T07:15:21.8010642Z 127.0.0.1 -  18/Apr/2026:07:15:21 +0000 "GET /superadmin_dash.php" 200
    2026-04-18T07:15:22.7993523Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'd.license_number' in 'field list' in /home/site/wwwroot/view_staff.php:34
    2026-04-18T07:15:22.7993974Z Stack trace:
    2026-04-18T07:15:22.7994018Z #0 /home/site/wwwroot/view_staff.php(34): mysqli->prepare('SELECT u.user_i...')
    2026-04-18T07:15:22.7994046Z #1 {main}
    2026-04-18T07:15:22.7994076Z   thrown in /home/site/wwwroot/view_staff.php on line 34
    2026-04-18T07:15:22.7994105Z 127.0.0.1 -  18/Apr/2026:07:15:22 +0000 "GET /view_staff.php" 500

5. clinic_schedule.php
    - Log Stream:
    2026-04-18T07:19:35.3864956Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Table 'oral.clinic_schedule' doesn't exist in /home/site/wwwroot/clinic_schedule.php:69
    2026-04-18T07:19:35.386536Z Stack trace:
    2026-04-18T07:19:35.3865787Z #0 /home/site/wwwroot/clinic_schedule.php(69): mysqli->prepare('SELECT day_of_w...')
    2026-04-18T07:19:35.3865839Z #1 {main}
    2026-04-18T07:19:35.3865944Z   thrown in /home/site/wwwroot/clinic_schedule.php on line 69
    2026-04-18T07:19:35.3865978Z 127.0.0.1 -  18/Apr/2026:07:19:35 +0000 "GET /clinic_schedule.php" 500

6. reports.php
    - Acitivity Audit Trail:
        - under column for time, use 12-hour format with pm or am.
        - under column for activity, just make it simple and specific. like if the activity is a login, it should just display login. not include the what user logged in. simply "Login" or whatever activity is done.
        - under column for movement details, I think "Tenant logged out" should be "Admin logged out". fix that.

    - Revenue Performance:
        - should also have a chart displaying the revenue performance of a tenant's clinic. for now, you can leave it empty and not use real data.
        - remove mock data



DENTIST
1. profile_settings.php
    - add a field where dentist can change their password. and when password field is filled or changed. use user's email address to verify and change password. email a verification to the email address and a link where they can change their password. use the same logic for forgot_password_superadmin.php.

2. dentist_schedule.php
    - remove everything
    - it should be a page where dentist could set their availability. they can set available dates and time.

RECEPTIONIST
1. receptionist_patients.php
    - add a field for username
    - make sure used email can't be used again
    - make sure used username can't be used again

2. Settings
    - add a field where receptionist can change their password. and when password field is filled or changed. use user's email address to verify and change password. email a verification to the email address and a link where they can change their password. use the same logic for forgot_password_superadmin.php.

3. receptionist_billing.php
    - make the appointment dropdown the second.
    - change "Add to Cart" button's label to "Add Service/s" 
    - instead of the receptionist selecting the service one by one, make it convenient for the receptionist where they could select one or multiple services then add that to the list/cart of services.
    - change "Amount Due After Deposit (₱)" label to "Total Amount".
    - Total Amount field should be a read-only field.