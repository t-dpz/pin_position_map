<?php
require_once __DIR__ . '/../lib.php';

pin_db()->exec(file_get_contents(__DIR__ . '/schema_demo.sql'));
