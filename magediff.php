<?php

require './class.magediff.php';

echo "Old Magento installation path: ";
if (!empty($argv[1])) {
    $oldPath = $argv[1];
    echo $oldPath . "\n";
} else {
    $handle = fopen("php://stdin","r");
    $oldPath = trim(fgets($handle));
    fclose($handle);
}

echo "New Magento installation path: ";
if (!empty($argv[2])) {
    $newPath = $argv[2];
    echo $newPath . "\n";
} else {
    $handle = fopen("php://stdin","r");
    $newPath = trim(fgets($handle));
    fclose($handle);
}

$mageDiff = new MageDiff($oldPath,$newPath);
$mageDiff->compare();
