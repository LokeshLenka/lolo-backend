<?php


$year = date('y');
printf($year);
$middle = '0707';

$lastUser = 0001;

if ($lastUser) {
    $lastSequence = (int)substr($lastUser, -4);
    $nextSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
} else {
    $nextSequence = '0001';
}

// return "{$year}{$middle}{$nextSequence}";

$username = "{$year}{$middle}{$nextSequence}";
echo $username;
