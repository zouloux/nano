<?php

namespace Nano\core;

use Nano\helpers\FileHelpers;

trait Nano_cache {

	// ------------------------------------------------------------------------- CACHE
	// Implementation status : 90%
	// Doc status : 90%

	// Can be "auto" in dot env to be defined automatically
	// "apcu" | "file" | "none"
	protected static string $__cacheMethod = "none";

	// File path to cache directory for file cache system
	protected static string $__cachePath;

	/**
	 * INTERNAL
	 * Init cache system from envs
	 */
	protected static function cacheInit () {
		// Read cache method from dot env
		self::$__cacheMethod = strtolower( Nano::getEnv("NANO_CACHE_METHOD", "auto") );
		// Legacy support for disable cache feature
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) || self::$__cacheMethod === "none" )
			self::$__cacheMethod = "none";
		// Auto mode, we check if apcu is available, otherwise we select file cache
		else if ( self::$__cacheMethod === "auto" )
			self::$__cacheMethod = function_exists("apcu_fetch") ? "apcu" : "file";
		// Init cache directory for file cache
		else if ( self::$__cacheMethod === "file" ) {
			self::$__cachePath = Nano::path( "app/data/", "cache/" );
			self::cacheInitDirectory();
		}
		else if ( self::$__cacheMethod !== "apcu" ) {
			$cache = self::$__cacheMethod;
			throw new \Exception("Nano_cache::cacheInit // Invalid cache method $cache set from dot env");
		}
	}

	protected static string $__cachePrefix = "_nano_";

	/**
	 * Set cache prefix.
	 * All cache commands will work in a different namespace.
	 * Default prefixed is namespaced to  _nano_
	 * @param $prefix
	 * @return void
	 */
	static function setCachePrefix ( $prefix ) {
		self::$__cachePrefix = $prefix."__";
	}

	/**
	 * INTERNAL
	 * Get a file cache file path from its key.
	 */
	protected static function cacheGetFilePath ( $key ) {
		return self::$__cachePath.md5($key);
	}

	/**
	 * INTERNAL
	 * Init cache directory for file cache system.
	 */
	protected static function cacheInitDirectory () {
		if ( !file_exists(self::$__cachePath) )
			mkdir( self::$__cachePath, 0777, true );
	}

	/**
	 * Clear all cache.
	 * @return bool
	 */
	static function cacheClear () {
		if ( self::$__cacheMethod === "apcu" )
			return apcu_clear_cache();
		else if ( self::$__cacheMethod === "file" ) {
			// Reset cache folder for file cache system
			if ( file_exists(self::$__cachePath) )
				FileHelpers::recursiveRemoveDirectory( self::$__cachePath );
			self::cacheInitDirectory();
			return true;
		}
		else
			return true;
	}

	/**
	 * Check if a value is stored in cache on this key
	 * @param string $key Cache key, prefixed with cachePrefix.
	 * @return boolean
	 */
	static function cacheHas ( string $key ) {
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" )
			return apcu_exists( $key );
		else if ( self::$__cacheMethod === "file" ) {
			return file_exists( self::cacheGetFilePath( $key ) );
		}
		else
			return false;
	}

	/**
	 * Retrieve a value from cache.
	 * @param string $key Cache key, prefixed with cachePrefix.
	 * @return mixed
	 */
	static function cacheGet ( string $key ) {
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" )
			return apcu_fetch( $key );
		else if ( self::$__cacheMethod === "file" ) {
			$path = self::cacheGetFilePath( $key );
			return file_exists( $path ) ? unserialize( file_get_contents( $path ) ) : null;
		}
		else
			return null;
	}

	/**
	 * Store value to current cache system.
	 * @param string $key Cache key, prefixed with cachePrefix.
	 * @param mixed $data Data to store, can be any type of value.
	 * @return bool
	 */
	static function cacheSet ( string $key, mixed $data ) {
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" )
			return !!apcu_store( $key, $data );
		else if ( self::$__cacheMethod === "file" ) {
			$path = self::cacheGetFilePath( $key );
			return file_put_contents( $path, serialize($data) ) !== false;
		}
		else
			return true;
	}

	/**
	 * Get a cached value with a handler.
	 * Will call the $getHandler until it returns a value.
	 * This value will be cached and will be returned without calling $getHandler again, until cache is cleared.
	 * @param string $key Cache key, prefixed with cachePrefix.
	 * @param callable $getHandler Function to return value to cache. Will be called until it returns a value. This value will be stored in cache.
	 * @param callable $retrieveHandler Called when value is retrieved from cache. TODO : ARGUMENTS
	 * @param boolean $disableCache Set to true to disable cache, can be useful in some cases where you want the cache to be bypassed and the handler always called.
	 * @return false|mixed
	 */
	static function cacheDefine ( $key, $getHandler, $retrieveHandler = null, $disableCache = false ) {
		// Always call handler if cache is disabled
		if ( self::$__cacheMethod === "none" || $disableCache )
			return $getHandler();
		// Store key without prefix for file cache system
		$keyNoPrefix = $key;
		$key = self::$__cachePrefix.$key;
		// Get file path for file cache system
		$path = self::cacheGetFilePath( $key );
		// Check existence in cache for apcu or file
		$isAPCU = self::$__cacheMethod === "apcu";
		if ( $isAPCU ? apcu_exists( $key ) : file_exists( $path ) ) {
			// Call retrieve handler
			// FIXME : What about the arguments here ?
			if ( !is_null($retrieveHandler) )
				$retrieveHandler();
			// Get value from cache
			$fetched = $isAPCU ? apcu_fetch( $key ) : Nano::cacheGet( $keyNoPrefix );
			// Return value if not null, otherwise, keep going to call get handler
			if ( !is_null($fetched) )
				return $fetched;
		}
		// Get value from handler because cache failed
		$result = $getHandler();
		// If we have any value, store it in cache
		if ( !is_null($result) ) {
			$isAPCU
			? apcu_store( $key, $result )
			: Nano::cacheSet( $keyNoPrefix, $result );
		}
		return $result;
	}
}
