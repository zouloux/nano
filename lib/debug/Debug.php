<?php

namespace Nano\debug;

use Nano\core\App;
use Nano\core\Env;
use Nano\templates\TemplateRenderer;
use Pecee\SimpleRouter\SimpleRouter;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * TODO :
 * - Obfuscate passwords automatically
 * - Monitor DB adapter and DB requests
 * - Monitor Cache objects and button to clear
 */

class Debug
{
	// ------------------------------------------------------------------------- INIT

	static function init ()
	{
		// Start application profiling
		Debug::profile("App", true);

		if ( Env::get('NANO_DEBUG_BAR') ) {
			// Listen for debug bar assets and expose them to browser
			SimpleRouter::get('/assets/debug-bar', function ( $match ) {
				// Load js and css only
				$extension = explode(".", $match)[1];
				if ( $extension != "js" && $extension != "css" ) {
					SimpleRouter::response()->httpCode(404);
					return "Not found";
				}
				return file_get_contents( __DIR__."/debug-bar.".$extension );
			})->setMatch('/\/assets\/debug\-bar\.([\w]+)/is'); // FIXME -> Do a PR to allow dots in URL with a param
		}
	}

	// ------------------------------------------------------------------------- DUMP

	/**
	 * Dump something to the debug bar.
	 * Will be visible only if NANO_DEBUG_BAR is enabled.
	 */
	static public function dump ( mixed $data ) {
		self::$_dumps[] = [
			'stack' => debug_backtrace(),
			'value' => $data,
		];
	}

	static function consoleDump ( $value ) {
		if ( is_array($value) || is_object($value) )
			error_log( json_encode( $value ) );
		else
			error_log( $value );
	}

	// ------------------------------------------------------------------------- PROFILE

	protected static array $__profiles = [];

	public static function getProfiles () {
		return self::$__profiles;
	}

	static public function profile ( string $name, bool $forceProfiling = false )
	{
		// If not profiling, return a noop to avoid calling undefined
		if ( !$forceProfiling && !Env::get("NANO_PROFILE", false) )
			return function () {};
		self::$__profiles[ $name ] = [ microtime( true ) ];
		// Return the stop function to avoid having to repeat the $name
		return function ( $discard = false ) use ( $name ) {
			if ( $discard )
				unset( self::$__profiles[ $name ] );
			else
				self::profileStop( $name );
		};
	}

	static public function profileStop ( string $name )
	{
		// Silently fail if we never started this profile.
		// Profiling should not halt code.
		if ( !isset(self::$__profiles[ $name ]) )
			return null;
		self::$__profiles[ $name ][1] = microtime( true );
	}

	// ------------------------------------------------------------------------- ADD CUSTOM TAB

	protected static array $__customTabs = [];
	static function addCustomTab ( string $title, callable $profileHandler )
	{
		self::$__customTabs[] = [ $title, $profileHandler ];
	}

	// ------------------------------------------------------------------------- RENDER

	protected static array $_dumps = [];

	protected static HtmlDumper $__dumper;

	protected static VarCLoner $__cloner;

	static function dumpToString ( mixed $data ) {
		return Debug::$__dumper->dump(Debug::$__cloner->cloneVar($data), true);
	}

	static protected function tabButton ($name, $class = 'tabButton'):string {
		return "		<button class='DebugBar_$class'>$name</button>";
	}
	static protected function tabContent ($content):string {
		return implode("\n", [
			"		<div class='DebugBar_tabContent'>",
			$content,
			"		</div>",
		]);
	}

	static public function render () {
		// Stop application total profiling
		Debug::profileStop("App");
		Debug::profileStop("Responder");
		// Init var dumper as string output
		Debug::$__dumper = new HtmlDumper();
		Debug::$__cloner = new VarCloner();
		Debug::$__cloner->addCasters( ReflectionCaster::UNSET_CLOSURE_FILE_INFO );
		// Inject CSS before to avoid a layout shift
		$base = App::getBase();
		$renderedHTML = "<link rel='stylesheet' href='{$base}assets/debug-bar.css' />";
		// Init tab bar
		$renderedHTML .= "<div class='DebugBar'>";
		$renderedHTML .= "	<div class='DebugBar_tabs'>";
		$renderedHTML .= self::tabButton('Server');
//		$renderedHTML .= self::tabButton('App data');
		$renderedHTML .= self::tabButton('Theme variables');
		$renderedHTML .= self::tabButton('Dumps');
		if ( Env::get("NANO_PROFILE", false) )
			$renderedHTML .= self::tabButton('Profiling');
		// Add custom tab title
		foreach ( self::$__customTabs as $customTab )
			$renderedHTML .= self::tabButton( $customTab[0] );
		// Resize and lock buttons
		$renderedHTML .= self::tabButton('', 'lockButton');
		$renderedHTML .= self::tabButton('↕️', 'resizeButton');
		$renderedHTML .= "	</div>";
		// Init tab contents
		$renderedHTML .= "	<div class='DebugBar_contents'>";
		$debugEnv = Env::get("NANO_DEBUG_ENV", false);
		$noDebugEnvMessage = "NANO_DEBUG_ENV needs to be enabled";
		$renderedHTML .= self::tabContent(
			self::dumpToString([
				'cookie' => $_COOKIE,
				'env' => $debugEnv ? $_ENV : $noDebugEnvMessage,
				'request' => $_REQUEST,
				'server' => $debugEnv ? $_SERVER : $noDebugEnvMessage,
				'session' => $_SESSION ?? [],
				'constants' => (
				Env::get('NANO_CONSTANTS', false)
					? get_defined_constants(1)
					: "Set env NANO_CONSTANTS=true to show them (not recommended)."
				)
			])
		);
		$renderedHTML .= self::tabContent( self::dumpToString(TemplateRenderer::getThemeVariables()) );
		$dumbBuffer = '';
		foreach ( self::$_dumps as $dump ) {
			$dumbBuffer .= "		<h3 class='DebugBar_dumpTitle'>";
			foreach ( $dump['stack'] as $key => $callBlock ) {
				if ( $key > 0 ) $dumbBuffer .='<span>';
				if ( $key > 0 ) $dumbBuffer .= ' < ';
				$dumbBuffer .= ( isset($callBlock['file']) ? basename($callBlock['file'], '.php' ).'/' : '');
				$dumbBuffer .= $callBlock['function'].(isset($callBlock['line']) ? ':'.$callBlock['line'] : '');
				if ( $key > 0 ) $dumbBuffer .='</span>';
			}
			$dumbBuffer .= "		</h3>";
			$dumbBuffer .= self::dumpToString( $dump['value'] );
		}
		$renderedHTML .= self::tabContent( $dumbBuffer );
		$renderedHTML .= "	</div>";
		// Profiling
		if ( Env::get("NANO_PROFILE", false) ) {
			$profileBuffer = "<div class='DebugBar_profiling'>";
			$appStartReference = self::$__profiles['App'][0];
			$appEndReference = self::$__profiles['App'][1];
			$appDurationReference = $appEndReference - $appStartReference;
			foreach ( self::$__profiles as $key => $profile ) {
				$start = max($profile[0], $appStartReference);
				$duration = ($profile[1] ?? $appEndReference) - $start;
				$width = $duration / $appDurationReference * 100;
				$title = $key." (".(round($duration * 10000) / 10)."ms)";
				$left = -($appStartReference - $start) / $appDurationReference * 100;
				$rightClass = ($left > 50 ? 'DebugBar_profileBar-right' : '');
				$profileBuffer .= "<div class='DebugBar_profileBar $rightClass' style='width: $width%; left: $left%'>";
				$profileBuffer .= "$title</div>";
			}
			$renderedHTML .= self::tabContent($profileBuffer.'</div>');
		}
		// Add custom tab contents
		foreach ( self::$__customTabs as $customTab )
			$renderedHTML .= self::tabContent( $customTab[1]() );
		// Close debug bar
		$renderedHTML .= "</div>";
		// Inject javascript as asynchronously
		$renderedHTML .= "<script src='{$base}assets/debug-bar.js' async defer></script>";
		return $renderedHTML;
	}
}
