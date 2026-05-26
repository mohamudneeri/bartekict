<?php

define("CLIENTAREA", true);

require __DIR__ . '/init.php';

use WHMCS\ClientArea;

$ca = new ClientArea();

$ca->setPageTitle('Domain Transfer - BARTEK ICT');
$ca->initPage();

// Tell WHMCS which template to load
$ca->setTemplate('custom_domain_transfer');

$ca->output();