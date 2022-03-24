<?php

namespace Nano\core;

use Exception;
use Nano\debug\NanoDebug;
use Nano\helpers\NanoUtils;

trait Nano_data {

	// ------------------------------------------------------------------------- PATH UTILS
	// Implementation status : 100%

	/**
	 * Compute a path to an app resource.
	 * For file system only, do not use for http resources.
	 * Will return an absolute path from relative elements.
	 * @param string $from Root dir to search from, starting at web root dir.
	 * @param string $path  Path to the resource, added to $from. Will prevent going backward by removing dots.
	 * 						Keep empty if you need to target an folder.
	 * @param string $extension Optionally, force file extension. If not defined, extension needs to set be into $path.
	 * @return string Absolute path from relative elements.
	 */
	static function path ( string $from, string $path = "", string $extension = "" ) {
		$path = ltrim(str_replace("..", "", $path), "/");
		return Nano::$__rootPath."/".rtrim($from, "/")."/".$path.(empty($extension) ? '' : ".".$extension);
	}

	// ------------------------------------------------------------------------- JSON R / W
	// Implementation status : 100%

	/**
	 * Read any json or json5 file from root
	 * @param string $absolutePath Needs to be absolute, use Nano::path. Can load any json or json5 file in app, static data, or dynamic data.
	 * @param bool $throw Will throw errors if any. Otherwise, will just return null.
	 * @param bool $useNativeJsonDecode If true, will not use json5_decode but json_decode
	 * @return mixed|null
	 * @throws Exception
	 */
	static function readJSON5 ( string $absolutePath, bool $throw = true, bool $useNativeJsonDecode = false ): mixed {
		// Check if this file exists
		if ( !file_exists($absolutePath) ) {
			if ( !$throw ) return null;
			throw new Exception("Nano::readJson // File ${absolutePath} not found.");
		}
		// Try to load and decode file (with json5)
		try {
			$profiling = NanoDebug::profile("Reading file $absolutePath");
			$rawContent = file_get_contents( $absolutePath );
			$res = (
				$useNativeJsonDecode
				? json_decode( $rawContent, true, 512, JSON_NUMERIC_CHECK )
				: json5_decode( $rawContent, true, 512, JSON_NUMERIC_CHECK )
			);
			$profiling();
			return $res;
		}
		// Error while loading / decoding file
		catch ( Exception $error ) {
			if ( !$throw ) return null;
			throw new Exception( "Nano::readJson // Invalid JSON5 file $absolutePath.\n".$error->getMessage() );
		}
	}

	/**
	 * Read json file from the dynamic data folder ( /data )
	 * Will use Nano::readJSON5 but with native loader, not JSON5.
	 * @param string $relativePath This path is from /data. Do not use Nano::path !
	 * @param bool $throw Will throw errors if any. Otherwise, will just return null.
	 * @return mixed|null
	 * @throws Exception
	 */
	static function readDynamicData ( string $relativePath, bool $throw = true ): mixed {
		return Nano::readJSON5( Nano::path( "data/", $relativePath ), $throw, true );
	}

	/**
	 * Save a json file into the dynamic data folder ( /data )
	 * Will override existing file.
	 * Will create needed directories.
	 * @param string $relativePath This path is from /data. Do not use Nano::path !
	 * @param mixed $data Data to save as JSON.
	 * @param bool $throw Will throw errors if any. Otherwise, will just return null.
	 * @return bool|int Number of bytes written or false if unable to save.
	 * @throws Exception
	 */
	static function writeDynamicData ( string $relativePath, mixed $data, bool $throw = true ): bool|int {
		$absolutePath = Nano::path( "data/", $relativePath );
		$jsonDirPath = dirname($absolutePath);
		if ( !is_dir($jsonDirPath) )
			mkdir($jsonDirPath, 0777, true);
		try {
			$profiling = NanoDebug::profile("Writing dynamic file $absolutePath");
			$res = file_put_contents( $absolutePath, json_encode($data, JSON_PRETTY_PRINT) );
			$profiling();
			return $res;
		}
		// Error while writing / encoding file
		catch ( Exception $error ) {
			if ( !$throw ) return false;
			throw new Exception( "Nano::writeDynamicData // Unable to save file $absolutePath.\n".$error->getMessage() );
		}
	}

	// ------------------------------------------------------------------------- APP DATA GET / SET
	// Implementation status : 100%

	// Stored app data
	protected static array $__appData = [];

	// Directory to load app data from
	protected static string $__appDataDirectory;

	/**
	 * Set app data directory
	 */
	static function setAppDataDirectory ( string $directoryRelativeToAppRoot ) {
		Nano::$__appDataDirectory = $directoryRelativeToAppRoot;
	}

	/**
	 * Get loaded app data with dot notation.
	 */
	static function getAppData ( string $key = null ) {
		return (
			is_null($key) ? Nano::$__appData
			: NanoUtils::dotGet( Nano::$__appData, $key )
		);
	}

	/**
	 * Load and inject app data from /app/data folder.
	 * @param string $key Key to inject into. Handle dot notation.
	 * @param string|null $filePathFromAppDataWithoutExtension File path relative to /app/data folder, without extension.
	 * @param string $extension Default extension is json, can be json5 or txt.
	 * @param string $extension Default extension is json5.
	 * @return void
	 * @throws Exception
	 */
	static function loadAppData ( string $key, string $filePathFromAppDataWithoutExtension = null, string $extension = "json", mixed $default = null ) {
		if ( !in_array($extension, ["json5", "json", "txt"]) )
			throw new Exception("Nano::loadAppData // Invalid extension $extension");
		// Path is from key if not defined
		if ( $filePathFromAppDataWithoutExtension == null )
			$filePathFromAppDataWithoutExtension = $key;
		// Absolute path to json file
		$absolutePath = Nano::path(Nano::$__appDataDirectory, $filePathFromAppDataWithoutExtension, $extension);
		// Load as raw text
		if ( $extension == "txt" ) {
			if ( !file_exists($absolutePath) ) {
				if ( is_null($default) )
					throw new Exception("Nano::loadAppData // File $filePathFromAppDataWithoutExtension not found.");
				$data = $default;
			}
			else {
				$data = file_get_contents( $absolutePath );
			}
		}
		// Load as Json and throw errors
		else if ( $extension == "json" || $extension == "json5" )
			$data = Nano::readJSON5( $absolutePath );
		// Inject
		self::injectAppData( $key, $data );
	}

	/**
	 * Inject app data.
	 * @param string $key Key to inject into. Handle dot notation.
	 * @param mixed $data
	 * @return void
	 */
	static function injectAppData ( string $key, mixed $data ) {
		// Inject data
		NanoUtils::dotAdd( Nano::$__appData, $key, $data );
	}
}