<?php

namespace Nano\core;

use Exception;
use Nano\debug\Debug;
use Pecee\Http\Input\InputHandler;
use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
use Pecee\Http\Request;
use Pecee\Http\Response;
use Pecee\SimpleRouter\Event\EventArgument;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\Handlers\EventHandler;
use Pecee\SimpleRouter\Route\IGroupRoute;
use Pecee\SimpleRouter\Route\ILoadableRoute;
use Pecee\SimpleRouter\Router;
use Pecee\SimpleRouter\SimpleRouter;


class App
{
	// --------------------------------------------------------------------------- PATH CONFIG

	public static string $rootPath = "";
	public static string $appPath = "";
	public static string $dataPath = "";
	public static string $publicPath = "";

	public static array $appExcludePath = [];

	// --------------------------------------------------------------------------- HTTP ENV

	protected static string $__scheme;
	protected static string $__host;
	protected static string $__base;
	protected static string $__clientIP;

	public static function getScheme (): string {
		return self::$__scheme;
	}
	public static function getHost (): string {
		return self::$__host;
	}
	public static function getBase (): string {
		return self::$__base;
	}
	public static function getClientIP (): string {
		return self::$__clientIP;
	}

	public static function getAbsolutePath ( string $subPath = "" ): string {
		return self::getScheme().'://'.self::getHost().rtrim(self::getBase(), '/').'/'.ltrim($subPath, '/');
	}

	public static function initHTTP ( ?string $base = null, bool $unstablePrependBaseToRoutes = false ): void {
		if ( isset(self::$__base) )
			throw new Exception("App::initHTTPEnv // Cannot init http env twice.");
		// --- BASE
		if ( !is_null($base) && Env::exists('NANO_APP_BASE') )
			throw new Exception("App::initHTTPEnv // Cannot set base ($base) from argument if NANO_APP_BASE is defined from envs.");
		self::$__base = Env::exists('NANO_APP_BASE') ? Env::get('NANO_APP_BASE') : ($base ?? '/');
		if ( !str_starts_with(self::$__base, '/') )
			throw new Exception("App::initHTTPEnv // Base ($base) have to start with '/'");
		// Prepend base to router
		if ( self::$__base !== "/" && $unstablePrependBaseToRoutes) {
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
			self::$__scheme = Env::get("NANO_APP_SCHEME");
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
			self::$__host = Env::get("NANO_APP_HOST");
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

	// --------------------------------------------------------------------------- LOAD APP FILES

	public static function load (): void {
		$profile = Debug::profile("Loading application functions", true);
		Loader::loadFunctions( self::$appPath, self::$appExcludePath );
		$profile();
	}

	// --------------------------------------------------------------------------- ERRORS

	public static array $__notFoundPrefixAndHandlers = [];
	public static array $__internalErrorHandlers = [];

	protected static function dispatchError ( string $type, Exception $error ): void {
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

	static function dispatchNotFound ( ?Exception $error = null ): void {
		if ( is_null($error) )
			$error = new Exception("not-found", 404);
		self::dispatchError("not-found", $error);
	}

	/**
	 * @param callable(Request $request, Exception $error): void $callback
	 * @return void
	 */
	public static function onNotFound ( string $prefix, Callable $callback ): void {
		self::$__notFoundPrefixAndHandlers[] = [$prefix, $callback];
	}

	/**
	 * @param callable(string $type, Request $request, Exception $error): void $callback
	 * @return void
	 */
	public static function onInternalError ( Callable $callback ): void {
		self::$__internalErrorHandlers[] = $callback;
	}

	// --------------------------------------------------------------------------- RUN

	public static function run (): void {
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

	// --------------------------------------------------------------------------- RESPONSE

	const array ROUTE_PARAMETER_WITH_SLASHES = [ 'defaultParameterRegex' => '[\w\-\/\.]+' ];

	protected static array $__injectedProfile;

	static function injectProfileInJSON ( string $jsonKey = "__profile", string $log = "" ): void {
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

	static function json ( mixed $data, int $status = 200, $options = 0, $depth = 512, ?callable $then = null ): void {
		if ( is_array($data) && isset(self::$__injectedProfile) )
			$data = [ ...$data, ...self::$__injectedProfile];
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

	static function jsonThen ( mixed $data, ?callable $callback = null, int $status = 200 ): void {
		self::json( $data, $status, JSON_NUMERIC_CHECK, 512, $callback );
	}

	static function jsonError ( int $code, string $status, array $data = [], ?Exception $error = null, ?callable $then = null ): void {
		$output = [
			'code'   => $code,
			'status' => $status,
			...$data
		];
		if ( !is_null( $error ) ) {
			$output["error"] = [
				"code"    => $error->getCode(),
				"message" => $error->getMessage(),
			];
		}
		self::jsonThen( $output, $then, $code );
	}

	static function redirect ( string $url, int $code = 302 ): void {
		header( 'Location: ' . $url, true, $code );
	}

	static function text ( string|array $lines, int $code = 200, ?Response $response = null, string $contentType = "text/plain" ): void {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->header("Content-Type: $contentType; charset=utf-8");
		print (is_array($lines) ? implode("\n", $lines) : $lines);
	}

	static function xml ( string|array $lines, int $code = 200, ?Response $response = null, string $contentType = "application/xml" ): void {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->header("Content-Type: $contentType; charset=utf-8");
		print (is_array($lines) ? implode("\n", $lines) : $lines);
	}

	// --------------------------------------------------------------------------- LOG

	/**
	 * Print in console to stdout
	 * @param mixed $data Will be json_encoded if not a string
	 * @return void
	 */
	static function stdout ( mixed $data ): void {
		if ( !is_string( $data ) )
			$data = json_encode($data);
		fwrite(fopen('php://stdout', 'w'), $data);
	}

	/**
	 * Print in console to stderr
	 * @param mixed $data Will be json_encoded if not a string
	 * @return void
	 */
	static function stderr ( mixed $data ): void {
		if ( !is_string( $data ) )
			$data = json_encode($data);
		error_log(json_encode($data));
	}

	// --------------------------------------------------------------------------- ROBOTS / SITEMAP

	/**
	 * Print robots.txt.
	 * Will read NANO_ROBOTS constant :
	 * - "public" 	-> allow all, show sitemap
	 * - "private"	-> disallow all, hide sitemap
	 * @param array $allow List of URL to allow
	 * @param array $disallow List of URL to disallow
	 * @param string $sitemap Print sitemap path
	 * @return void
	 */
	static function printRobots ( array $allow = [], array $disallow = [], string $sitemap = 'sitemap.xml' ): void {
		$lines = [ "User-agent: *" ];
    $envRobots = Env::get('NANO_ROBOTS', "");
    if ( $envRobots === "public" ) {
      $allow = ["*", ...$allow];
      $disallow = ["", ...$disallow];
    } else if ( $envRobots === "private" ) {
      $disallow = ["/", ...$disallow];
      $sitemap = "";
    }
		foreach ( $allow as $a )
			$lines[] = 'Allow: '.$a;
		foreach ( $disallow as $d )
			$lines[] = 'Disallow: '.$d;
		if ( !empty($sitemap) ) {
      $lines[] = "";
			$lines[] = "Sitemap: ".App::getAbsolutePath($sitemap);
    }
		App::text( $lines );
	}

	/**
	 * Print a sitemap.xml that point to other sitemap.xml endpoints.
	 * Useful for multi-lang websites.
	 * @param array $sitemaps List of sitemap URLs
	 * @return void
	 */
	static function printSitemapRedirect ( array $sitemaps ): void {
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

	/**
	 * Print a sitemap.xml from page lists.
	 * Page array structure :
	 * - href 			-> Complete page href
	 * - priority		?> From 0.0 to 1.0
	 * - lastmod  	?> Last modification timestamp
	 * - frequency	?> Update frequency "always" / "hourly" / "daily" / "weekly" / "monthly" / "yearly" / "never"
	 * @param array $pages List of page, all in arrays
	 * @return void
	 */
	static function printSitemapPages ( array $pages ): void {
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

	// --------------------------------------------------------------------------- SIMPLE ROUTER

  public static function getRouter () : Router {
    return SimpleRouter::router();
  }

  public static function getRouterRequest () : Request {
    return SimpleRouter::request();
  }

  public static function getRouterInput () : InputHandler {
    return self::getRouterRequest()->getInputHandler();
  }

  public static function getCurrentURL () : \Pecee\Http\Url {
    return self::getRouter()->getUrl();
  }

	// --------------------------------------------------------------------------- LOCALE

  protected static array $__locales = [];
  public static function getLocales () : array { return self::$__locales; }

  /**
   * Set locales with format ["en" => "English", "fr" => "Français"]
   * @param array $locales
   * @return void
   */
  public static function setLocales ( array $locales ): void {
    self::$__locales = $locales;
  }

  /**
   * Get client 2 chars locale code from http headers ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
   * And from ?locale get parameters.
   *
   * @param array|null $allowedLocales List of allowed locales ( 2 chars, lower ) -> ["en", "fr"], will use app locales keys if null.
   * @param string|null $defaultLocale If null, will use first of allowed locales list.
   *
   * @see self::setLocales()
   * @see self::getLocales()
   *
   * @return string
   */
	static function getClientLocale ( ?array $allowedLocales = null, ?string $defaultLocale = null ): string {
    if ( is_null($defaultLocale) )
      $allowedLocales = array_keys(self::getLocales());
    // Locale from get parameters
    $getLocale = self::getRouterInput()->get("locale", "");
    if ( !empty($getLocale) && in_array($getLocale, $allowedLocales) )
      return $getLocale;
    // Default locale if not available in browser header
		$defaultLocale ??= $allowedLocales[0];
		$locale = $defaultLocale;
		if ( !isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) )
			return $locale;
		// Get user locale from browser header
		$browserLocale = strtolower( substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) );
		if ( in_array($browserLocale, $allowedLocales) )
			$locale = $browserLocale;
		return $locale;
	}

	/**
	 * Verify if a locale is valid, otherwise redirect to client's locale.
	 *
	 * @param string $urlLocale Locale to check.
	 * @param array|null $allowedLocales List of allowed locales ( 2 chars, lower ) -> ["en", "fr"]
	 * @param string|null $defaultLocale Default locale -> "en". If null, will use first of allowed locales list.
	 *
	 * @return void
	 */
	static function verifyLocaleAndRedirect ( string $urlLocale, ?array $allowedLocales = null, ?string $defaultLocale = null ) : void {
    // Default parameters from locale config
    $allowedLocales ??= array_keys(self::getLocales());
    $defaultLocale ??= array_keys(self::getLocales())[0];
    // User locale is valid
		if ( !empty( $urlLocale) && in_array( $urlLocale, $allowedLocales) )
      return;
    // Get user locale from request
    $userLocale = App::getClientLocale( $allowedLocales, $defaultLocale );
    // Extract path parts
    $path = self::getCurrentURL()->getPath();
    $pathParts = explode("/", $path, 3);
    // Check if first part looks like a locale to include or exclude it from redirection
    if ( strlen($pathParts[1] ?? "") === 2 )
      $path = strtolower($pathParts[2] ?? "");
    $path = ltrim($path, "/");
    $redirect = "/$userLocale/$path";
    App::redirect($redirect);
    exit;
	}
}
