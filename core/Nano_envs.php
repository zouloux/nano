<?php

namespace Nano\core;

use Exception;
use M1\Env\Parser;
use Nano\debug\NanoDebug;

trait Nano_envs
{
	// ------------------------------------------------------------------------- ENVS
	// Implementation status : 100%

	/**
	 * Get an env variable.
	 * Can be loaded with loadEnvs.
	 * Will use $_ENV with a string to boolean and a numeric parser
	 */
	static function getEnv ( string $key = null, mixed $default = null ) {
		if ( is_null($key) )
			return $_ENV;
		return $_ENV[ $key ] ?? $default;
	}

	/**
	 * Load a dot env file into Nano.
	 * Can load multiple dot envs
	 * @param string $pathFromRoot Relative file path from root.
	 * @param string $filterPrefix If defined, will filter out every var not starting by this prefix.
	 * @param bool $throw Will throw if any error. Disable to override with optional .env.local by example.
	 * @return void
	 * @throws Exception
	 */
	static function loadEnvs ( string $pathFromRoot = "../.env", string $filterPrefix = "NANO_", bool $throw = true ) {
		$profiling = NanoDebug::profile("Load envs .$pathFromRoot", true);
		// Target file and throw if it does not exist
		$dotEnvPath = Nano::$__rootPath."/".$pathFromRoot;
		if ( !file_exists($dotEnvPath) ) {
			if ( !$throw ) return null;
			throw new Exception("Nano::loadEnvs // Dot env file $dotEnvPath does not exists.");
		}
		// Load vars
		try {
			$vars = Parser::parse(file_get_contents( $dotEnvPath ));
		}
		catch ( Exception $error ) {
			if ( !$throw ) {
				$profiling();
				return null;
			}
			throw new Exception("Nano::loadEnvs // Unable to load $dotEnvPath env file.\n".$error->getMessage());
		}
		// Injected filtered vars into $__envs
		foreach ( $vars as $key => $value ) {
			// Filter out
			if ( !is_null($filterPrefix) && stripos($key, $filterPrefix) !== 0 )
				continue;
			$_ENV[ $key ] = $value;
		}
		$profiling();
	}
}