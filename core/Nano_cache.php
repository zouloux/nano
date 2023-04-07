<?php

namespace Nano\core;

use Nano\helpers\FileHelpers;

trait Nano_cache {

	// ------------------------------------------------------------------------- CACHE
	// Implementation status : 80%
	// Doc status : 0%

	// "apcu" | "file" | "none"
	protected static string $__cacheMethod;

	protected static string $__cachePath;

	protected static function initCache () {
		if ( isset(self::$__cacheMethod) )
			return;
		self::$__cacheMethod = Nano::getEnv("NANO_CACHE_METHOD", "apcu");
		if ( Nano::getEnv("NANO_DISABLE_CACHE", false) )
			self::$__cacheMethod = "none";
		if ( self::$__cacheMethod === "file" ) {
			self::$__cachePath = Nano::path( "app/data/", "cache/" );
			self::cacheInitDirectory();
		}
	}

	protected static string $__cachePrefix = "_nano_";
	static function setCachePrefix ( $prefix ) {
		self::$__cachePrefix = $prefix;
	}

	protected static function cacheGetFilePath ( $key ) {
		return self::$__cachePath.md5($key);
	}
	protected static function cacheInitDirectory () {
		if ( !file_exists(self::$__cachePath) )
			mkdir( self::$__cachePath, 0777, true );
	}

	static function cacheClear () {
		self::initCache();
		if ( self::$__cacheMethod === "apcu" )
			return apcu_clear_cache();
		else if ( self::$__cacheMethod === "file" ) {
			if ( file_exists(self::$__cachePath) )
				FileHelpers::recursiveRemoveDirectory( self::$__cachePath );
			self::cacheInitDirectory();
			return 1;
		}
		else
			return 1;
	}

	static function cacheHas ( string $key ) {
		self::initCache();
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" )
			return apcu_exists( $key );
		else if ( self::$__cacheMethod === "file" ) {
			return file_exists( self::cacheGetFilePath( $key ) );
		}
		else
			return false;
	}

	static function cacheGet ( string $key ) {
		self::initCache();
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

	static function cacheSet ( string $key, mixed $data ) {
		self::initCache();
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" )
			return apcu_store( $key, $data );
		else if ( self::$__cacheMethod === "file" ) {
			$path = self::cacheGetFilePath( $key );
			return file_put_contents( $path, serialize($data) );
		}
		else
			return 1;
	}

	static function cacheDefine ( $key, $getHandler, $retrieveHandler = null, $disableCache = false ) {
		self::initCache();
		if ( self::$__cacheMethod === "none" || $disableCache )
			return $getHandler();
		$keyNoPrefix = $key;
		$key = self::$__cachePrefix.$key;
		if ( self::$__cacheMethod === "apcu" ) {
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
		else if ( self::$__cacheMethod === "file" ) {
			$path = self::cacheGetFilePath( $key );
			if ( file_exists( $path ) ) {
				if ( !is_null($retrieveHandler) )
					$retrieveHandler();
				$fetched = Nano::cacheGet( $keyNoPrefix );
				if ( !is_null($fetched) )
					return $fetched;
			}
			$result = $getHandler();
			if ( !is_null($result) )
				Nano::cacheSet( $keyNoPrefix, $result );
			return $result;
		}
	}
}
