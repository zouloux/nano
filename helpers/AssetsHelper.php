<?php

namespace Nano\helpers;

use Exception;
use Nano\core\Nano;
use Nano\helpers\NanoUtils;

class AssetsHelper
{
	protected static array $__assets = [
		"top" => [
			"scripts" => [],
			"styles" => [],
		],
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
				? "http://".$_SERVER["HTTP_HOST"].":5173/"
				: rtrim($viteProxy, "/")."/"
			);
			// Set assets to proxy vite dev server
			$base = $viteProxy.ltrim($assetsPath, "/");
			self::addScriptFile("top", $base."@vite/client", true);
			//			$viteMainStyle !== false && self::addStyleFile($styleLocation, $base.$viteMainStyle);
			//			$viteMainStyle !== false && self::addScriptFile($styleLocation, $base.$viteMainStyle, true, false);
			$viteMainScript !== false && self::addScriptFile($scriptLocation, $base.$viteMainScript, true, false);

			//			if ( $viteMainScript ) {
			//				$src = $base.$viteMainScript;
			//				self::addScriptInline("header", <<<EOD
			//function loadScriptSync (src) {
			//    var script = document.createElement('script');
			//    script.src = src;
			//    script.type = "module";
			//    script.async = false;
			//    script.defer = false;
			//    document.getElementsByTagName('head')[0].appendChild(script);
			//}
			//loadScriptSync("$src")
			//EOD);
			//			}
		}
		// Use vite built assets
		else {
			// Load manifest json file into assets directory
			$path = Nano::path($assetsPath, 'manifest', 'json');
			$manifest = Nano::readJSON5($path, false, true);
			//dump($manifest);
			if ( is_null($manifest) )
				return;
			// Add entry points
			$viteMainStyle !== false && self::addStyleFile($styleLocation, $assetsPath.$manifest[$viteMainStyle]["file"]."?".$cacheBusterSuffix);
			if ( $viteMainScript !== false ) {
				// Add main JS module
				self::addScriptFile($scriptLocation, $assetsPath.$manifest[$viteMainScript]["file"]."?".$cacheBusterSuffix, true, true);
				// Get legacy entry points
				foreach ( $manifest as $key => $entry ) {
					if ( !isset($entry["isEntry"]) || !$entry["isEntry"] ) continue;
					if ( $key === $viteMainScript ) continue;
					// Legacy polyfills src is something like : "../../vite/legacy-polyfills-legacy"
					if ( stripos($entry["src"], "legacy-polyfills-legacy") !== false )
						$legacyPolyfills = $entry;
					// Legacy entry points src is something like : "index-legacy.tsx"
					else if ( stripos($entry["src"], "-legacy.") !== false )
						$legacyEntryPoint = $entry;
				}
				// Add polyfills as nomodule first
				if ( isset($legacyPolyfills) )
					self::addScriptFile($scriptLocation, $assetsPath.$legacyPolyfills["file"]."?".$cacheBusterSuffix, false);
				// Then add legacy entry point as nomodule
				if ( isset($legacyEntryPoint) )
					self::addScriptFile($scriptLocation, $assetsPath.$legacyEntryPoint["file"]."?".$cacheBusterSuffix, false);
			}
		}
	}

	public static function addScriptFile ( $location, $href, $module = null, $async = false, $defer = false ) {
		NanoUtils::dotAdd( self::$__assets, $location.".scripts", [[
			"href" => $href,
			"module" => $module,
			"async" => $async,
			"defer" => $defer,
		]]);
	}

	public static function addScriptInline ( $location, $content, $module = null ) {
		NanoUtils::dotAdd( self::$__assets, $location.".scripts", [[
			"content" => $content,
			"module" => $module,
		]]);
	}

	public static function addStyleFile ( $location, $href ) {
		NanoUtils::dotSet( self::$__assets, $location.".styles", [[
			"href" => $href
		]]);
	}

	public static function addStyleInline ( $location, $content ) {
		NanoUtils::dotAdd( self::$__assets, $location.".styles", [[
			"content" => $content
		]]);
	}


	public static function getAssetTags ( $location, $type ) {
		// FIXME : Sanitize
		$assets = NanoUtils::dotGet( self::$__assets, $location.".".$type );
		$buffer = "";
		foreach ( $assets as $asset ) {
			if ( $type === "styles" ) {
				if ( isset($asset["content"]) )
					$buffer .= '<style>'.$asset['content'].'</style>';
				else
					$buffer .= '<link rel="stylesheet" type="text/css" href="'.$asset["href"].'" />';
			}
			if ( $type === "scripts" ) {
				$arguments = [];
				if ( is_bool($asset["module"]) )
					$arguments[] = $asset["module"] ? 'type="module"' : 'nomodule';
				if ( isset($asset["content"]) )
					$inline = $asset["content"];
				else {
					$arguments = array_merge($arguments, [
						'src="'.$asset["href"].'"',
						$asset["async"] ? "async" : "",
						$asset["defer"] ? "defer" : "",
					]);
					$inline = "";
				}
				$buffer .= '<script  '.implode(" ", $arguments).'>'.$inline.'</script>';
			}
		}
		return $buffer;
	}
}