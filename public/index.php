<?php

require_once __DIR__.'/init.php';

use Nano\core\App;

// -----------------------------------------------------------------------------

// Load application
App::load();

// Execute matching API endpoint and catch 404s
App::run();
