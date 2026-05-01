<?php
$c = mysqli_connect('localhost', 'root', '', 'oral');
$r = mysqli_query($c, 'DESCRIBE billing');
while($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
