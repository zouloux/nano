<?php


// Init envs
use Nano\core\App;
use Nano\debug\Debug;

require_once __DIR__.'/init.php';

// Start application profiling
$profile = Debug::profile("App", true);

// Load application
App::load();

App::onNotFound(function ($path) {
	if ( str_starts_with($path, "/api/") ) {
		App::jsonNotFound();
	}
	else {
		dump("Not found template");
	}
});

// Execute matching API endpoint and catch 404s
App::run();
$profile();