
when I register a tenant, the email is automatically displayed as the username in the sidebar even though there is already a username field in the registration. only username should be displayed in the sidebar.


transfer the tenant's clinic code from the dashboard to the "Book Appointment" modal form in their tenant's homepage


Sales Reports
    - check if sales in reports in superadmin_sales_reports.php and tenant_sales_reports.php's pdf generation is good


patients.php, appointments.phpand billings.php
    - check if it looks like the same and works as for receptionist's.


subscription.php
    - check if these changes were already done.

    subscription.php
    - this is indicated: 
    
    "Payment method No saved payment method is available. You can still manage your subscription settings here." but there are no display or form where user can edit/manage their payment method. fix that. add a button where the user can be lead back to the paymongo page to assess their payment method. also, add a "save payment method" checkbox in the paymongo payment page (after registration). so if that checkbox is checked, that will be the user;s saved method in subscription.php.

    tell me if my plan is good. suggest better ways to improve subscription.php page.


    In subscription.php page, add an "Auto-Renewal" checkbox, if that checkbox is checked, next renewal should be displayed. if auto-renewal is checkbox is checked, its the renewal after the selected duration for the tenant's subscription plan (selected in registration)

    and there must be a function where it lets the user know if their subscription plan will end. so I think display the ending date of their subscription in subscription.php page. and if ever the date for the due date is close, let the user know.


code.html (main landing page)
    - test the registration of tenants.


patients.php and receptionist_patients.php
    - alerts like "patient with the same email address already exists"  should be inside the modal form, not in a separate alert above the modal form. and it should not make the user automatically close the modal form and make the user fill the fields again.