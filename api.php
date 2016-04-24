<?php

error_reporting(E_ALL); // Development
//error_reporting(E_ERROR);

require_once 'vendor/luracast/restler/vendor/restler.php';

use Luracast\Restler\Restler;

$r = new Restler();
$r->addAPIClass('Cafeteria');
$r->handle();
