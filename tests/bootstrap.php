<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

echo sprintf("Tests started in temp %s.\n", sys_get_temp_dir());

$loader->add('Propel\Tests', __DIR__ . '/../vendor/propel/propel/tests/');