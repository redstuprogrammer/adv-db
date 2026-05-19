<?php
$raw = file_get_contents('https://gist.githubusercontent.com/chrisbjr/784565232f10cba6530856dc7fda367a/raw/');
$data = json_decode($raw, true);

$mapping = [];

foreach ($data as $item) {
    $area = $item['area'];
    $zip = $item['zip'];
    
    // Clean up PH - prefix
    $cleanArea = preg_replace('/^PH\s*-\s*/i', '', $area);
    // Replace ? with ñ
    $cleanArea = str_replace('?', 'ñ', $cleanArea);
    
    // Extract words
    // Format could be: "Abra Bangued" or "Las Piñas City Manila Doctors Village"
    // Let's store area to zip mapping or split it.
    // To make it easy, we can search for the city name in the area string.
    $mapping[] = [
        'area' => $cleanArea,
        'zip' => $zip
    ];
}

// Write the clean zip database
file_put_contents(__DIR__ . '/../Landing Page/zipcodes.json', json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Successfully built Landing Page/zipcodes.json with " . count($mapping) . " entries.\n";
