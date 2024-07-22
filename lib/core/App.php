<?php

namespace Nano\core;

use Exception;
use Nano\debug\Debug;
use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
use Pecee\Http\Request;
use Pecee\SimpleRouter\Event\EventArgument;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\Handlers\EventHandler;
use Pecee\SimpleRouter\Route\IGroupRoute;
use Pecee\SimpleRouter\Route\ILoadableRoute;
use Pecee\SimpleRouter\SimpleRouter;


class App
{
	// ------------------------------------------------------------------------- PATH CONFIG

	public static string $rootPath = "";
	public static string $appPath = "";
	public static string $dataPath = "";
	public static string $publicPath = "";

	public static array $appExcludePath = [];


	// ------------------------------------------------------------------------- HTTP ENV

	protected static string $__scheme;
	protected static string $__host;
	protected static string $__base;
	protected static string $__clientIP;

	public static function getScheme () {
		return self::$__scheme;
	}
	public static function getHost () {
		return self::$__host;
	}
	public static function getBase () {
		return self::$__base;
	}
	public static function getClientIP () {
		return self::$__clientIP;
	}

	public static function getAbsolutePath ( string $subPath = "" ) {
		return self::getScheme().'://'.self::getHost().rtrim(self::getBase(), '/').'/'.ltrim($subPath, '/');
	}

	public static function initHTTP ( string $base = null ) {
		if ( isset(self::$__base) )
			throw new Exception("App::initHTTPEnv // Cannot init http env twice.");
		// --- BASE
		if ( !is_null($base) && Env::exists('NANO_APP_BASE') )
			throw new Exception("App::initHTTPEnv // Cannot set base ($base) from argument if NANO_APP_BASE is defined from envs.");
		self::$__base = Env::exists('NANO_APP_BASE') ? Env::get('NANO_APP_BASE') : ($base ?? '/');
		if ( !str_starts_with(self::$__base, '/') )
			throw new Exception("App::initHTTPEnv // Base ($base) have to start with '/'");
		// Prepend base to router
		if ( self::$__base !== "/" ) {
			$eventHandler = new EventHandler();
			$eventHandler->register(EventHandler::EVENT_ADD_ROUTE, function( EventArgument $event ) {
				$base = self::$__base;
				$route = $event->route;
				// Skip routes added by group as these will inherit the url
				if ( !$event->isSubRoute ) return;
				if ( $route instanceof ILoadableRoute )
					$route->prependUrl( $base );
				else if ( $route instanceof IGroupRoute )
					$route->prependPrefix( $base );
			});
			SimpleRouter::addEventHandler( $eventHandler );
		}
		// --- SCHEME
		if ( Env::exists("NANO_APP_SCHEME") )
			self::$__scheme = NANO_APP_SCHEME;
		else if ( isset($_SERVER['https']) && $_SERVER['https'] == 'on' )
			self::$__scheme = 'https';
		else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' )
			self::$__scheme = 'https';
		else if ( isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443' )
			self::$__scheme = 'https';
		else if ( isset($_SERVER['REQUEST_SCHEME']) )
			self::$__scheme = $_SERVER['REQUEST_SCHEME'];
		else
			self::$__scheme = 'http';
		// Override server https value
		$_SERVER['HTTPS'] = self::$__scheme ? 'on' : 'off';
		// --- HOST
		if ( Env::exists("NANO_APP_HOST") )
			self::$__host = NANO_APP_HOST;
		else if ( isset($_SERVER['HTTP_X_FORWARDED_HOST']) )
			self::$__host = $_SERVER['HTTP_X_FORWARDED_HOST'];
		else if ( isset($_SERVER['HTTP_HOST']) )
			self::$__host = $_SERVER['HTTP_HOST'];
		else if ( isset($_SERVER['SERVER_NAME']) )
			self::$__host = $_SERVER['SERVER_NAME'];
		else
			self::$__host = gethostname();
		// --- CLIENT IP
		if ( isset($_SERVER['HTTP_X_REAL_IP']) )
			self::$__clientIP = $_SERVER['HTTP_X_REAL_IP'];
		else if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) )
			self::$__clientIP = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
		else if ( isset($_SERVER['REMOTE_ADDR']) )
			self::$__clientIP = '0.0.0.0';
	}

	// ------------------------------------------------------------------------- LOAD APP FILES

	public static function load () {
		$profile = Debug::profile("Loading application functions", true);
		Loader::loadFunctions( self::$appPath, self::$appExcludePath );
		$profile();
	}

	// ------------------------------------------------------------------------- ERRORS

	public static $__notFoundPrefixAndHandlers = [];
	public static $__internalErrorHandlers = [];

	protected static function dispatchError ( string $type, Exception $error ) {
		$request = SimpleRouter::request();
		$path = $request->getUrl()->getPath();
		if ( $type === "not-found" ) {
			// Browser handlers and their prefixes
			$defaultHandler = null;
			foreach ( self::$__notFoundPrefixAndHandlers as $prefixAndHandler ) {
				// If this is the default prefix,
				// store the associated handler to call it if no specific handler has been found
				if ( $prefixAndHandler[0] === "/" ) {
					$defaultHandler = $prefixAndHandler[1];
				}
				// Check if this non-default prefix matches the path
				else if ( str_starts_with($path, $prefixAndHandler[0]) ) {
					// Call this specific handler and cancel the default one
					$defaultHandler = null;
					$prefixAndHandler[1]( $request, $error );
				}
			}
			// Call the default handler if we didn't find any specific one before
			if ( !is_null($defaultHandler) )
				$defaultHandler( $request, $error );
		}
		// Other errors
		else
			array_map( fn ($f) => $f( $type, $request, $error ), self::$__internalErrorHandlers );
	}

	/**
	 * @param callable(Request $request, Exception $error): void $callback
	 * @return void
	 */
	public static function onNotFound ( string $prefix, Callable $callback ) {
		self::$__notFoundPrefixAndHandlers[] = [$prefix, $callback];
	}

	/**
	 * @param callable(string $type, Request $request, Exception $error): void $callback
	 * @return void
	 */
	public static function onInternalError ( Callable $callback ) {
		self::$__internalErrorHandlers[] = $callback;
	}


	// ------------------------------------------------------------------------- RUN

	public static function run () {
//		SimpleRouter::error( function ( Request $request, Exception $error ) {
//			$type = $error->getCode() === 404 ? "not-found" : "error";
//			self::dispatchError($type, $error);
//		});
		$profile = Debug::profile("Responder");
		try {
			SimpleRouter::start();
		} catch ( NotFoundHttpException $error ) {
			self::dispatchError("not-found", $error);
		} catch ( TokenMismatchException $error ) {
			self::dispatchError("token-mismatch", $error);
		} catch ( HttpException $error ) {
			self::dispatchError("http", $error);
		} catch ( Exception $error ) {
			self::dispatchError("unknown", $error);
		}
		finally {
			$profile();
		}
	}

	// -------------------------------------------------------------------------


	const ROUTE_PARAMETER_WITH_SLASHES = [ 'defaultParameterRegex' => '[\w\-\/\.]+' ];

	static function json ( mixed $data, int $status = 200, $options = 0, $depth = 512, callable|null $then = null )
	{
		SimpleRouter::response()->httpCode( $status );
		SimpleRouter::response()->header( 'Content-Type: application/json; charset=utf-8' );
		print json_encode( $data, $options, $depth );
		// Then handler is for action continuing after the request,
		// that does not concern the end user.
		// The callback is called with error routed to stderr.
		if ( !is_null($then) ) {
			// Disable front error reporting
			error_reporting(0);
			@ini_set('display_errors', 0);
			// Flush data to the client and close connexion
			flush();
			if ( function_exists('fastcgi_finish_request') )
				fastcgi_finish_request();
			// Execute the then callback and catch errors
			try {
				$then();
			} catch ( Exception $error ) {
				// Show errors in stderr
				Debug::consoleDump("API::json // \$then callback error caught.");
				Debug::consoleDump( "#".$error->getCode()." -> ".$error->getFile().":".$error->getLine() );
				Debug::consoleDump( $error->getMessage() );
				Debug::consoleDump( $error->getTraceAsString() );
			}
		}
		exit;
	}

	static function jsonThen ( mixed $data, callable|null $callback = null, int $status = 200 )
	{
		self::json( $data, $status, JSON_NUMERIC_CHECK, 512, $callback );
	}

	static function jsonError ( int $code, string $status, array $data = [], Exception $error = null, callable $then = null )
	{
		$output = [
			'code'   => $code,
			'status' => $status,
			...$data
		];
		if ( !is_null( $error ) ) {
			$output[ 'error' ] = [
				"code"    => $error->getCode(),
				"message" => $error->getMessage(),
			];
		}
		self::jsonThen( $output, $then, $code );
	}

	static function jsonNotFound () {
		self::jsonError( 404, 'not found' );
	}

	static function input () {
		$request = SimpleRouter::request();
		return $request->getInputHandler();
	}

	static function redirect ( string $url, int $code = 302 ) {
		header( 'Location: ' . $url, true, $code );
	}

	// -------------------------------------------------------------------------

	/**
	 * Get client 2 chars locale code from http headers ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
	 * @param array $allowedLocales List of allowed locales ( 2 chars, lower ) -> ["en", "fr"]
	 * @param string|null $defaultLocale Default locale -> "en". If null, will use first of allowed locales list.
	 * @return string
	 */
	static function getClientLocale ( array $allowedLocales, string $defaultLocale = null ) {
		$defaultLocale ??= $allowedLocales[0];
		$locale = $defaultLocale;
		if ( !isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) )
			return $locale;
		// Get user locale
		$browserLocale = strtolower( substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) );
		if ( in_array($browserLocale, $allowedLocales) )
			$locale = $browserLocale;
		return $locale;
	}

	/**
	 * Verify if a locale is valid, otherwise redirect to client's locale.
	 * @param string $locale Locale to check.
	 * @param array $allowedLocales List of allowed locales ( 2 chars, lower ) -> ["en", "fr"]
	 * @param string|null $defaultLocale Default locale -> "en". If null, will use first of allowed locales list.
	 * @return void
	 */
	static function verifyLocaleAndRedirect ( string $locale, array $allowedLocales, string $defaultLocale = null ) {
		if ( empty($locale) || !in_array($locale, $allowedLocales) ) {
			$userLocale = App::getClientLocale( $allowedLocales, $defaultLocale );
			App::redirect("/$userLocale/");
			exit;
		}
	}
}
