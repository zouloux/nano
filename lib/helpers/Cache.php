<?php

namespace Nano\helpers;

use Exception;
use Nano\core\Env;

class Cache {

	// Can be "auto" in dot env to be defined automatically
	// "apcu" | "file" | "none"
	protected static string $__cacheMethod = "";

	// File path to cache directory for file cache system
	protected static string $__cachePath;


	/**
	 * Init and configure cache system.
	 * @param string $cacheMethod Can be "apcu" / "file" / "none" or "auto" for "apcu" with "file" fallback
	 * @param string|null $cachePath Absolute path for the "file" cache system
	 * @return void
	 * @throws Exception
	 */
	public static function init ( string $cacheMethod, ?string $cachePath = null ): void {
		if ( Env::get('NANO_DISABLE_CACHE', false) === true ) {
			self::$__cacheMethod = "none";
			return;
		}
		// Check cache path directory
		if ( ( $cacheMethod === "file" || $cacheMethod === "auto" ) && ( is_null($cachePath) ) ) {
			throw new \Exception("Cache::init // Invalid cache path $cachePath");
		}
		// Disable cache feature but keep api working
		if ( $cacheMethod === "none" || $cacheMethod === "apcu" || $cacheMethod === "file") {
			self::$__cacheMethod = $cacheMethod;
		}
		// Auto mode, we check if apcu is available, otherwise we select file cache
		else if ( $cacheMethod === "auto" ) {
			self::$__cacheMethod = function_exists("apcu_fetch") ? "apcu" : "file";
		}
		// Invalid cache method
		else {
			throw new \Exception("Cache::init // Invalid cache method $cacheMethod set from dot env");
		}
		// Init cache directory for file cache
		if ( self::$__cacheMethod === "file" ) {
			self::$__cachePath = $cachePath;
			self::cacheInitDirectory();
		}
	}

	public static function isActive (): bool {
		return !empty( self::$__cacheMethod );
	}

	protected static string $__cachePrefix = "__cache__";

	/**
	 * Set cache prefix.
	 * All cache commands will work in a different namespace.
	 * Default prefixed is namespaced to  __cache__
	 * @param $prefix
	 * @return void
	 */
	static function setCachePrefix ( $prefix ): void {
		self::$__cachePrefix = $prefix;
	}

	/**
	 * INTERNAL
	 * Get a file cache file path from its key.
	 */
	protected static function cacheGetFilePath ( $key ): string {
		return rtrim(self::$__cachePath).'/'.md5($key);
	}

	/**
	 * INTERNAL
	 * Init cache directory for file cache system.
	 */
	protected static function cacheInitDirectory (): void {
		if ( !file_exists(self::$__cachePath) )
			mkdir( self::$__cachePath, 0777, true );
	}

	/**
	 * Clear all cache.
	 * @return bool
	 * @throws \Exception
	 */
	static function clear (): bool {
		if ( empty(self::$__cacheMethod) )
			throw new Exception("Cache::clear // Invalid cache method");
		if ( self::$__cacheMethod === "apcu" )
			return apcu_clear_cache();
		else if ( self::$__cacheMethod === "file" ) {
			// Reset cache folder for file cache system
			if ( file_exists(self::$__cachePath) )
				FileSystem::recursiveRemoveDirectory( self::$__cachePath );
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
	 * @throws \Exception
	 */
	static function has ( string $key ): bool {
		if ( empty(self::$__cacheMethod) )
			throw new Exception("Cache::has // Invalid cache method");
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
	 * @throws \Exception
	 */
	static function get ( string $key ): mixed {
		if ( empty(self::$__cacheMethod) )
			throw new Exception("Cache::get // Invalid cache method");
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
	 * @throws \Exception
	 */
	static function set ( string $key, mixed $data ): bool {
		if ( empty(self::$__cacheMethod) )
			throw new Exception("Cache::set // Invalid cache method");
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
	 * @param callable|null $retrieveHandler Called when value is retrieved from cache. TODO : ARGUMENTS
	 * @param bool $disableCache Set to true to disable cache, can be useful in some cases where you want the cache to be bypassed and the handler always called.
	 * @return false|mixed
	 * @throws Exception
	 */
	static function define ( string $key, callable $getHandler, ?callable $retrieveHandler = null, bool $disableCache = false ): mixed {
		if ( empty(self::$__cacheMethod) )
			throw new Exception("Cache::define // Invalid cache method");
		// Always call handler if cache is disabled
		if ( self::$__cacheMethod === "none" || $disableCache )
			return $getHandler();
		// Store key without prefix for file cache system
		$keyNoPrefix = $key;
		$key = self::$__cachePrefix.$key;
		// Check existence in cache for apcu or file
		$isAPCU = self::$__cacheMethod === "apcu";
		if ( $isAPCU ? apcu_exists( $key ) : file_exists( self::cacheGetFilePath( $key ) ) ) {
			// Call retrieve handler
			// FIXME : What about the arguments here ?
			if ( !is_null($retrieveHandler) )
				$retrieveHandler();
			// Get value from cache
			$fetched = $isAPCU ? apcu_fetch( $key ) : self::get( $keyNoPrefix );
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
			: self::set( $keyNoPrefix, $result );
		}
		return $result;
	}

	/**
	 * Count entries in cache
	 * @return int|mixed|null
	 */
	static function count (): mixed {
		$isAPCU = self::$__cacheMethod === "apcu";
		// todo : support other cache methods
		if ( !$isAPCU )
			return null;
		$info = apcu_cache_info();
		return $info['num_entries'] ?? 0;
	}
}
