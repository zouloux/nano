<?php

namespace Nano\helpers;

class Session
{
	// Default session duration
	const int DEFAULT_DURATION = 60 * 60 * 24 * 10; // 10 days

	// Default cookie name
	const string DEFAULT_COOKIE_NAME = "session";

	// Customize cookie name
	public static string $cookieName = self::DEFAULT_COOKIE_NAME;

	protected static bool $__started = false;

	/**
	 * Start session and send http headers.
	 * @param int|null $sessionDuration Duration of the session and the cookie in seconds.
	 * @return void
	 */
	public static function start ( int $sessionDuration = null ) {
		if ( self::$__started )
			return;
		self::$__started = true;
		// Set session lifetime
		$sessionDuration ??= self::DEFAULT_DURATION;
		ini_set( 'session.gc_maxlifetime', $sessionDuration );
		session_set_cookie_params( $sessionDuration );
		// Disable cache headers
		session_cache_limiter( false );
		// Start
		if ( !empty( static::$cookieName ) )
			session_name( static::$cookieName );
		session_start();
	}

	/**
	 * Get some session data from the key. Can be array / string / int / etc
	 * @param string $key
	 * @param bool $thenClear Clear data while returning it
	 * @return mixed|null
	 */
	static function get ( string $key, bool $thenClear = false ):mixed {
		self::start();
		if ( !isset( $_SESSION[ $key ] ) )
			return null;
		$value = unserialize( $_SESSION[ $key ] );
		if ( $thenClear )
			unset( $_SESSION[ $key ] );
		return $value;
	}

	/**
	 * Set some session data to this key. Can be array / string / int / etc
	 * @param string $key
	 * @param mixed|null $value
	 * @return void
	 */
	static function set ( string $key, mixed $value = null ) {
		self::start();
		if ( is_null( $value ) )
			unset( $_SESSION[ $key ] );
		else
			$_SESSION[ $key ] = serialize( $value );
	}

	static function has ( string $key ) {
		self::start();
		return isset( $_SESSION[ $key ] );
	}

	static function clear () {
		self::start();
		$_SESSION = [];
	}

	static function destroy () {
		self::start();
		session_destroy();
	}
}
