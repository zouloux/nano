<?php

namespace Nano\core;

use Exception;
use Nano\debug\Debug;

class Loader
{
	/**
	 * Autoload php files in a directory.
	 * Is recursive.
	 * Will load files and directories in ascendant alphanumeric order.
	 * Name your files like so :
	 * - 00.my.first.file.php
	 * - 01.loaded.after.php
	 * - 02.you.got.it.php
	 * Can also start at 01.
	 * Will skip files and directories with name starting with an underscore. ex :
	 * - _skipped.php
	 * Will skip directories containing a .nanoexclude file
	 */
	public static function loadFunctions ( string $directory, array $exclude = [] ): void {
		$files = scandir( $directory );
		foreach ( $files as $file ) {
			if ( $file == '.' || $file == '..' )
				continue;
			if ( stripos( $file, '_' ) === 0 )
				continue;
			if ( in_array($file, $exclude) )
				continue;
			if ( file_exists($directory . '/.nanoexclude') )
				continue;
			$path = $directory.'/'.$file;
			// Recursively load directories
			if ( is_dir( $path ) ) {
				self::loadFunctions( $path, $exclude );
				continue;
			}
			// Target file
			$filePath = $directory.'/'.$file;
			$info = pathinfo( $filePath );
			// Only load PHP obviously
			if ( $info[ 'extension' ] === "php" )
				require_once( $filePath );
		}
	}

	// --------------------------------------------------------------------------- WORDPRESS

	protected static bool $__wordpressLoaded = false;

	/**
	 * Load Wordpress into Nano application.
	 * Use only if you need to access any Wordpress resource or Class.
	 * @return bool
	 * @throws Exception
	 */
	public static function loadWordpress (): bool {
		// Start only once, silently fail if already started
		if ( self::$__wordpressLoaded )
			return false;
		self::$__wordpressLoaded = true;
		// Need wordpress path
		if ( !defined('NANO_WORDPRESS_PATH') )
			throw new Exception("Loader::loadWordpress // NANO_WORDPRESS_PATH not defined");
		if ( !defined('WP_USE_THEMES') )
			define( 'WP_USE_THEMES', false );
		$profile = Debug::profile("Loading wordpress");
		require_once NANO_WORDPRESS_PATH.'/wp-load.php';
		$profile();
		return true;
	}

	protected static bool $__wordpressStarted = false;

	/**
	 * Start Wordpress and its theme rendering.
	 * Cannot start Wordpress if already loaded.
	 * @return bool
	 * @throws Exception
	 */
	public static function startWordpress () {
		if ( self::$__wordpressLoaded )
			throw new Exception("Loader::startWordpress // Cannot start wordpress if it was already loaded.");
		// Start only once, silently fail if already started
		if ( self::$__wordpressStarted )
			return false;
		self::$__wordpressStarted = true;
		// Need wordpress path
		if ( !defined('NANO_WORDPRESS_PATH') )
			throw new Exception("Loader::startWordpress // NANO_WORDPRESS_PATH not defined");
		if ( !defined('WP_USE_THEMES') )
			define( 'WP_USE_THEMES', false );
		$profile = Debug::profile("Starting wordpress");
		require_once NANO_WORDPRESS_PATH.'/index.php';
		$profile();
		return true;
	}

	/**
	 * Will proxy all SimpleRouter requests to Wordpress REST API.
	 * Usage : SimpleRouter::all("/base/wp-json/{path}", function ( $path ) {
	 * 	Loader::proxyWordpressWPJson( $path );
	 * }, App::ROUTE_PARAMETER_WITH_SLASHES)
	 * Use along bare_fields_feature_global_move_wp_json_origin if using WPS Bare Fields.
	 * @param string $path
	 * @return void
	 * @throws Exception
	 */
	public static function proxyWordpressWPJson ( string $path ) {

		self::loadWordpress();

		$method = strtoupper( App::getRouterRequest()->getMethod() );
		$fullPath = '/'.$path;
		$bodyParameters = App::getRouterInput()->all();
		$headers = getallheaders();

		$request = new \WP_REST_Request( $method, $fullPath );
		$request->set_body_params( $bodyParameters );
		$request->set_query_params( $_GET );
		$request->set_headers( $headers );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			$error = $response->as_error();
			$errorData = $error->get_error_data();
			App::stderr( $errorData );
			$data = [ 'message' => $error->get_error_message() ];
			App::json($data, $response->status ?? 500);
		}

		App::json( $response->get_data() );
	}
}
