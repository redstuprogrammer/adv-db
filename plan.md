FOR ANY DB RELATED ISSUES USE DB DUMP FOLDER AS A REFERENCE.

a. SUPER ADMIN
    1. forgot_password_superadmin.php
        - I dont think the mailer works. it doesnt send anything in email address' inbox.

    2. superadmin_create_superadmin.php
        - the ui displays a barebones page. fix that.

    3. superadmin_dash
        - Revenue Trends doesn't display correctly, it displays the opposite. let me explain: we already have a revenue for the month of april for tenant "ToothFairy" for his subscription plan 250 pesos, but it displays the 250 pesos revenue on the other months and 0 pesos for the month of april. it has a bug fix it.

        - in Super Admin Daily Activity, the most recent activity should be the first to be displayed. change the organization of the list.

        - in Tenant Daily Activity, the most recent activity should be the first to be displayed. change the organization of the list. 

    4. Register Clinic
        - make sure Subscription Duration doesnt accept negative numbers.

    5. superadmin_sales_reports
        - turn any display of dollars into pesos
        - why is the total revenue ₱2,988.00 when we only have one tenant in the db which purchased for 250 pesos. fix that.
        - it has the same Revenue Trends bug. fix that.

        - for PDF Report and CSV
            - remove Recent Transactions and Top Perfoming Tenants

b. FOR ALL SALES REPORTS:
    - For the Super Admin PDF: Focus on platform subscription revenue. Include a summary of Total Monthly Recurring Revenue, a pie chart of Subscription Plans, and a table of the latest subscription payments including Tenant Name, Plan, and Amount.

    - For the Tenant PDF: Focus on dental clinic operations. Include a summary of Total Sales, a bar chart of Revenue by Procedure Type, and a detailed table including Patient Name, Procedure, Provider, and Amount.

    - For both CSVs: Ensure all raw transaction data including IDs, timestamps, and payment statuses are included as columns.

    - tables are messed up with texts overflowing. peso sign is displayed as question mark, fix that. display it nice and organized.



c. FOR admin_logs and tenant activities - Action Type
    - just make it simple. since "Details" already display what type of activity happened, make action types simple. if it's a login by any users, just display "Login", the same for logout. if it's registration then just display registration no need to include which user. 

    in simple terms change:
        for admin_logs:
        Superadmin Login -> Login
        Superadmin Logout -> Logout
        Tenant Registration -> Registration
        Tenant Status Change -> Status
        Dentist Login -> Login
        Dentist Logout -> Logout
        Receptionist Login -> Login
        Receptionist Logout -> Logout
        Tenant Logout -> Logout
        Appointment Scheduled -> Appointment
        Payment Received -> Payment
        Patient Created -> Create
        Invoice Generated -> Invoice 
        Appointment Completed -> Appointment
        Patient Updated -> Update
        Subscription Renewed -> Renewal
        Report Generated -> Generation

        for tenant activities:
        Patient Created -> Create
        Appointment Scheduled -> Appointment
        Payment Received -> Payment
        Staff Added -> Create
        Clinical Notes -> Notes
        Appointment Created -> Appointment
        Appointment Updated -> Appointment
        Dentist Login -> Login
        Receptionist Login -> Login
        Dentist Logout -> Logout
        Receptionist Logout -> Logout
        Tenant Logout -> Logout



d. ADMIN
    1. tenant_login.php
        - when logging in for admin/tenant, username isnt still accepted/recognized. allow email/username for admin/tenant

    2. patients.php
        - 2026-04-19T10:52:55.992538Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'p.status' in 'field list' in /home/site/wwwroot/patients.php:66
        2026-04-19T10:52:55.992576Z Stack trace:
        2026-04-19T10:52:55.9925861Z #0 /home/site/wwwroot/patients.php(66): mysqli->prepare('SELECT p.patien...')
        2026-04-19T10:52:55.9925891Z #1 {main}
        2026-04-19T10:52:55.992592Z   thrown in /home/site/wwwroot/patients.php on line 66
        2026-04-19T10:52:55.992595Z 127.0.0.1 -  19/Apr/2026:10:52:55 +0000 "GET /patients.php" 500

    3. users.php 
        - joined date column still displays N/A, which is wrong. it should display the date when the user joined/created.

    4. staff.php
        - 2026-04-19T10:54:37.6727721Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'd.license_number' in 'field list' in /home/site/wwwroot/view_staff.php:34
        2026-04-19T10:54:37.6728164Z Stack trace:
        2026-04-19T10:54:37.6728215Z #0 /home/site/wwwroot/view_staff.php(34): mysqli->prepare('SELECT u.user_i...')
        2026-04-19T10:54:37.6728252Z #1 {main}
        2026-04-19T10:54:37.6728283Z   thrown in /home/site/wwwroot/view_staff.php on line 34
        2026-04-19T10:54:37.6728317Z 127.0.0.1 -  19/Apr/2026:10:54:37 +0000 "GET /view_staff.php" 500
        2026-04-19T10:54:40.0370764Z 127.0.0.1 -  19/Apr/2026:10:54:39 +0000 "GET /staff.php" 200
        2026-04-19T10:54:40.3700984Z 127.0.0.1 -  19/Apr/2026:10:54:40 +0000 "GET /index.php" 302
        2026-04-19T10:54:40.4499842Z 127.0.0.1 -  19/Apr/2026:10:54:40 +0000 "GET /superadmin_dash.php" 200
        2026-04-19T10:54:40.8954657Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'd.license_number' in 'field list' in /home/site/wwwroot/view_staff.php:34
        2026-04-19T10:54:40.8955043Z Stack trace:
        2026-04-19T10:54:40.8955084Z #0 /home/site/wwwroot/view_staff.php(34): mysqli->prepare('SELECT u.user_i...')
        2026-04-19T10:54:40.8955111Z #1 {main}
        2026-04-19T10:54:40.8955142Z   thrown in /home/site/wwwroot/view_staff.php on line 34
        2026-04-19T10:54:40.895517Z 127.0.0.1 -  19/Apr/2026:10:54:40 +0000 "GET /view_staff.php" 500
        2026-04-19T10:54:41.8213381Z 127.0.0.1 -  19/Apr/2026:10:54:41 +0000 "GET /staff.php" 200
        2026-04-19T10:54:42.1056436Z 127.0.0.1 -  19/Apr/2026:10:54:42 +0000 "GET /index.php" 302
        2026-04-19T10:54:42.1850698Z 127.0.0.1 -  19/Apr/2026:10:54:42 +0000 "GET /superadmin_dash.php" 200

    5. services.php
        - remove description column
        - in actions column, add a "View" button to see the full details of the service including its description.

    6. clinic_schedule.php
        - 2026-04-19T10:58:26.601879Z NOTICE: PHP message: PHP Fatal error:  Uncaught mysqli_sql_exception: Unknown column 'is_open' in 'field list' in /home/site/wwwroot/clinic_schedule.php:69
        2026-04-19T10:58:26.601922Z Stack trace:
        2026-04-19T10:58:26.6019263Z #0 /home/site/wwwroot/clinic_schedule.php(69): mysqli->prepare('SELECT day_of_w...')
        2026-04-19T10:58:26.6019291Z #1 {main}
        2026-04-19T10:58:26.6019321Z   thrown in /home/site/wwwroot/clinic_schedule.php on line 69
        2026-04-19T10:58:26.601935Z 127.0.0.1 -  19/Apr/2026:10:58:26 +0000 "GET /clinic_schedule.php" 500

    7. reports.php
        - clicking Revenue Performance does nothing.    

    8. settings.php
        - why are there two password change. we already have one, remove the one in the bottom or "Account Settings"
        - remove Username Placeholder, Password Placeholder, Login Page Title, Brand Card Subtitle, and Login Page Description. don't allow them to be editable.
        - Brand Text Color doesnt display what color is being used.
        - allow t-card to be editable too.
        - "Clinic Login" and "Please sign in to access your clinic portal" font color should be complimentary to the selected color for t-card to make sure it is still visible. try to make the font color automatically compatible with the selected color for t-card.


e. I am still able to see protected pages when clicking the browser back button after logging out. I need a two-part fix:

    Server-Side: Update my logout logic and middleware to set 'Cache-Control' headers to no-store, no-cache, must-revalidate, proxy-revalidate. This ensures the browser doesn't store a snapshot of the page.

    Client-Side: Add a check in my (React/Vue/Next.js) route guard or a useEffect hook that verifies the auth token on every page load. If the token is missing or the session cookie is gone, redirect to /login immediately.

    Please provide the code for both the headers and the route protection logic afterwards.


f. I am facing a critical session conflict issue in my multi-tenant system with 4 roles (Super Admin, Admin, Dentist, Receptionist). Currently, opening different roles or different tenants in multiple tabs causes the sessions to overwrite each other.

    I need a 'Heavy Duty' architectural fix for the following:

    Session Namespacing: Stop using generic keys like token or user_id in LocalStorage/Cookies. Help me implement a system where session keys are namespaced by Tenant ID or Role (e.g., tenant123_token).

    SessionStorage over LocalStorage: Switch the client-side state to sessionStorage instead of localStorage. Explain how to ensure that when a Super Admin clicks a link to a specific tenant login, it opens in a new tab with a completely isolated session that won't bleed into other open tabs.

    Request Interceptors: Update my API interceptor (Axios/Fetch) to dynamically pull the correct token based on the current URL or tab context, ensuring that a request from the 'Dentist' tab doesn't accidentally use the 'Admin' credentials.

    Tenant-Specific Login: When I navigate from Super Admin to a Tenant Login, ensure the URL contains a unique identifier (like a UUID or slug) that the frontend uses to lock that tab to that specific tenant's data.

    Please provide the logic for the Auth Provider (client-side) and the Session Middleware (server-side) to support tab-level isolation.


g. DENTIST
    1. dentist_schedule.php
        - remove Today's Appointments and Upcoming Appointments.
        - this page should be a page where dentist could set their own schedule for date and time. a page where they could set their availability. so we can use their availability for when receptionist and patient schedules an appointment.
    
    2. remove profile_settings.php
        - remove settings from the sidebar.

h. Clock UI Display
    - use this style for every clock so that it is consistent. for admin and dentist:
        .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--dashboard-accent);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--dashboard-accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }
