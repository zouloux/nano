<?php

namespace Nano\helpers;

// TODO : DOC

class Session
{
	public static string $cookieName = "";

	protected static bool $__started = false;

	public static function start ()
	{
		if ( self::$__started )
			return;
		self::$__started = true;
		session_cache_limiter( false );
		if ( !empty( static::$cookieName ) )
			session_name( static::$cookieName );
		session_start();
	}

	static function get ( string $key, bool $thenClear = false )
	{
		self::start();
		if ( !isset( $_SESSION[ $key ] ) )
			return null;
		$value = unserialize( $_SESSION[ $key ] );
		if ( $thenClear )
			unset( $_SESSION[ $key ] );
		return $value;
	}

	static function set ( string $key, mixed $value = null )
	{
		self::start();
		if ( is_null( $value ) )
			unset( $_SESSION[ $key ] );
		else
			$_SESSION[ $key ] = serialize( $value );
	}

	// todo : clear
	// todo : has
}