<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CentralBank\Application;
use CentralBank\Database;

// Initialize database
$database = new Database(__DIR__ . '/../data/banks.db');

// Initialize application
$app = new Application($database);

// Handle request
$app->handle();