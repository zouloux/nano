<?php

namespace Nano\core;

use Exception;
use Pecee\SimpleRouter\Event\EventArgument;
use Pecee\SimpleRouter\Handlers\EventHandler;
use Pecee\SimpleRouter\Route\IGroupRoute;
use Pecee\SimpleRouter\Route\ILoadableRoute;
use Pecee\SimpleRouter\SimpleRouter;

trait Nano_base {
	// ------------------------------------------------------------------------- BASE
	// Implementation status : 90%
	// TODO : Doc
	// TODO : Test base with router and reverse routing

	protected static string $__base = "/";

	static function getBase () {
		return Nano::$__base;
	}

	static function setBase ( string $base ) {
		Nano::$__base = ltrim(rtrim($base, "/"), "/")."/";
		Nano::initBaseHandler();
	}

	protected static EventHandler $__eventHandler;

	static protected function initBaseHandler () {
		// Init it only once we set a base
		if ( !is_null(Nano::$__eventHandler) ) return;
		Nano::$__eventHandler = new EventHandler();
		Nano::$__eventHandler->register(EventHandler::EVENT_ADD_ROUTE, function( EventArgument $event ) {
			$base = Nano::getBase();
			$route = $event->route;
			// Skip routes added by group as these will inherit the url
			if ( !$event->isSubRoute ) return;
			if ( $route instanceof ILoadableRoute )
				$route->prependUrl( $base );
			else if ( $route instanceof IGroupRoute )
				$route->prependPrefix( $base );
		});
		SimpleRouter::addEventHandler( Nano::$__eventHandler );
	}

	static function getAbsoluteHost ( string $parts = "all" ) {
		// Get host with reverse proxy compat
		if ( isset($_SERVER['HTTP_X_FORWARDED_HOST']) )
			$host = $_SERVER['HTTP_X_FORWARDED_HOST'];
		else if ( isset($_SERVER['HTTP_HOST']) )
			$host = $_SERVER['HTTP_HOST'];
		else if ( isset($_SERVER['SERVER_NAME']) )
			$host = $_SERVER['SERVER_NAME'];
		else
			$host = gethostname();
		// Get scheme with reverse proxy compat
		if ( isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == "443" )
			$scheme = 'https';
		else if ( isset($_SERVER['https']) && $_SERVER['https'] == 'on' )
			$scheme = 'https';
		else if ( isset($_SERVER['REQUEST_SCHEME']) )
			$scheme = $_SERVER['REQUEST_SCHEME'];
		else
			$scheme = 'http';
		// Return parts
		if ( $parts == "all" )
			return $scheme.'://'.$host;
		else if ( $parts == "scheme" )
			return $scheme;
		else if ( $parts == "host" )
			return $host;
		else
			throw new Exception("Nano::getAbsoluteHost // Parts can be 'all', 'scheme' or 'host'.");
	}

	static function getRequestPath ( bool $absolute = false ) {
		// TODO : Check other method fallback ?
		$path = $_SERVER['REQUEST_URI'];
		// NOTE : Can be full path sometimes
//		$path = 'https://test.com/fr/';
		if ( stripos($path, '://') !== false )
			$path = explode("/", $path, 4)[3];
		$path = '/'.ltrim($path, '/');
		if ( $absolute )
			$path = Nano::getAbsoluteHost().$path;
		return $path;
	}
}