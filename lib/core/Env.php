<?php

namespace Nano\core;

use Dotenv\Dotenv;
use Exception;

class Env
{
	/**
	 * Get the value of an env variable with $_ENV.
	 * Will try to parse numbers and booleans.
	 * @param string $key Key of the env to get
	 * @param mixed|null $default Default value used if not found.
	 * @return mixed
	 */
	public static function get ( string $key, mixed $default = null ) : mixed
	{
		$value = $_ENV[$key] ?? false;
		if ( $value === false )
			return $default;
		switch (strtolower($value)) {
			case 'true':
				return true;
			case 'false':
				return false;
			case '':
				return '';
			case 'null':
				return null;
			case is_numeric( $value ):
				return floatval( $value );
		}
		if ( preg_match('/\A([\'"])(.*)\1\z/', $value, $matches) )
			return $matches[2];
		return $value;
	}

	public static function exists ( string $key )
	{
		return isset($_ENV[$key]);
	}

	/**
	 * Load a dot env file and declare each envs.
	 * @param string $directory Root directory to load the file from
	 * @param string $fileName Default file name is ".env"
	 * @param bool $throw If true, will throw errors in case of file not found.
	 * @return void
	 * @throws Exception
	 */
	public static function loadDotEnvFile ( string $directory, string $fileName = ".env", bool $throw = true )
	{
		$filePath = rtrim($directory, '/').'/'.$fileName;
		if ( !file_exists( realpath( $filePath ) ) ) {
			if ( $throw )
				throw new Exception("Env::loadDotEnvFile // $filePath not found" );
			else return;
		}
		Dotenv::createImmutable( $directory, $fileName )->load();
	}

	/**
	 * Define envs as const from a list
	 * @param string $forceDotEnvPrefix
	 * @param array $envList
	 * @return void
	 */
	public static function defineEnvs ( string $forceDotEnvPrefix, array $envList )
	{
		foreach ( $envList as $key => $value ) {
			// Map value to key if we do not have default value
			$realKey = is_int( $key ) ? $value : $key;
			// Force prefix for all dot envs if $forceDotEnvPrefix is defined.
			// It allows to have env vars like WP_DB_HOST in .env mapped to DB_HOST here
			$prefix = (
				!empty( $forceDotEnvPrefix ) && !str_starts_with( $realKey, $forceDotEnvPrefix )
				? $forceDotEnvPrefix : ""
			);
			// Get value from .env with forced prefix,
			// If value is not found take the default, or empty string if no default
			$defaultValue     = is_int( $key ) ? "" : $value;
			$valueWithDefault = Env::get( $prefix.$realKey, $defaultValue );
			if ( !defined( $realKey ) )
				define( $realKey, $valueWithDefault );
		}
	}

	/**
	 * Create a dot env file with private key.
	 * Will create the file only if it does not already exist.
	 *
	 * @param string $directory path to the directory where is stored the keys.env
	 * @param string $fileName dot env file name
	 * @param array $keys list of key names to generate
	 *
	 * @return void
	 * @throws \Random\RandomException
	 */
	public static function initKeysEnvFile ( string $directory, string $fileName, array $keys )
	{
		$filePath = $directory.'/'.$fileName;
		if ( !file_exists( realpath( $filePath ) ) ) {
			$output = [];
			foreach ( $keys as $key )
				$output[] = $key.'='.bin2hex( random_bytes( 32 ) );
			$buffer = implode( "\n", [
				"# Auto-generated file, do not modify. Delete this file to re-generated keys.",
				...$output
			]);
      mkdir( dirname( $filePath ), 0777, true );
			file_put_contents( $filePath, $buffer );
		}
		self::loadDotEnvFile( $directory, $fileName );
		self::defineEnvs( '', $keys );
	}
}
