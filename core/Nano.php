<?php

namespace Nano\core;

use Exception;
use Nano\debug\NanoDebug;
use Nano\helpers\File;
use Nano\helpers\FileHelpers;
use Nano\renderers\AbstractRenderer;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\SimpleRouter;

class Nano
{
	// ------------------------------------------------------------------------- TRAITS

	use Nano_base;
	use Nano_envs;
	use Nano_data;
	use Nano_routes;
	use Nano_sessions;
	use Nano_controllers;
	use Nano_errors;
	use Nano_locale;

	// ------------------------------------------------------------------------- RENDERER

	public static AbstractRenderer $renderer;

	// ------------------------------------------------------------------------- INIT
	// Implementation status : 100%

	// Root path computed with __DIR__ from index.php
	protected static string $__rootPath;

	/**
	 * Init nano app. Use only from index.php at web root.
	 * @param string $rootPath Must be __DIR__ from index.php at web root.
	 * @param array $controllerDirectories
	 * @param string $controllersSuffix
	 * @param string $appDataDirectory
	 * @throws Exception
	 */
	static function init ( string $rootPath, array $controllerDirectories = ['app/controllers'], string $controllersSuffix = "NanoController", string $appDataDirectory = 'app/data' ) {
		NanoDebug::profile("App", true);
		if ( isset(Nano::$__rootPath) )
			throw new Exception("Nano::init // Cannot re-init Nano app.");
		// Store root path without trailing slash, and store controller directories
		Nano::$__rootPath = rtrim($rootPath, "/");
		Nano::$__controllerDirectories = $controllerDirectories;
		Nano::$__controllersSuffix = $controllersSuffix;
		Nano::$__appDataDirectory = $appDataDirectory;
		// Init session before anything else
		session_cache_limiter(false);
		session_start();
		// After init action
		Nano::action("App", "afterInit", [], false);
	}

	/**
	 * Start and execute matching route.
	 */
	static function start () {
		// Set base from env
		if ( !is_null(Nano::getEnv('NANO_BASE')) )
			Nano::setBase( Nano::getEnv('NANO_BASE') );
		// Init debugger
		if ( Nano::getEnv("NANO_DEBUG") )
			NanoDebug::init();
		// Browse all before route handlers and pass returned value to the next handler
		$beforeStartProfile = NanoDebug::profile("Before start");
		$beforeRouteArgument = null;
		foreach ( self::$__beforeRouteHandlers as $handler )
			$beforeRouteArgument = $handler( $beforeRouteArgument );
		// Expose returned string
		if ( is_string($beforeRouteArgument) ) {
			echo $beforeRouteArgument;
			exit;
		}
		// Before start action
		Nano::action("App", "beforeStart", [ $beforeRouteArgument ], false );
		$beforeStartProfile();
		// Start router and execute route handler
		NanoDebug::profile("Responder");
		try {
			// Execute matching routes
			$response = SimpleRouter::router()->start();
			// Return string as response
			if ( is_string($response) )
				print $response;
			// Not found
			else if ( is_null($response) )
				throw new NotFoundHttpException("Route not found", 404);
		}
		catch ( Exception $error ) {
			// Call caught error
			if ( self::errorHandler( $error ) ) return;
			// If error isn't processable, show it
			if ( self::getEnv("NANO_DEBUG") )
				dump($error); // TODO : Do better :) Use Whoops package ?
		}
	}

	// TODO : DOC
	protected static array $__beforeRouteHandlers = [];
	static function onBeforeRoute ( callable $handler ) {
		self::$__beforeRouteHandlers[] = $handler;
	}


	/**
	 * Load responders from /app/responders from app root by default.
	 * Responders are php files, loaded once.
	 * Use prefixed numbers as prefix to load in correct order.
	 * Ex :
	 * - 00.admin.responder.php
	 * - 01.front.responder.php
	 */
	static function loadResponders ( string $path = "app/responders" ) {
		$profileStop = NanoDebug::profile("Loading responders");
		$responderFiles = FileHelpers::listFolder( Nano::path($path) );
		/** @var File $file */
		foreach ( $responderFiles as $file ) {
			// Load only PHP files
			if (!str_ends_with($file->path, ".php")) continue;
			require_once( $file->path );
		}
		$profileStop();
	}
}
