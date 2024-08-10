<?php

namespace Nano\templates;

use Nano\core\Env;
use Nano\core\Utils;
use Nano\debug\Debug;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Pecee\SimpleRouter\SimpleRouter;

class TemplateRenderer {

	// Template root path, relative to app root.
	protected static string $__templateRootPath;

	protected static FilesystemLoader $__loader;
	protected static Environment $__environment;

	static function init ( string $templateRootPath, string $cachePath = null ) {
		self::$__templateRootPath = $templateRootPath;
		self::$__loader = new FilesystemLoader( self::$__templateRootPath );
		self::$__environment = new Environment( self::$__loader, [
			'cache' => is_null($cachePath) || Env::get('NANO_DISABLE_TEMPLATE_CACHE') ? false : $cachePath,
			'auto_reload' => true,
			'debug' => false,
		]);
		TwigHelpers::injectHelpers( self::$__environment );
	}

	// -------------------------------------------------------------------------

	public function getTwig ():Environment { return self::$__environment; }

	public function injectHelpers ( callable $handler ) {
		$handler( self::$__environment );
	}

	// ------------------------------------------------------------------------- THEME VARS

	// Theme variables, init first with empty array
	protected static array $__themeVariables = [];

	/**
	 * Get theme variables array.
	 */
	public static function getThemeVariables ( string $key = null ) {
		return (
			// No key, return root data for current template index
			is_null( $key ) ? self::$__themeVariables
			// Otherwise, traverse with dot notation key
			: Utils::dotGet( self::$__themeVariables, $key )
		);
	}

	/**
	 * Inject a value into root scoped vars. Will merge arrays and add strings / numbers.
	 * This will use Utils::dotAdd.
	 */
	public static function addThemeVariable ( string $key, mixed $value ) {
		if ( is_numeric($value) || is_string($value) || is_array($value) )
			Utils::dotAdd( self::$__themeVariables, $key, $value );
		else
			Utils::dotSet( self::$__themeVariables, $key, $value );
	}

	// ------------------------------------------------------------------------- THEME HANDLERS

	protected static array $__themeHandlers = [];

	public static function addThemeHandler ( string $name, callable $handler ) {
		self::$__themeHandlers[ $name ] = $handler;
	}

	public static function callThemeHandler ( string $name, ...$arguments ) {
		$handlers = self::$__themeHandlers;
		if ( !isset($handlers[$name]) )
			throw new \Exception("TemplateRenderer::callThemeHandler // Handler $name does not exist.");
		return $handlers[$name](...$arguments);
	}

	// ------------------------------------------------------------------------- CALLBACKS

	protected static array $__beforeViewHandlers = [];
	protected static array $__processRenderStreamHandlers = [];

	public static function onBeforeView ( Callable $handler ) {
		self::$__beforeViewHandlers[] = $handler;
	}

	/**
	 * Filter rendered stream before its sent to the browser.
	 * @param callable $handler
	 * @return void
	 */
	public static function onProcessRenderStream ( Callable $handler ) {
		self::$__processRenderStreamHandlers[] = $handler;
	}

	// ------------------------------------------------------------------------- RENDER

	/**
	 * Render a template to a string.
	 * First render will be returned as string, other renders from first render
	 * will be echoed to be injected into the first render stream.
	 * @param string $templateName Path to template, without extension,
	 *                               starting from template root path.
	 *                               Ex : "pages/login"
	 *                               $templateName will be sanitized.
	 * @param array $vars Vars to give to template along with App::$globalVars.
	 * @param array $options ["return" => false, "debug" => true, "code" => 200]
	 * @return array|false|mixed|string|string[]|null
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public static function render ( string $templateName, array $vars = [], array $options = [] ) {
		$options = [
			"return" => false,
			"debug" => true,
			"code" => 200,
			...$options,
		];
		// Inject template name
		$vars['templateName'] = $templateName;
		// Inject back current theme variables
		$vars = array_merge( self::$__themeVariables, $vars );
		// Store theme variables for root template
		self::$__themeVariables = $vars;
		// Before render middleware
		foreach ( self::$__beforeViewHandlers as $f ) {
			$r = $f( $templateName, $vars );
			if ( is_array($r) )
				$vars = $r;
		}
		// Set HTTP status
		SimpleRouter::response()->httpCode( $options['code'] );
		// Load template
		$profiling = Debug::profile("Rendering template $templateName");
		try {
			$template = self::$__environment->load( $templateName.'.twig');
		}
		// TODO
		catch ( \Exception $e ) {
			dump("TEMPLATE NOT FOUND");
			dump($e);
			exit;
		}
		// Render with twig and filter it
		try {
			$stream = $template->render( self::$__themeVariables );
		}
		// TODO
		catch ( \Exception $e ) {
			dump("TEMPLATE ERROR");
			dump($e);
			exit;
		}
		// Render with twig and filter it
		$profiling();
		$stream = self::filterCapturedStream( $stream, $options['debug'] );
		if ( $options['return'] )
			return $stream;
		print $stream;
		exit;
	}

	// Filter stream with AppController and minify it if needed
	protected static function filterCapturedStream ( string $stream, bool $injectDebug = true ) {
		$profiling = Debug::profile("Filter captured stream");
		// Call middleware and filter captured stream
		foreach ( self::$__processRenderStreamHandlers as $handler )
			$stream = $handler( $stream, $injectDebug );
		// Minify output if configured in env
		if ( Env::get("NANO_MINIFY_OUTPUT") )
			$stream = Utils::minifyHTML( $stream );
		// Inject debugger
		$profiling();
		if ( $injectDebug && Env::get("NANO_DEBUG") && Env::get("NANO_DEBUG_BAR", true) )
			$stream .= Debug::render();
		return $stream;
	}
}