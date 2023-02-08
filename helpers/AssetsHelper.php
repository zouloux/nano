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
	 * Manage vite assets automatically from .env config.
	 * Will load cache busting version from data/version/version.txt.
	 * Will get manifest and assets from "assets/" from base.
	 * @return void
	 * @throws Exception
	 */
	public static function viteAuto () {
		// Get config from .env
		$viteProxy = Nano::getEnv("NANO_VITE_PROXY", false);
		$viteMainScript = Nano::getEnv("NANO_VITE_MAIN_SCRIPT", "index.ts");
		$viteMainStyle = Nano::getEnv("NANO_VITE_MAIN_STYLE", "index.less");
		$assetsPath = Nano::getBase()."assets/";
		// Read cache buster version
		Nano::loadAppData("version", "version", "txt", "0");
		$cacheBuster = Nano::getAppData("version");
		// Inject assets
		AssetsHelper::injectViteAssets( $viteProxy, $viteMainScript, $viteMainStyle, $assetsPath, $cacheBuster );
	}

	/**
	 * @param string|boolean $viteProxy Vite proxy URL. Default is "http://localhost:5173/" if true is given.
	 * 									False will load generated built assets from file system.
	 * @param string $viteMainScript Vite main script. Default is "index.ts"
	 * @param string $viteMainStyle Vite main style. Default is "index.less"
	 * @param string $assetsPath Assets paths from base. Default is "assets/"
	 * @param string $cacheBusterSuffix
	 * @return void
	 * @throws Exception
	 */
	public static function injectViteAssets (
		mixed $viteProxy,
		string $viteMainScript = "index.ts",
		string $viteMainStyle = "index.less",
		string $assetsPath = "assets/",
		string $cacheBusterSuffix = ""
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
			self::addScriptFile("header", $viteProxy."assets/@vite/client", true);
			self::addStyleFile("header", $viteProxy.$viteMainStyle);
			self::addScriptFile("footer", $viteProxy.$viteMainScript, true);
		}
		// Use vite built assets
		else {
			// Load manifest json file into assets directory
			$path = Nano::path($assetsPath, 'manifest', 'json');
			$manifest = Nano::readJSON5($path, false, true);
			if ( is_null($manifest) )
				return;
			// Add entry points
			self::addStyleFile("header", $assetsPath.$manifest[$viteMainStyle]["file"]."?".$cacheBusterSuffix);
			self::addScriptFile("footer", $assetsPath.$manifest[$viteMainScript]["file"]."?".$cacheBusterSuffix, true);
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
		foreach ( $assets as $asset )
			if ( $type === "scripts" )
				$buffer .= '<script src="'.$asset["href"].'" type="module"'.( $asset["async"] ? " async" : "").( $asset["defer"] ?  "defer" : "").'></script>';
			else if ( $type === "styles" )
				$buffer .= '<link rel="stylesheet" type="text/css" href="'.$asset["href"].'" />';
		return $buffer;
	}
}