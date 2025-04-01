<?php

namespace Nano\templates;

use Nano\core\App;
use Nano\core\Env;
use Nano\core\Utils;
use Nano\helpers\FileSystem;
use Pecee\SimpleRouter\SimpleRouter;

class LayoutManager
{
	// --------------------------------------------------------------------------- CHARSET

	const CHARSET_UTF8 = "utf-8";

	protected static string $__charset = "";
	public static function getCharset () {
		return self::$__charset;
	}
	public static function setCharset ( string $charset ) {
		self::$__charset = $charset;
	}

	// --------------------------------------------------------------------------- HTML LANG

	protected static string $__htmlLang = "";
	public static function getHTMLLang () {
		return self::$__htmlLang;
	}
	public static function setHTMLLang ( string $htmlLang ) {
		self::$__htmlLang = $htmlLang;
	}

	// --------------------------------------------------------------------------- HTML CLASSES

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

	// --------------------------------------------------------------------------- VIEWPORT

	const VIEWPORT_FIXED = "width=device-width, initial-scale=1";
	const VIEWPORT_FIXED_NO_SCALE = "width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=0";

	protected static string $__viewport = "";
	public static function getViewport () {
		return self::$__viewport;
	}
	public static function setViewport ( string $viewport ) {
		self::$__viewport = $viewport;
	}

	// --------------------------------------------------------------------------- META DATA

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
		self::overrideMetaKey( $metaData, "description" );
		self::overrideMetaKey( $metaData, "shareTitle" );
		self::overrideMetaKey( $metaData, "shareDescription" );
		self::overrideMetaKey( $metaData, "shareImage" );
	}

	protected static function overrideMetaKey ( $metaData, $key ) {
		if ( isset($metaData[$key]) && $metaData[$key] )
			self::$__metaData[$key] = $metaData[$key];
	}

	// --------------------------------------------------------------------------- VIEWPORT

	protected static string $__title = "";
	public static function getTitle () {
		return self::$__title;
	}

	/**
	 * Set title tag.
	 * @param string|null $pageTitle Current page title.
	 * @param string|null $siteName Site name, if you need to template it. To disable templating, send null.
	 * @param string $titleTemplate Use this template to combine site name and page name
	 * @return void
	 */
	public static function setTitle ( string $pageTitle = null, string $siteName = null, string $titleTemplate = "{{site}} - {{page}}") {
		// No site name, no templating
		if ( is_null($siteName) )
			self::$__title = $pageTitle;
    // No title, no templating
    else if ( is_null($pageTitle) )
      self::$__title = $siteName;
		// Generate page title from site name and template
		else {
			self::$__title = Utils::stache($titleTemplate, [
				"site" => $siteName,
				"page" => $pageTitle,
			]);
		}
	}

	// --------------------------------------------------------------------------- ICONS

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

	// --------------------------------------------------------------------------- APP

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

	// --------------------------------------------------------------------------- FONT PRELOAD

	protected static array $__fontPreloads = [];

	/**
	 * Add a font file to be preloaded.
	 * @param string $path Path to the font resource. Can be woff or woff2.
	 * @return void
	 */
	public static function addFondPreload ( $path ) {
		self::$__fontPreloads[] = $path;
	}

	// --------------------------------------------------------------------------- RENDER META TAGS

  protected static function fixBasePath ( string $path ) : string {
    if ( str_starts_with($path, "/") && !str_starts_with($path, "//") )
      return App::getAbsolutePath( $path );
    else
      return $path;
  }

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
    // Share title and description fallbacks to page title and description
    $shareTitle = self::$__metaData["shareTitle"] ?? self::$__title ?? "";
    $shareDescription = self::$__metaData["shareDescription"] ?? self::$__metaData["description"] ?? "";
		// --- CHARSET
		if ( self::$__charset )
			$buffer[] = "<meta charset=\"".htmlspecialchars(self::$__charset)."\" />";
		// --- VIEWPORT
		if ( self::$__viewport )
			$buffer[] = "<meta name=\"viewport\" content=\"".htmlspecialchars(self::$__viewport)."\" />";
		// --- TITLE
		$buffer[] = "<title>".htmlspecialchars(self::$__title)."</title>";
		// --- DESCRIPTION
		if ( isset(self::$__metaData["description"]) && self::$__metaData["description"] )
			$buffer[] = "<meta name=\"description\" content=\"".htmlspecialchars(self::$__metaData["description"])."\">";
		// --- OG TITLE
		if ( !empty($shareTitle) )
			$buffer[] = "<meta property=\"og:title\" content=\"".htmlspecialchars($shareTitle)."\" />";
		// --- OG DESCRIPTION
		if ( !empty($shareDescription) )
			$buffer[] = "<meta property=\"og:description\" content=\"".htmlspecialchars($shareDescription)."\" />";
		// --- OG IMAGE
		if ( isset(self::$__metaData["shareImage"]) && self::$__metaData["shareImage"] )
			$buffer[] = "<meta property=\"og:image\" content=\"".htmlspecialchars(self::fixBasePath(self::$__metaData["shareImage"]))."\" />";
		// --- FAVICON 32
		if ( isset(self::$__icons[0]) && self::$__icons[0] )
			$buffer[] = "<link rel=\"icon\" type=\"image/png\" href=\"".htmlspecialchars(self::fixBasePath(self::$__icons[0]))."\" />";
		// --- ICON 1024
		if ( isset(self::$__icons[1]) && self::$__icons[1] )
			$buffer[] = "<link rel=\"apple-touch-icon\" type=\"image/png\" href=\"".htmlspecialchars(self::fixBasePath(self::$__icons[1]))."\" />";
		// --- WEB APP CAPABLE
		if ( self::$__appTheme )
			$buffer[] = "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\" />";
		// --- WEB APP TITLE
		if ( isset(self::$__appTheme["title"]) && self::$__appTheme["title"] )
			$buffer[] = "<meta name=\"apple-mobile-web-app-title\" content=\"".htmlspecialchars(self::$__appTheme["title"])."\" />";
		// --- WEB APP IOS TITLE BAR
		if ( isset(self::$__appTheme["titleBar"]) && self::$__appTheme["titleBar"] && self::$__appTheme["titleBar"] != "none" )
			$buffer[] = "<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"".htmlspecialchars(self::$__appTheme["titleBar"])."\" />";
		// --- WEB APP COLOR
		if ( isset(self::$__appTheme["color"]) && self::$__appTheme["color"] ) {
			// MICROSOFT
			$buffer[] = "<meta name=\"msapplication-config\" content=\"none\" />";
			$buffer[] = "<meta name=\"msapplication-TileImage\" content=\"".htmlspecialchars(self::$__appTheme["color"])."\" />";
			// OTHERS
			$buffer[] = "<meta name=\"theme-color\" content=\"".htmlspecialchars(self::$__appTheme["color"])."\" />";
		}
		// --- FONT PRELOADS
		if ( !empty(self::$__fontPreloads) ) {
			// https://wp-rocket.me/blog/font-preloading-best-practices/
			foreach ( self::$__fontPreloads as $href )
				// Note : we do not specify type on purpose. Browsers should be smart enough to understand file type.
				$buffer[] = "<link rel=\"preload\" as=\"font\" href=\"".htmlspecialchars($href)."\" crossorigin=\"anonymous\" />";
		}

		return (
			$returnAsArray
			? $buffer
			: implode("\n", $buffer)
		);
	}

	// --------------------------------------------------------------------------- RENDER HTML ATTRIBUTES

	public static function renderHTMLAttributes () {
		$buffer = [];
		if ( self::$__htmlLang )
			$buffer[] = "lang=\"".htmlspecialchars(self::$__htmlLang)."\"";
		if ( self::$__htmlClasses )
			$buffer[] = "class=\"".htmlspecialchars(implode(" ", self::$__htmlClasses))."\"";
		return implode(" ", $buffer);
	}

	// --------------------------------------------------------------------------- ASSETS

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
	 * Redirect assets request to vite server.
	 * Will use the current app host and add the vite dev port.
	 * Only for dev mode with NANO_VITE_PROXY, in production this behavior is disabled.
	 * @param string $assetsDirectory
	 * @param string $viteScheme
	 * @param int $vitePort
	 * @return void
	 */
	public static function autoViteRedirect (
		string $assetsDirectory = "assets/",
		string $viteScheme = "http",
		int $vitePort = 5173,
	) {
		// Get config from .env
		$viteProxy = !!Env::get("NANO_VITE_PROXY", false);
		if ( !$viteProxy )
			return;
		$assetsPath = App::getBase().$assetsDirectory;
		// Enable redirect for style resources ( map /assets to the equivalent vite server port )
		SimpleRouter::get('/assets/{path}', function ( string $path = "" ) use ($viteScheme, $assetsPath, $vitePort) {
			$abs = $viteScheme.'://'.App::getHost().':'.$vitePort.$assetsPath.$path;
			SimpleRouter::response()->redirect( $abs, 302);
		}, App::ROUTE_PARAMETER_WITH_SLASHES);
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
		string $styleLocation = "header",
		int $vitePort = 5173,
		string $viteScheme = "http"
	) {
		// If we need to use vite proxy
		if ( $viteProxy ) {
			// Get default vite proxy
			$viteProxy = (
			( $viteProxy === true || strtolower($viteProxy) == "true" || $viteProxy == "1" )
				? $viteScheme."://".$_SERVER["HTTP_HOST"].":".$vitePort."/"
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
			$path = App::$publicPath.rtrim($assetsPath, '/').'/.vite/manifest.json';
			$manifest = FileSystem::readJSON( $path );
			if ( is_null($manifest) )
				return;
			// Add entry points
			$viteMainStyle !== false && self::addStyleFile($styleLocation, $assetsPath.$manifest[$viteMainStyle]["file"]."?".$cacheBusterSuffix);
			if ( $viteMainScript !== false ) {
				// Add main JS module
        $scriptManifest = $manifest[$viteMainScript];
				self::addScriptFile($scriptLocation, $assetsPath.$scriptManifest["file"]."?".$cacheBusterSuffix, true, true);
        // Check if this js module generated css files and load them
        if ( is_array($scriptManifest["css"]) )
          foreach ( $scriptManifest["css"] as $cssFile )
            self::addStyleFile($styleLocation, $assetsPath.$cssFile."?".$cacheBusterSuffix);
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
			// Register fonts to preload from manifest
			foreach ( $manifest as $key => $entry ) {
				$path = $entry["file"] ?? "";
				$extension = strtolower( pathinfo($path, PATHINFO_EXTENSION) );
				if ( $extension === "woff" || $extension === "woff2" )
					self::addFondPreload( $assetsPath.$path."?".$cacheBusterSuffix );
			}
		}
	}

	public static function addScriptFile ( $location, $href, $module = null, $async = false, $defer = false ) {
		Utils::dotAdd( self::$__assets, $location.".scripts", [[
			"href" => $href,
			"module" => $module,
			"async" => $async,
			"defer" => $defer,
		]]);
	}

	public static function addScriptInline ( $location, $content, $module = null ) {
		Utils::dotAdd( self::$__assets, $location.".scripts", [[
			"content" => $content,
			"module" => $module,
		]]);
	}

	public static function addScriptJSONVariable ( $location, $name, $value, $jsonFlags = 0, $jsonDepth = 512 ) {
		$content = "window['$name'] = ".json_encode($value, $jsonDepth).";";
		self::addScriptInline($location, $content);
	}

	public static function addStyleFile ( $location, $href ) {
		Utils::dotSet( self::$__assets, $location.".styles", [[
			"href" => $href
		]]);
	}

	public static function addStyleInline ( $location, $content ) {
		Utils::dotAdd( self::$__assets, $location.".styles", [[
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
		$assets = Utils::dotGet( self::$__assets, $location.".".$type );
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
					$buffer[] = '<link rel="stylesheet" type="text/css" href="'.htmlspecialchars($asset["href"]).'" />';
			}
			// Generate script tags
			if ( $type === "scripts" ) {
				$arguments = ['']; // empty string so implode will always start with a space if we have more than 0 argument
				if ( is_bool($asset["module"]) )
					$arguments[] = $asset["module"] ? 'type="module"' : 'nomodule';
				if ( isset($asset["content"]) )
					$inline = $asset["content"];
				else {
					$arguments[] = 'src="'.htmlspecialchars($asset["href"]).'"';
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

	/**
	 * Autoconfigure assets for vite.
	 * Will read NANO_VITE_PROXY env to detect if we need to load assets from vite server
	 * If false or not defined, will load assets from assets directory.
	 * Will use cache busting from app data file version.txt
	 * @see LayoutManager::addViteAssets
	 * @param string $indexScript Index script file name in vite manifest
	 * @param string|bool $indexStyle Index style file name in vite manifest
	 * @param string $assetsDirectory Path to generated assets directory from base.
	 * @param string $styleLocation Style location
	 * @return bool Will return true if in dev mode.
	 * @throws \Exception
	 */
	public static function autoAssets ( string $indexScript, string|bool $indexStyle, string $assetsDirectory = "assets/", string $styleLocation = "header" ) {
		// Get config from .env
		$viteProxy = !!Env::get("NANO_VITE_PROXY", false);
		$assetsPath = App::getBase().$assetsDirectory;
		// Inject assets
		self::addViteAssets(
			$viteProxy,
			// No style to load with the proxy, because index.ts(x) loads the index.less
			$indexScript, $viteProxy ? false : $indexStyle,
			$assetsPath, self::$cacheBuster,
			// Place assets in footer because we have a pre-loader
			'footer', $styleLocation
		);
		return $viteProxy;
	}

	public static $cacheBuster = "0";

	// --------------------------------------------------------------------------- UMAMI

	/**
	 * Add Umami Tracker
	 * @param string $umamiCode
	 * @param string $umamiEndpoint
	 * @param string $location
	 * @return void
	 */
	public static function setUmamiCode ( string $umamiCode, string $umamiEndpoint = "https://analytics.umami.is/script.js", string $location = "header" ) {
		LayoutManager::addScriptInline($location, implode(";", [
			"var script = document.createElement('script')",
			"script.async = true",
			"script.dataset.websiteId = '".htmlspecialchars($umamiCode)."'",
			"script.src='".addslashes($umamiEndpoint)."'",
			"document.getElementsByTagName('head')[0].appendChild(script)"
		]));
	}
}
