







edit_tenant_homepage.php
- apply changes works but refreshes page and just displays the previous/default element.
- sync to live site displays "Error" when clicked
edit_tenant_homepage.php?tenant=toothfairy-73d1:571  POST https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/Landing%20Page/edit_tenant_homepage.php?tenant=toothfairy-73d1 500 (Internal Server Error)
(anonymous) @ edit_tenant_homepage.php?tenant=toothfairy-73d1:571
- image size make it accept bigger size
- when I upload an image, it says "unauthorized". 

check pdf report for superadmin and tenant


Clinic Sales PDF
- remove average revenue per patient
- change Clinic Revenue Transactions to Clinic Transactions
- remove Revenue by Service Type section
- remove column for service type
- make sure it recognizes the difference between downpayment or full payment.


OralSync Report/PDF - superadmin pdf
- should display sales about tenant subscriptions

in pdf for tenant subscriptions change "Revenue by Subscription Plan" to "Sales by Subscription Plan"

superadmin pdf
- if pdf filter 'All Time' is selected it should display these charts: daily, weekly, monthly, yearly. and display all the sales logs which it does already.
- if pdf filter is 'Today's Sales' then it should display present day's chart and present day sales logs.
- if pdf filter is 'This Week's Sales' then it should display present week's chart and present week sales logs.
- if pdf filter is 'This Month's Sales' then it should display present month's chart and present month sales logs.
- if pdf filter is 'This Year's Sales' then it should display present year's chart and present year sales logs.

do the same for clinic pdf

edit_tenant_homepage.php
- when I click apply changes, it works, but then the page refreshes and displays the original state of the element. so it appears that the changes didnt apply.
- when I click sync to live site, it displays "Error: Unknown column 'announcements_json' in 'field list'".
