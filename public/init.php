<?php

$rootPath = realpath( __DIR__.'/..' );

// ----------------------------------------------------------------------------- VENDORS

// Load vendors
require_once $rootPath.'/vendor/autoload.php';

use Nano\core\App;
use Nano\core\Env;
use Nano\debug\Debug;
use Nano\templates\TemplateRenderer;
use Nano\helpers\Cache;

// ----------------------------------------------------------------------------- PATHS CONFIG
// Mandatory step 1 - Init application paths

// Compute path to project root relative to index.php.
// Where are composer.json, .env, vendor ...
App::$rootPath = $rootPath;

// Path to application directory, relative to index.php.
// Where project file belongs.
App::$appPath = realpath( $rootPath.'/app' );

// Path to project's generated data directory.
// This directory is outside public to disable access to sqlite file.
// Generated files like uploads have to be here and linked with a docker volume
// to be also accessible in public directory.
App::$dataPath = realpath( $rootPath.'/data' );

// Path to public directory
App::$publicPath = realpath( $rootPath.'/public' );

// Paths to exclude from app imports
App::$appExcludePath = [ "10.views", "20.emails" ];

// ----------------------------------------------------------------------------- ENVS
// Mandatory step 2 - Load envs

// Load the environment variables from dot env file
Env::loadDotEnvFile( $rootPath );

// Try to load email.env file
Env::loadDotEnvFile( $rootPath, 'email.env', false );


// ----------------------------------------------------------------------------- HTTP
// Mandatory step 3 - Init HTTP config

App::initHTTP();

// ----------------------------------------------------------------------------- PROJECT

Cache::init("apcu");
Debug::init();
TemplateRenderer::init( App::$appPath.'/10.views', App::$dataPath.'/twig/cache' );



// fixme : change this
// Define Wordpress path
//define('NANO_WP_PATH', __DIR__ . '/wordpress');
