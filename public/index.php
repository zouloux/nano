<?php

require_once __DIR__.'/init.php';

use Nano\core\App;
use Nano\debug\Debug;

// -----------------------------------------------------------------------------

// Start application profiling
$profile = Debug::profile("App", true);

// Load application
App::load();

// Execute matching API endpoint and catch 404s
App::run();
$profile();