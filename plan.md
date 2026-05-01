1. generate_pdf.php
    - generate_pdf.php in billing.php displays a blank white page. whats wrong, why isnt it displaying anything
    
2. billing.php
    - remove treatment column, and make sure it displays the services done to the patient inside the pdf.

3. receptionist_appointments.php
    - make sure same appointment details such as date, time, dentist, etc. arent allowed. it should notify the user something like "booking is already exists".


TENANT HOMEPAGE 
1. <section class="py-24 bg-surface-container-low" id="schedule">
    - should display the tenant's corresponding weekly schedule set in 'clinic_schedule.php'.
    - make time slots display in 12-hour format too.

2. <section class="py-24" id="location">
    - remove display for "Valet Parking"
    - location and contacts should match the tenant's details.