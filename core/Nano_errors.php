<?php
namespace Nano\core;

use Exception;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\SimpleRouter;

trait Nano_errors
{
	protected static $__errorHandlers = [];

	/**
	 * Add an handler to raised fatal errors.
	 * @param int|null $code Filter error by code. Set to null to catch all errors.
	 * @param callable $function
	 * @return void
	 */
	static function onUncaughtError ( int|null $code, callable $function ) {
		self::$__errorHandlers[] = [ $code, $function ];
	}

	/**
	 * Add an handler for 404.
	 */
	static function onNotFound ( callable $function ) {
		self::$__errorHandlers[] = [ "not-found", $function ];
	}

	static function errorHandler ( Exception $error ) {
		$hadHandler = false;
		// Not found errors
		$errorCode = $error instanceof NotFoundHttpException ? 404 : $error->getCode();
		if ( $error instanceof NotFoundHttpException ) {
			SimpleRouter::response()->httpCode( 404 );
			foreach ( self::$__errorHandlers as $handler ) {
				if ( $handler[0] != "not-found" ) continue;
				$hadHandler = true;
				echo $handler[1]();
			}
		}
		// Fatal errors
		else {
			foreach ( self::$__errorHandlers as $handler ) {
				if ( !is_null($handler[0]) && $handler[0] != $errorCode ) continue;
				$hadHandler = true;
				$handler[1]( $error );
			}
		}
		return $hadHandler;
	}
}