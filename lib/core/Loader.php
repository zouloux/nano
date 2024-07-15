<?php

namespace Nano\core;

use Exception;

class Loader
{
	/**
	 * Autoload php files in a directory.
	 * Is recursive.
	 * Will load files and directories in ascendant alphanumeric order.
	 * Name your files like so :
	 * - 00.my.first.file.php
	 * - 01.loaded.after.php
	 * - 02.you.got.it.php
	 * Can also start at 01.
	 * Will skip files and directories with name starting with an underscore. ex :
	 * - _skipped.php
	 */
	public static function loadFunctions ( string $directory, array $exclude = [] )
	{
		$files = scandir( $directory );
		foreach ( $files as $file ) {
			if ( $file == '.' || $file == '..' )
				continue;
			if ( stripos( $file, '_' ) === 0 )
				continue;
			if ( in_array($file, $exclude) )
				continue;
			$path = $directory.'/'.$file;
			// Recursively load directories
			if ( is_dir( $path ) ) {
				self::loadFunctions( $path, $exclude );
				continue;
			}
			// Target file
			$filePath = $directory.'/'.$file;
			$info = pathinfo( $filePath );
			// Only load PHP obviously
			if ( $info[ 'extension' ] === "php" )
				require_once( $filePath );
		}
	}

	/**
	 * Load Wordpress into Nano application.
	 * Use only if you need to access any Wordpress resource or Class.
	 * @return void
	 * @throws Exception
	 */
	public static function loadWordpress ()
	{
		if ( !defined('NANO_WORDPRESS_PATH') )
			throw new Exception("Loader::loadWordpress // NANO_WORDPRESS_PATH not defined");
		if ( !defined('WP_USE_THEMES') )
			define( 'WP_USE_THEMES', false );
		require_once NANO_WORDPRESS_PATH.'/wp-load.php';
	}
}