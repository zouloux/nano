<?php

namespace Nano\core;

trait Nano_sessions {

	// ------------------------------------------------------------------------- SESSION
	// Implementation status : 100%
	// TODO : Doc \w serialize
	// TODO : Test with stdobject
	// TODO : Use dot notation ?

	static function setSession ( string $key, mixed $value ) {
		if ( is_null($value) )
			unset( $_SESSION[$key] );
		else
			$_SESSION[$key] = serialize( $value );
	}

	static function getSession ( string $key, bool $thenClear = false ) {
		if ( !isset($_SESSION[ $key ]) ) return null;
		$value = unserialize( $_SESSION[ $key ] );
		if ( $thenClear ) unset( $_SESSION[ $key ] );
		return $value;
	}
}