SUPER ADMIN
1. superadmin_tenant_reports.php
    - I want you to safely remove "Reports Dashboard".
    <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Reports Dashboard</div>
                    <div class="sa-card-subtitle">Visual summary of system activities and statistics</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="sa-tabs">
                <button class="sa-tab active" data-tab="tenant-activities">Tenant Activities</button>
                <button class="sa-tab" data-tab="user-registrations">User Registrations</button>
                <button class="sa-tab" data-tab="usage-statistics">Usage Statistics</button>
            </div>

            <!-- Tab Content: Tenant Activities -->
            <div class="sa-tab-content active" id="tenant-activities">
                <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: var(--sa-primary); margin: 0 0 8px 0;">Recent Tenant Activities</h3>
                        <p style="font-size: 0.875rem; color: var(--sa-muted); margin: 0;">Latest activities across all tenants</p>
                    </div>
                </div>
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Tenant</th>
                            <th>Activity</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="tenant-activities-table-body">
                        <?php
                        try {
                            $tenantActivities = [];
                            $stmt = $pdo->query("SELECT tal.log_date, t.company_name, tal.activity_type, tal.details 
                                               FROM tenant_activity_logs tal 
                                               JOIN tenants t ON tal.tenant_id = t.tenant_id 
                                               ORDER BY tal.log_date DESC LIMIT 10");
                            while ($activity = $stmt->fetch()) {
                                $tenantActivities[] = $activity;
                            }

                            if (count($tenantActivities) === 0) {
                                $tenantActivities = [
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),'company_name' => 'SeaSmile Dental','activity_type' => 'Appointment Scheduled','details' => 'New appointment booked for patient Maria Cruz'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),'company_name' => 'BrightHope Clinic','activity_type' => 'Payment Received','details' => 'Invoice payment of ₱2,500 received from patient'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-3 hours')),'company_name' => 'PearlCare Dental','activity_type' => 'Patient Created','details' => 'New patient profile added for John Reyes'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-4 hours')),'company_name' => 'SmileBright Clinic','activity_type' => 'Staff Login','details' => 'Receptionist Sarah logged in'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-5 hours')),'company_name' => 'DentalCare Plus','activity_type' => 'Invoice Generated','details' => 'Invoice #INV-2026-001 generated for treatment'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-6 hours')),'company_name' => 'HealthyTeeth Co','activity_type' => 'Appointment Completed','details' => 'Appointment completed for patient Anna Santos'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-7 hours')),'company_name' => 'BrightSmile Dental','activity_type' => 'Patient Updated','details' => 'Patient contact information updated'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-8 hours')),'company_name' => 'PearlWhite Clinic','activity_type' => 'Dentist Login','details' => 'Dr. Michael Chen logged in'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-9 hours')),'company_name' => 'CareDental','activity_type' => 'Subscription Renewed','details' => 'Monthly subscription payment processed'],
                                    ['log_date' => date('Y-m-d H:i:s', strtotime('-10 hours')),'company_name' => 'OralHealth Pro','activity_type' => 'Report Generated','details' => 'Monthly revenue report exported'],
                                ];
                            }

                            foreach ($tenantActivities as $activity) {
                                echo "<tr>
                                        <td>" . formatDateTimeReadable($activity['log_date']) . "</td>
                                        <td>" . htmlspecialchars($activity['company_name']) . "</td>
                                        <td>" . htmlspecialchars($activity['activity_type']) . "</td>
                                        <td>" . htmlspecialchars($activity['details']) . "</td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='4' style='text-align: center; color: var(--sa-muted);'>No activities found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab Content: User Registrations -->
            <div class="sa-tab-content" id="user-registrations">
                <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: var(--sa-primary); margin: 0 0 8px 0;">Recent User Registrations</h3>
                        <p style="font-size: 0.875rem; color: var(--sa-muted); margin: 0;">New tenant registrations and account creations</p>
                    </div>
                </div>
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Registration Date</th>
                            <th>Company Name</th>
                            <th>Owner</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="user-registrations-table-body">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT created_at, company_name, owner_name, contact_email, status 
                                               FROM tenants 
                                               ORDER BY created_at DESC LIMIT 10");
                            while ($tenant = $stmt->fetch()) {
                                echo "<tr>
                                        <td>" . formatDateReadable($tenant['created_at']) . "</td>
                                        <td>{$tenant['company_name']}</td>
                                        <td>{$tenant['owner_name']}</td>
                                        <td>{$tenant['contact_email']}</td>
                                        <td><span class='sa-pill " . ($tenant['status'] == 'active' ? 'sa-pill-active' : 'sa-pill-inactive') . "'>{$tenant['status']}</span></td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='5' style='text-align: center; color: var(--sa-muted);'>No registrations found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab Content: Usage Statistics -->
            <div class="sa-tab-content" id="usage-statistics">
                <div style="margin-bottom: 16px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: var(--sa-primary); margin: 0 0 8px 0;">Usage Statistics</h3>
                    <p style="font-size: 0.875rem; color: var(--sa-muted); margin: 0;">System usage metrics and tenant activity overview</p>
                </div>
                <div class="sa-grid">
                    <?php
                    try {
                        // Total tenants
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
                        $total_tenants = $stmt->fetch()['total'];

                        // Active tenants
                        $stmt = $pdo->query("SELECT COUNT(*) as active FROM tenants WHERE status = 'active'");
                        $active_tenants = $stmt->fetch()['active'];

                        // Today's activities
                        $stmt = $pdo->query("SELECT COUNT(*) as today FROM tenant_activity_logs WHERE DATE(log_date) = CURDATE()");
                        $today_activities = $stmt->fetch()['today'];

                        // This month's new tenants
                        $stmt = $pdo->query("SELECT COUNT(*) as new_month FROM tenants WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $new_month = $stmt->fetch()['new_month'];

                        // This week's activities
                        $stmt = $pdo->query("SELECT COUNT(*) as week FROM tenant_activity_logs WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                        $week_activities = $stmt->fetch()['week'];
                    } catch (Exception $e) {
                        $total_tenants = $active_tenants = $today_activities = $new_month = $week_activities = 0;
                    }
                    ?>
                    <div class="sa-metric">
                        <div class="sa-metric-value"><?php echo $total_tenants; ?></div>
                        <div class="sa-metric-label">Total Tenants</div>
                    </div>
                    <div class="sa-metric">
                        <div class="sa-metric-value"><?php echo $active_tenants; ?></div>
                        <div class="sa-metric-label">Active Tenants</div>
                    </div>
                    <div class="sa-metric">
                        <div class="sa-metric-value"><?php echo $today_activities; ?></div>
                        <div class="sa-metric-label">Today's Activities</div>
                    </div>
                    <div class="sa-metric">
                        <div class="sa-metric-value"><?php echo $new_month; ?></div>
                        <div class="sa-metric-label">New This Month</div>
                    </div>
                    <div class="sa-metric">
                        <div class="sa-metric-value"><?php echo $week_activities; ?></div>
                        <div class="sa-metric-label">Activities This Week</div>
                    </div>
                </div>
            </div>
        </div>

2. Revenue Trends
    - Chronological Ordering: The X-axis is currently sorted in reverse-chronological order (Newest to Oldest). It shows April 2026 on the far left and May 2025 on the far right. Please modify the data processing logic so the timeline flows from left to right (Oldest to Newest), ending with the most recent month.

    Visual Data Mapping: The chart is currently displaying a single flat line at the top of the Y-axis across all months. The visual line does not reflect the actual fluctuations in revenue data. Please ensure the data points are being mapped correctly to the Y-axis and that the axis scales dynamically to the values.

    Currency Formatting: Ensure the Y-axis and tooltips correctly handle the currency prefix (₱) without breaking the numerical plotting.



TENANT LOGIN - tenant_login.php
1. as I said it doesnt recognize the tenant's username. I tried entering 'toothfairy' which is a tenant's username (you can check db dump folder), but it says "Incorrect email or password". it should accept email/username for all user and roles.

2026-04-23T02:10:57.1371Z NOTICE: PHP message: PHP Warning:  Undefined array key "username" in /home/site/wwwroot/tenant_login.php on line 169
2026-04-23T02:10:57.4290481Z NOTICE: PHP message: Post-login redirect from tenant_login.php: dentist_dashboard.php?tenant=toothfairy-bb24

2. check if forgot_password_tenant.php mailer really works. 


ADMIN
make the borderline for the searchbars more visible

1. dashboard.php
    - clock doesnt work (00:00:00 AM)

2. patients.php
    - clock doesnt work (00:00:00 AM)

3. clinic_schedule.php
    - Task: Align the layout of clinic_schedule.php to match the "Services Management" page and optimize the schedule table.

    1. Header Layout (The "Services Management" Style):

    Structure: Wrap the page title and the clock in a single, full-width <div> with display: flex and justify-content: space-between.

    Alignment: * The title ("Clinic Schedule") must be on the far left.

    The Date and Clock must be on the far right, contained within a single line.

    Styling: Remove the centered "floating" header. Use a white background, a height of approximately 70px, and a subtle border-bottom: 1px solid #e2e8f0.

    2. Table & Card Refinement:

    Container: Instead of floating cards, place the schedule inside a single, clean white container with a max-width of 1000px and margin: 20px auto.

    Table Row Density: Reduce the padding on the table rows. The current rows are too tall. Aim for a "compact" look.

    Row Logic (Conditional Styling): * If a day is Unchecked (Closed): Apply a background color of #f8fafc (light grey) to the entire row and set the opacity of the time inputs to 0.5.

    If a day is Checked (Open): Use a white background and high-contrast text.

    3. Batch Action Buttons:

    Positioning: Move the "Copy Monday to All" buttons to the top right area of the table container (just below the header) rather than centering them in the middle of the screen.

    Style: Use a "Ghost Button" style (outline only) or a small, subtle button to keep the focus on the schedule itself.

    4. Save Button:

    Positioning: Align the "Save Schedule" button to the bottom right of the main container.

    Width: Keep it a standard button width (don't make it stretch across the whole screen).

4. reports.php 
    - Revenue Performance button isnt working. when I click the tab for it, nothing happens.

5. settings.php
    - turn Reset to Default modal form color red to dark blue like our consistent blue of our web. do the same for super admin's reset to default modal form. 

    and also when reset settings is clicked, it shouldnt show any alert anymore since there is a modal form for confirmation already. do that for super admin too.

    - reset to default is buggy, it doesnt really return somethings to default. fix that. make sure that when reset to default is applied, all the elements in the settings that can be edited is reset to default.

    - Task: Update the color picker inputs in the "Login Page Customization" section so they provide instant visual feedback.

    The Issue: Currently, when a user selects a color from the color picker, the small preview swatch/icon next to the hex code does not update until the form is saved. For example, selecting a shade of red for "Brand Text Color" still shows a white preview swatch.

    Requirements:

    Live Preview: Add a JavaScript event listener (using the input event) to all color picker elements.

    Dynamic Update: As the user moves the color slider, the background color of the preview swatch or the icon associated with that specific input should update immediately to match the selected hex value.

    Value Sync: Ensure that if a user manually types a hex code into the text field, the color picker swatch also updates to reflect that hex code.

    Visual Consistency: The preview should work for all four fields: Brand Card Background, Brand Text Color, Sign In Button Color, and Text Link Color.

    Technical Suggestion:
    Use a simple JavaScript function that targets the oninput event of the color input to change the style.backgroundColor of the preview element.



DENTIST 
1. dentist_patients.php
    - View button doesnt work. 
    - clinical_record.php displays a blank white page. if it doesnt have anything yet, you can use "Previous OralSync" folder as a reference and add things there. if its missing some db things, tell me what is needed to insert in MySQL. create a file where I can copy-paste the queries needed.

2. dentist_schedule.php
    - clock doesnt work (00:00:00 AM)

    - Task: Overhaul the dentist_schedule.php UI to match the clean dashboard style of the Services Management page and consolidate the saving logic.

    1. Header Sync:

    Refactor Header: Move the "My Schedule" title and the renderDateClock() output into a single, full-width t-header div.

    Layout: Use display: flex and justify-content: space-between so the title is on the left and the clock is on the right.

    2. Modernize the Layout (Table over Cards):

    The Problem: Currently, each day is a separate card with its own "Save" button. This is bulky and inefficient.

    The Solution: Convert the "schedule-grid" into a single compact table or list within one white container.

    Row Behavior: * If "Not Available" is checked, the entire row should be slightly greyed out (opacity: 0.6).

    Replace the individual "Save" buttons. Instead, wrap the entire table in a single <form> so the dentist can set their whole week and click one "Save Schedule" button at the bottom.

    3. UI Improvements:

    Toggle Look: Instead of a standard checkbox, use a cleaner "Switch" or a clearly labeled checkbox that toggles the visibility of the time inputs on that row.

    Compactness: Use the same row padding as the Services Management page to reduce vertical scrolling.

    Batch Action: Add a "Copy Monday to Weekdays" button at the top of the table to save the dentist time.

    4. Code Logic Update:

    Single POST: Adjust the PHP logic to handle an array of days in one POST request rather than processing one day at a time.

    Button: Place a single, primary "Save All Changes" button at the bottom right of the schedule container.

3. clock
    - the clock doesnt work on every page for dentist (00:00:00 AM) fix it.


LOG STREAMS:
2026-04-23T02:10:57.1371Z NOTICE: PHP message: PHP Warning:  Undefined array key "username" in /home/site/wwwroot/tenant_login.php on line 169
2026-04-23T02:10:57.4290481Z NOTICE: PHP message: Post-login redirect from tenant_login.php: dentist_dashboard.php?tenant=toothfairy-bb24
2026-04-23T02:15:38.125463Z NOTICE: PHP message: Error fetching dentist availability: Table 'oral.dentist_availability' doesn't exist
2026-04-23T02:19:52.7951679Z NOTICE: PHP message: PHP Warning:  Undefined array key "username" in /home/site/wwwroot/tenant_login.php on line 169
2026-04-23T02:19:53.130736Z NOTICE: PHP message: PHP Warning:  Undefined array key "username" in /home/site/wwwroot/tenant_login.php on line 169
2026-04-23T02:19:53.2458198Z NOTICE: PHP message: Post-login redirect from tenant_login.php: receptionist_dashboard.php?tenant=toothfairy-bb24
2026-04-23T02:19:53.2464728Z 127.0.0.1 -  23/Apr/2026:02:19:52 +0000 "POST /tenant_login.php" 302
2026-04-23T02:19:53.4274998Z 127.0.0.1 -  23/Apr/2026:02:19:53 +0000 "POST /tenant_login.php" 302
2026-04-23T02:19:53.4294847Z NOTICE: PHP message: Post-login redirect from tenant_login.php: receptionist_dashboard.php?tenant=toothfairy-bb24


RECEPTIONIST
1. clock
    - the clock doesnt work on every page for dentist (00:00:00 AM) fix it.

2. receptionist_patients.php
    - Add Patient button doesnt work. 

3. receptionist_appointments.php
    - Schedule Appointment doesnt work.

4. receptionist_billing.php
    - Task: Refactor the "Create New Invoice" modal in billing.php to support multi-service selection and a "Floor-Protected" dynamic total calculation.

    1. Multi-Select Service UI:

    Refactor Selection: Replace the current "Select -> Add to Cart" flow with a searchable multi-select component (e.g., Select2 style).

    Interaction: When a service is selected, it should appear as a "tag" or "pill" in the input.

    Button Update: Change the "Add to Cart" button label to "Add Service/s".

    Visual List: Below the selector, maintain a clean list of selected services and their base prices with a remove option.

    2. The "Floor-Protected" Total Calculation:

    Label Update: Rename the field "Amount Due After Deposit (₱)" to "Total Amount (₱)".

    Calculation Logic:

    Step 1 (Subtotal): Sum the prices of all selected services.

    Step 2 (Deduction): Subtract the booking downpayment ONLY if the "Related Appointment" was requested_by = 'patient' and the patient actually arrived.

    Step 3 (The Floor): This calculated value (Subtotal - Downpayment) is the "Original Total."

    Editable Constraint: The "Total Amount" field must remain editable, but implement a JavaScript validation:

    The user CAN increase the price (e.g., for extra materials or complexity).

    The user CANNOT lower the price below the Original Total.

    If they try to input a lower value, the field should automatically snap back to the "Original Total" and show a brief toast/message: "Price cannot be lower than the base service total."

    3. Data Handling & Synchronization:

    Real-time Updates: The "Total Amount" and "Original Total" floor must update instantly as services are added/removed or if the appointment selection changes the downpayment status.

    JSON Submission: Ensure procedures_json is correctly populated with the final list of services for the database.

    4. Header & Layout Sync:

    Header Alignment: Update the page header so the title "Billing & Payments" is on the left and the clock/date is on the right using justify-content: space-between, matching the Services Management page layout.


LANDING PAGE
    HOMEPAGE
    1. code.html
            - when Contact Us button is clicked, it should bring the user to the <section class="py-32 bg-surface"> or the part where it displays the email and contact number


    TENANT HOMEPAGE - tenant_homepage.php  
    1. announcements.php doesn't work
        - 2026-04-23T02:54:10.1109968Z NOTICE: PHP message: PHP Warning:  require_once(db_connection.php): Failed to open stream: No such file or directory in /home/site/wwwroot/announcements.php on line 2
        2026-04-23T02:54:10.1110374Z NOTICE: PHP message: PHP Fatal error:  Uncaught Error: Failed opening required 'db_connection.php' (include_path='.:/usr/local/lib/php') in /home/site/wwwroot/announcements.php:2
        2026-04-23T02:54:10.1110415Z Stack trace:
        2026-04-23T02:54:10.1110445Z #0 {main}
        2026-04-23T02:54:10.1110474Z   thrown in /home/site/wwwroot/announcements.php on line 2
        2026-04-23T02:54:10.1110503Z 127.0.0.1 -  23/Apr/2026:02:54:10 +0000 "GET /announcements.php" 500
    
    2. Weekly Schedule
        - inside weekly schedule displays the availability of the clinic in terms of days and time. I want the time to be displayed in a 12-hour format. modify that please.
    


    