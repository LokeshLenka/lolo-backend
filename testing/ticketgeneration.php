<?php

use Illuminate\Support\Str;

$ticketCode = 'LOLO-' . strtoupper(Str::uuid()->toString()) . '-' . now()->format('dmY');

echo $ticketCode;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <?php echo $ticketCode ?>
</body>

</html>
