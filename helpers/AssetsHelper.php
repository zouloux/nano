<?php

namespace Nano\helpers;

use Exception;
use Nano\core\Nano;
use Nano\helpers\NanoUtils;

class AssetsHelper
{
	protected static array $__assets = [
		"header" => [
			"scripts" => [],
			"styles" => [],
		],
		"footer" => [
			"scripts" => [],
			"styles" => [],
		]
	];

	/**
	 * @param string|boolean $viteProxy Vite proxy URL. Default is "http://localhost:5173/" if true is given.
	 * 									False will load generated built assets from file system.
	 * @param string|false $viteMainScript Vite main script. False to disable.
	 * @param string|false $viteMainStyle Vite main style. False to disable.
	 * @param string $assetsPath Assets paths from base. Default is "assets/"
	 * @param string $cacheBusterSuffix Will add this to the end of asset href.
	 * 									Specify a different string each time you want to invalidate the resources.
	 * @param string $scriptLocation
	 * @param string $styleLocation
	 * @return void
	 * @throws Exception
	 */
	public static function addViteAssets (
		mixed $viteProxy,
		mixed $viteMainScript,
		mixed $viteMainStyle,
		string $assetsPath = "assets/",
		string $cacheBusterSuffix = "",
		string $scriptLocation = "footer",
		string $styleLocation = "header",
	) {
		// If we need to use vite proxy
		if ( $viteProxy ) {
			// Get default vite proxy
			$viteProxy = (
				( $viteProxy === true || strtolower($viteProxy) == "true" || $viteProxy == "1" )
				? "http://localhost:5173/"
				: rtrim($viteProxy, "/")."/"
			);
			// Set assets to proxy vite dev server
			self::addScriptFile("header", $viteProxy.ltrim($assetsPath, "/")."@vite/client", true);
			$viteMainStyle !== false && self::addStyleFile($styleLocation, $viteProxy.$viteMainStyle);
			$viteMainScript !== false && self::addScriptFile($scriptLocation, $viteProxy.$viteMainScript, true, true);
		}
		// Use vite built assets
		else {
			// Load manifest json file into assets directory
			$path = Nano::path($assetsPath, 'manifest', 'json');
			$manifest = Nano::readJSON5($path, false, true);
			if ( is_null($manifest) )
				return;
			// Add entry points
			$viteMainStyle !== false && self::addStyleFile($styleLocation, $assetsPath.$manifest[$viteMainStyle]["file"]."?".$cacheBusterSuffix);
			$viteMainScript !== false && self::addScriptFile($scriptLocation, $assetsPath.$manifest[$viteMainScript]["file"]."?".$cacheBusterSuffix, true, true);
		}
	}

	public static function addScriptFile ( $location, $href, $module, $async = false, $defer = false ) {
		$script = [
			"href" => $href,
			"module" => $module,
			"async" => $async,
			"defer" => $defer,
		];
		NanoUtils::dotSet( self::$__assets, $location.".scripts", [ $script ]);
	}

	public static function addStyleFile ( $location, $href ) {
		$style = [
			"href" => $href
		];
		NanoUtils::dotSet( self::$__assets, $location.".styles", [ $style ]);
	}

	// TODO
	//	public static function addScriptInline ( $location ) {}
	// TODO
	//	public static function addStyleInline ( $location ) {}

	public static function getAssetTags ( $location, $type ) {
		$assets = NanoUtils::dotGet( self::$__assets, $location.".".$type );
		$buffer = "";
		foreach ( $assets as $asset ) {
			if ( $type === "styles" )
				$buffer .= '<link rel="stylesheet" type="text/css" href="'.$asset["href"].'" />';
			if ( $type === "scripts" )
				$buffer .= '<script src="'.$asset["href"].'" type="module"'.( $asset["async"] ? " async" : "").( $asset["defer"] ?  "defer" : "").'></script>';
		}
		return $buffer;
	}
}