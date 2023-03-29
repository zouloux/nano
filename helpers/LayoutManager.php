<?php

namespace Nano\helpers;

use Nano\core\Nano;
use Nano\helpers\NanoUtils;


class LayoutManager
{
	// ------------------------------------------------------------------------- CHARSET

	const CHARSET_UTF8 = "utf-8";

	protected static string $__charset = "";
	public static function getCharset () {
		return self::$__charset;
	}
	public static function setCharset ( string $charset ) {
		self::$__charset = $charset;
	}

	// ------------------------------------------------------------------------- HTML LANG

	protected static string $__htmlLang = "";
	public static function getHTMLLang () {
		return self::$__htmlLang;
	}
	public static function setHTMLLang ( string $htmlLang ) {
		self::$__htmlLang = $htmlLang;
	}

	// ------------------------------------------------------------------------- HTML CLASSES

	protected static array $__htmlClasses = [];
	public static function getHTMLClasses () {
		return self::$__htmlClasses;
	}
	public static function setHTMLClasses ( array $htmlClasses ) {
		self::$__htmlClasses = $htmlClasses;
	}
	public static function addHTMLClass ( string $htmlClass ) {
		self::$__htmlClasses[] = $htmlClass;
	}

	// ------------------------------------------------------------------------- VIEWPORT

	const VIEWPORT_FIXED = "width=device-width, initial-scale=1";
	const VIEWPORT_FIXED_NO_SCALE = "width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=0";

	protected static string $__viewport = "";
	public static function getViewport () {
		return self::$__viewport;
	}
	public static function setViewport ( string $viewport ) {
		self::$__viewport = $viewport;
	}

	// ------------------------------------------------------------------------- META DATA

	protected static array $__metaData = [];
	public static function getMetaData () {
		return self::$__metaData;
	}

	/**
	 * Inject meta-data and override previously injected meta-data.
	 * In first, inject website default meta-data.
	 * Then override current page meta-data.
	 *
	 * Structure
	 * $metaData
	 *   |- description
	 *   |- shareTitle ( is og:title )
	 *   |- shareDescription ( is og:description )
	 *   |- shareImage ( href for og:image )
	 *
	 * @param $metaData
	 * @return void
	 */
	public static function injectMetaData ( $metaData ) {
		if ( $metaData["description"] )
			self::$__metaData["description"] = $metaData["description"];
		if ( $metaData["shareTitle"] )
			self::$__metaData["shareTitle"] = $metaData["shareTitle"];
		if ( $metaData["shareDescription"] )
			self::$__metaData["shareDescription"] = $metaData["shareDescription"];
		if ( $metaData["shareImage"] )
			self::$__metaData["shareImage"] = $metaData["shareImage"];
	}

	// ------------------------------------------------------------------------- VIEWPORT

	protected static string $__title = "";
	public static function getTitle () {
		return self::$__title;
	}

	/**
	 * Set title tag.
	 * @param string $pageTitle Current page title.
	 * @param string $siteName Site name, if you need to template it. To disable templating, send null.
	 * @param string $titleTemplate Use this template to combine site name and page name
	 * @return void
	 */
	public static function setTitle ( $pageTitle, $siteName = null, $titleTemplate = "{{site}} - {{page}}") {
		// No site name, no templating
		if ( is_null($siteName) )
			self::$__title = $siteName;

		// Generate page title from site name and template
		else {
			self::$__title = NanoUtils::stache($titleTemplate, [
				"site" => $siteName,
				"page" => $pageTitle,
			]);
		}
	}

	// ------------------------------------------------------------------------- ICONS

	protected static array $__icons = [ null, null ];
	public static function getIcons () {
		return self::$__icons;
	}

	/**
	 * Set icons.
	 * @param string|null $icon32 Href to PNG 32x32 icon ( desktop browser favicon )
	 * @param string|null $icon1024 Href to PNG 1024x1024 icon ( mobile browsers )
	 * @return void
	 */
	public static function setIcons ( string $icon32 = null, string $icon1024 = null ) {
		self::$__icons = [ $icon32, $icon1024 ];
	}

	// ------------------------------------------------------------------------- APP

	protected static $__appTheme = [];
	public static function getAppTheme () {
		return self::$__appTheme;
	}

	/**
	 * Set web app theme parameters.
	 * @param string $appTitle App title is web app name when added to home page.
	 * @param string $appColor Application color as hex string "#FF0000". Used on some desktop and mobile browsers.
	 * @param string $iosTitleBar Rendering of iOS status bar.
	 * @return void
	 */
	public static function setAppTheme ( string $appTitle, string $appColor, string $iosTitleBar ) {
		self::$__appTheme = [
			"title" => $appTitle,
			"color" => $appColor,
			"titleBar" => $iosTitleBar,
		];
	}

	// ------------------------------------------------------------------------- RENDER META TAGS

	/**
	 * Render all meta tags.
	 * Will return a string and will not echo anything.
	 *
	 * In this order :
	 * |- charset
	 * |- viewport
	 * |- title
	 * |- description
	 * |- shareTitle
	 * |- shareDescription
	 * |- shareImage
	 * |- icon32
	 * |- icon1024
	 * |- webAppCapable
	 * |- webAppTitle
	 * |- webAppIosTitleBar
	 * |- webAppColor
	 * @param bool $returnAsArray Return as array to be able to filter it before rendering it.
	 * @return string|array
	 */
	public static function renderMetaTags ( bool $returnAsArray = false ) {
		$buffer = [];
		// --- CHARSET
		if ( self::$__charset )
			$buffer[] = "<meta charset=\"".addslashes(self::$__charset)."\" />";
		// --- VIEWPORT
		if ( self::$__viewport )
			$buffer[] = "<meta name=\"viewport\" content=\"".addslashes(self::$__viewport)."\" />";
		// --- TITLE
		$buffer[] = "<title>".strip_tags(self::$__title)."</title>";
		// --- DESCRIPTION
		if ( self::$__metaData["description"] )
			$buffer[] = "<meta name=\"description\" content=\"".addslashes(self::$__metaData["description"])."\">";
		// --- OG TITLE
		if ( self::$__metaData["shareTitle"] )
			$buffer[] = "<meta property=\"og:title\" content=\"".addslashes(self::$__metaData["shareTitle"])."\" />";
		// --- OG DESCRIPTION
		if ( self::$__metaData["shareDescription"] )
			$buffer[] = "<meta property=\"og:description\" content=\"".addslashes(self::$__metaData["shareDescription"])."\" />";
		// --- OG IMAGE
		if ( self::$__metaData["shareImage"] )
			$buffer[] = "<meta property=\"og:image\" content=\"".addslashes(self::$__metaData["shareImage"])."\" />";
		// --- FAVICON 32
		if ( self::$__icons[0] )
            $buffer[] = "<link rel=\"icon\" type=\"image/png\" href=\"".addslashes(self::$__icons[0])."\" />";
		// --- ICON 1024
		if ( self::$__icons[1] )
            $buffer[] = "<link rel=\"apple-touch-icon\" type=\"image/png\" href=\"".addslashes(self::$__icons[1])."\" />";
		// --- WEB APP CAPABLE
		if ( self::$__appTheme )
			$buffer[] = "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\" />";
		// --- WEB APP TITLE
		if ( self::$__appTheme["title"] )
			$buffer[] = "<meta name=\"apple-mobile-web-app-title\" content=\"".addslashes(self::$__appTheme["title"])."\" />";
		// --- WEB APP IOS TITLE BAR
		if ( self::$__appTheme["titleBar"] && self::$__appTheme["titleBar"] != "none" )
			$buffer[] = "<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"".addslashes(self::$__appTheme["titleBar"])."\" />";
		// --- WEB APP COLOR
		if ( self::$__appTheme["color"] ) {
			// MICROSOFT
			$buffer[] = "<meta name=\"msapplication-config\" content=\"none\" />";
			$buffer[] = "<meta name=\"msapplication-TileImage\" content=\"".addslashes(self::$__appTheme["color"])."\" />";
			// OTHERS
			$buffer[] = "<meta name=\"theme-color\" content=\"".addslashes(self::$__appTheme["color"])."\" />";
		}
		return (
			$returnAsArray
			? $buffer
			: implode("\n", $buffer)
		);
	}

	// ------------------------------------------------------------------------- RENDER HTML ATTRIBUTES

	public static function renderHTMLAttributes () {
		$buffer = [];
		if ( self::$__htmlLang )
			$buffer[] = "lang=\"".addslashes(self::$__htmlLang)."\"";
		if ( self::$__htmlClasses )
			$buffer[] = "class=\"".addslashes(implode(" ", self::$__htmlClasses))."\"";
		return implode(" ", $buffer);
	}

	// ------------------------------------------------------------------------- ASSETS

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
	 * Direct access to all registered assets
	 * @return array|array[][]
	 */
	public static function getAssets () {
		return self::$__assets;
	}

	/**
	 * Add vite assets, from vite server or generated vite assets directory.
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
	 * @throws \Exception
	 */
	public static function addViteAssets (
		mixed $viteProxy,
		mixed $viteMainScript,
		mixed $viteMainStyle,
		string $assetsPath = "assets/",
		string $cacheBusterSuffix = "",
		string $scriptLocation = "footer",
		string $styleLocation = "header"
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
			$viteMainScript !== false && self::addScriptFile($scriptLocation, $base.$viteMainScript, true);
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

	public static function addScriptJSONVariable ( $location, $name, $value, $jsonFlags = 0, $jsonDepth = 512 ) {
		$content = "window['$name'] = ".json_encode($value, $jsonDepth).";";
		self::addScriptInline($location, $content);
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

	/**
	 * Render assets tags.
	 * @param string $location Location of registered assets ( top / header / footer )
	 * @param string $type Type of asset tag to generate ( style / script )
 	 * @param bool $returnAsArray Return as array to be able to filter it before rendering it.
	 * @return string|array
	 * @throws \Exception
	 */
	public static function renderAssetTags ( string $location, string $type, bool $returnAsArray = false ) {
		// Get assets for this type and location
		if ( !isset(self::$__assets[$location]) )
			throw new \Exception("LayoutManager::renderAssetTags // Invalid location $location");
		$assets = NanoUtils::dotGet( self::$__assets, $location.".".$type );
		if ( !is_array($assets) || empty($assets) )
			return "";
		// Generate buffer lines from those assets
		$buffer = [];
		foreach ( $assets as $asset ) {
			// Generate style tags
			if ( $type === "styles" ) {
				// Inline
				if ( isset($asset["content"]) )
					// FIXME : content is not sanitized here
					$buffer[] = '<style>'.$asset['content'].'</style>';
				// Href
				else
					$buffer[] = '<link rel="stylesheet" type="text/css" href="'.addslashes($asset["href"]).'" />';
			}
			// Generate script tags
			if ( $type === "scripts" ) {
				$arguments = ['']; // empty string so implode will always start with a space if we have more than 0 argument
				if ( is_bool($asset["module"]) )
					$arguments[] = $asset["module"] ? 'type="module"' : 'nomodule';
				if ( isset($asset["content"]) )
					$inline = $asset["content"];
				else {
					$arguments[] = 'src="'.addslashes($asset["href"]).'"';
					if ( $asset["async"] )
						$arguments[] = "async";
					if ( $asset["defer"] )
						$arguments[] = "defer";
					$inline = "";
				}
				// FIXME : $inline is not sanitized here
				$buffer[] = '<script'.implode(" ", $arguments).'>'.$inline.'</script>';
			}
		}
		return (
			$returnAsArray
			? $buffer
			: implode("\n", $buffer)
		);
	}

	// ------------------------------------------------------------------------- AUTO MODE

	/**
	 * Autoconfigure meta from Bowl-like globals and page data.
	 *
	 * Structure :
	 *   $globals
	 *   |- meta -> @see LayoutManager::injectMetaData
	 *   |- siteName
	 *   |- theme
	 *      |- pageTitleTemplate
	 *      |- icon32
	 *      |- icon1024
	 *      |- appTitle
	 *      |- appColor
	 *      |- iosTitleBar
	 *   |- dictionaries
	 *      |- not-found
	 *         |- title
	 *   $pageData
	 *   |- title
	 *   |- fields
	 *      |- meta -> @see LayoutManager::injectMetaData
	 *
	 * @param mixed $globals Bowl-like Globals.
	 * @param mixed $pageData Bowl-like page data as array.
	 * @param mixed $htmlLang 2 chars locale for html tag
	 * @return void
	 */
	public static function autoMeta ( $globals, $pageData, $htmlLang = "en" ) {
		$theme = $globals["theme"] ?? [];
		// Inject global meta-data
		self::injectMetaData( $globals["meta"] );
		unset( $globals["meta"] );
		// Override with page meta-data
		if ( !is_null($pageData) && isset($pageData["fields"]["meta"]) ) {
			self::injectMetaData($pageData["fields"]["meta"]);
			unset($pageData["fields"]["meta"]);
		}
		self::setHTMLLang( $htmlLang );
		// Set page title
		$pageTitle = (
			is_null($pageData)
			? ( $globals["dictionaries"]["not-found"]["title"] ?? "Not found" )
			: $pageData["title"]
		);
		$titleTemplate = $theme["pageTitleTemplate"] ?? "{{site}} - {{page}}";
		self::setTitle( $pageTitle, $globals["siteName"], $titleTemplate );
		// Set UTF8 charset
		self::setCharset( self::CHARSET_UTF8 );
		// Set fixed view port
		self::setViewport( self::VIEWPORT_FIXED );
		// Set icons
		self::setIcons( $theme["icon32"], $theme["icon1024"] );
		// Set app
		self::setAppTheme( $theme["appTitle"], $theme["appColor"], $theme["iosTitleBar"] );
	}

	/**
	 * Autoconfigure assets for vite.
	 * Will read NANO_VITE_PROXY env to detect if we need to load assets from vite server
	 * If false or not defined, will load assets from assets directory.
	 * Will use cache busting from app data file version.txt
	 * @see LayoutManager::addViteAssets
	 * @param string $indexScript Index script file name in vite manifest
	 * @param string $indexStyle Index style file name in vite manifest
	 * @param string $assetsDirectory Path to generated assets directory from base.
	 * @return bool Will return true if in dev mode.
	 * @throws \Exception
	 */
	public static function autoAssets ( string $indexScript, string $indexStyle, $assetsDirectory = "assets/" ) {
		// Get config from .env
		$viteProxy = !!Nano::getEnv("NANO_VITE_PROXY", false);
		$assetsPath = Nano::getBase().$assetsDirectory;
		// Read cache buster version
		Nano::loadAppData("version", "version", "txt", "0");
		$cacheBuster = Nano::getAppData("version");
		// Inject assets
		self::addViteAssets(
			$viteProxy,
			// No style to load with the proxy, because index.ts(x) loads the index.less
			$indexScript, $viteProxy ? false : $indexStyle,
			$assetsPath, $cacheBuster,
			// Place assets in footer because we have a pre-loader
			'footer', 'footer'
		);
		return $viteProxy;
	}
}