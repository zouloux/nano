<?php

namespace Nano\core;

trait Nano_cache {

	// ------------------------------------------------------------------------- CACHE
	// Implementation status : 60%

	protected static string $__cachePrefix = "_nano_";
	static function setCachePrefix ( $prefix ) {
		self::$__cachePrefix = $prefix;
	}

	static function cacheClear () {
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) )
			return 1;
		return apcu_clear_cache();
	}

	static function cacheHas ( string $key ) {
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) )
			return false;
		$key = self::$__cachePrefix.$key;
		return apcu_exists( $key );
	}

	static function cacheGet ( string $key ) {
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) )
			return null;
		$key = self::$__cachePrefix.$key;
		return apcu_fetch( $key );
	}

	static function cacheSet ( string $key, mixed $result ) {
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) )
			return 1;
		$key = self::$__cachePrefix.$key;
		return apcu_store( $key, $result );
	}

	static function cacheDefine ( $key, $getHandler, $retrieveHandler = null, $disableCache = false ) {
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) || $disableCache )
			return $getHandler();
		$key = self::$__cachePrefix.$key;
		if ( apcu_exists( $key ) ) {
			if ( !is_null($retrieveHandler) )
				$retrieveHandler();
			$fetched = apcu_fetch( $key );
			if ( !is_null($fetched) )
				return $fetched;
		}

		$result = $getHandler();
		if ( !is_null($result) )
			apcu_store( $key, $result );

		return $result;
	}
}
