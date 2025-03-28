<?php

namespace Nano\core;

use Exception;
use Nano\debug\Debug;
use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
use Pecee\Http\Request;
use Pecee\Http\Response;
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
		// https with nginx reverse proxy
		else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' )
			self::$__scheme = 'https';
		else if ( isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443' )
			self::$__scheme = 'https';
		// https with cloudfront
		else if ( isset($_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO']) && $_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO'] == 'https' )
			self::$__scheme = 'https';
		else if ( isset($_SERVER['REQUEST_SCHEME']) )
			self::$__scheme = $_SERVER['REQUEST_SCHEME'];
		else
			self::$__scheme = 'http';
		// Override server https value
		$_SERVER['HTTPS'] = self::$__scheme === 'https' ? 'on' : 'off';
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

	protected static array $__injectedProfile;

	static function injectProfileInJSON ( string $jsonKey = "__profile", string $log = "" ) {
		if ( !Env::get('NANO_PROFILE', false) )
			return;
		$profiles = Debug::profileStopAllAndGet();
		if ( !empty($log) ) {
			$p2 = [];
			foreach ( $profiles as $key => $profile )
				$p2[$key] = round(($profile[1] - $profile[0]) * 10000) / 10;
			error_log(time()." - [".$log."] - ".json_encode($p2));
		}
		self::$__injectedProfile = [ $jsonKey => $profiles ];
	}

	static function json ( mixed $data, int $status = 200, $options = 0, $depth = 512, callable|null $then = null )
	{
		if ( is_array($data) && isset(self::$__injectedProfile) ) {
			$data = [ ...$data, ...self::$__injectedProfile];
		}
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

	static function input () {
		$request = SimpleRouter::request();
		return $request->getInputHandler();
	}

	static function redirect ( string $url, int $code = 302 ) {
		header( 'Location: ' . $url, true, $code );
	}

	static function text ( string|array $lines, int $code = 200, Response $response = null, string $contentType = "text/plain" ) {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->header("Content-Type: $contentType; charset=utf-8");
		print (is_array($lines) ? implode("\n", $lines) : $lines);
	}

	static function xml ( string|array $lines, int $code = 200, Response $response = null, string $contentType = "application/xml" ) {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->header("Content-Type: $contentType; charset=utf-8");
		print (is_array($lines) ? implode("\n", $lines) : $lines);
	}

	// -------------------------------------------------------------------------

	static function printRobots ( array $allow = ['*'], array $disallow = [], string $sitemap = 'sitemap.xml' ) {
		$lines = [
			"User-agent: *"
		];
		foreach ( $allow as $a )
			$lines[] = 'Allow: '.$a;
		foreach ( $disallow as $d )
			$lines[] = 'Disallow: '.$d;
		if ( !empty($sitemap) )
			$lines[] = "Sitemap: ".App::getAbsolutePath($sitemap);
		App::text( $lines );
	}

	static function printSitemapRedirect ( array $sitemaps ) {
		$lines = [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
		];
		foreach ( $sitemaps as $sitemap ) {
			$lines[] = '<sitemap>';
			$lines[] = "<loc>$sitemap</loc>";
			$lines[] = '</sitemap>';
		}
		$lines[] = '</sitemapindex>';
		App::xml( $lines );
	}

	static function printSitemapPages ( array $pages ) {
		$lines = [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
		];
		foreach ( $pages as $page ) {
			$lines[] = '<url>';
			$lines[] = '	<loc>'.$page['href'].'</loc>';
			if ( !empty($page['priority']) )
				$lines[] = '	<priority>'.$page['priority'].'</priority>';
			if ( !empty($page['date']) )
				$lines[] = '	<lastmod>'.(date('c', $page['date'])).'</lastmod>';
			if ( !empty($page['frequency']) )
				$lines[] = '	<changefreq>'.$page['frequency'].'</changefreq>';
			$lines[] = '</url>';
		}
		$lines[] = '</urlset>';
		App::xml( $lines );
	}

	// -------------------------------------------------------------------------

	static function notFound ( Exception $error = null ) {
		if ( is_null($error) )
			$error = new Exception("not-found", 404);
		self::dispatchError("not-found", $error);
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
