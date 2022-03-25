<?php

namespace Nano\renderers\twig;

use Nano\core\Nano;
use Nano\debug\NanoDebug;
use Nano\helpers\NanoUtils;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * TODO : Other NanoUtils
 * TODO : Other tests
 * TODO : Bowl Dictionary
 * TODO : Bowl Image
 */

class TwigRendererHelpers
{
	public static function injectHelpers ( Environment $twig ) {
		// --------------------------------------------------------------------- CORE UTILITY FUNCTIONS
		/**
		 * Use NanoUtils::stache
		 * {% set templateString = "Bonjour, mon nom est {{name}}, {{fullName}}" %}
		 * {{
		 *      templateString|stache([
		 *          'name' : 'Bond',
		 *          'fullName' : 'James Bond'
		 *      ])
		 * }}
		 */
		$twig->addFunction(
			new TwigFunction( 'stache', function ($string, $values) {
				return NanoUtils::stache( $string, $values );
			})
		);
		/**
		 * JSON
		 * ----
		 */
		$twig->addFunction(
			new TwigFunction( 'json', function ( mixed $object, $jsonOptions = null, int $jsonDepth = 512 ) {
				return json_encode( $object, $jsonOptions, $jsonDepth );
			})
		);
		/**
		 * SLUGIFY
		 * -------
		 */
		$twig->addFunction(
			new TwigFunction( 'slugify', function ( string $string ) {
				return NanoUtils::slugify( $string );
			})
		);
		/**
		 * DATA
		 * ----
		 * Get an injected static data with its path.
		 * Data are injected with Nano::injectAppData();
		 */
		$twig->addFunction(
			new TwigFunction( 'data', function ( string $key = null, mixed $default = null ) {
				$value = Nano::getAppData( $key ) ?? $default;
				return $value;
			})
		);
		/**
		 * Http path to a resource with base.
		 * If $path is empty or null, base will be returned.
		 * Base always finish with a slash.
		 */
		$twig->addFunction(
			new TwigFunction( 'path', function ( string|null $path = "" ) {
				return Nano::getBase().ltrim($path ?? "", "/");
			})
		);
		/**
		 * ROUTE
		 * -----
		 * Generate an href for a named route.
		 */
		$twig->addFunction(
			new TwigFunction( 'route', function ( string $routeName, array $parameters = [], bool $absolute = false, array $getParams = [] ) {
				$route = Nano::getURL( $routeName, $parameters, $getParams );
				return ($absolute ? Nano::getAbsoluteHost() : "").$route->getRelativeUrl( !empty($getParams) );
			})
		);
		/**
		 * ACTION
		 * ------
		 * Just a template shortcut to call an action on a controller.
		 * Alias of Nano::action
		 */
		$twig->addFunction(
			new TwigFunction( 'action', function (string $controllerName, string $actionName, array $arguments = []) {
				return Nano::action( $controllerName, $actionName, $arguments );
			})
		);
		/**
		 * VAR DUMP
		 * --------
		 * Dump any variable to the nano debug bar.
		 * NANO_DEBUG needs to be enabled
		 * NANO_DEBUG_BAR needs to be not disabled
		 * Otherwise will be dumped into template with dump()
		 */
		$twig->addFunction(
			new TwigFunction( 'dump', function ($data) {
				if ( !Nano::getEnv("NANO_DEBUG", false) ) return;
				if ( Nano::getEnv("NANO_DEBUG_BAR", true) )
					NanoDebug::dump( $data );
				else
					dump( $data );
			})
		);
		// --------------------------------------------------------------------- TESTS
		$twig->addTest(
			new TwigTest('string', function ($value) { return is_string($value); })
		);
		$twig->addTest(
			new TwigTest('numeric', function ($value) { return is_numeric($value); })
		);
		// --------------------------------------------------------------------- SPLIT TEXT
		$twig->addFilter(
			new TwigFilter( 'splitter', function ( $string, $type = 'br', $tag = 'span', $spanInSpan = false, $className = '', $insertBreaks = true ) {

				function twigSplitter_renderSplitterPart ( $part, $tag, $className, $spanInSpan = false ) {
					$output = "<${tag} class=\"${className}\">";
					if ($spanInSpan) $output .= '<span>';
					$output .= $part;
					if ($spanInSpan) $output .= '</span>';
					return $output."</${tag}>";
				}

				$string = str_replace("\r\n", "\n", $string);
				$string = str_replace('<br>', '<br/>', $string);
				$string = str_replace('<br />', '<br/>', $string);

				if ( $type == 'br' || $type == 'word' )
					$lines = explode('<br/>', $string);
				else if ( $type == 'nl' )
					$lines = explode("\n", $string);
				else
					throw new \Exception("Invalid splitter type${type}.");

				$outputLines = [];
				foreach ( $lines as $line ) {
					if ( $type == 'word' ) {
						$words = explode(' ', $line);
						$line = '';
						foreach ( $words as $word )
							$line .= twigSplitter_renderSplitterPart( $word, $tag, $className, $spanInSpan );
						$outputLines[] = $line;
					}
					else
						$outputLines[] = twigSplitter_renderSplitterPart( $line, $tag, $className, $spanInSpan);
				}

				return implode( $insertBreaks ? "<br/>" : '', $outputLines );
			})
		);
		// ---------------------------------------------------------------------
	}
}