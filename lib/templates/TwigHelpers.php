<?php

namespace Nano\templates;

use Nano\core\App;
use Nano\core\Env;
use Nano\core\Utils;
use Nano\debug\Debug;
use Nano\helpers\BlurHashHelper;
use Pecee\SimpleRouter\SimpleRouter;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

function _twigSplitter_renderSplitterPart ( $part, $tag, $className, $spanInSpan = false, $insertIndexCssVar = false, $index = 0 ) {
	$output = "<$tag class=\"$className\"";
	if ( $insertIndexCssVar )
		$output .= " style=\"--index: $index\"";
	$output .= ">";
	if ($spanInSpan) $output .= '<span>';
	$output .= $part;
	if ($spanInSpan) $output .= '</span>';
	return $output."</$tag>";
}


class TwigHelpers
{
	public static function injectHelpers ( Environment $twig ) {
		// --------------------------------------------------------------------- CORE UTILITY FUNCTIONS
		/**
		 * Use Utils::stache
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
				return Utils::stache( $string, $values );
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
				return Utils::slugify( $string );
			})
		);
		/**
		 * Http path to a resource with base.
		 * If $path is empty or null, base will be returned.
		 * Base always finish with a slash.
		 */
		$twig->addFunction(
			new TwigFunction( 'path', function ( string|null $path = "" ) {
				return App::getBase().ltrim($path ?? "", "/");
			})
		);
		/**
		 * ROUTE
		 * -----
		 * Generate an href for a named route.
		 */
		$twig->addFunction(
			new TwigFunction( 'route', function ( string $routeName, array $parameters = [], array $getParams = [] ) {
				$route = SimpleRouter::getUrl($routeName, $parameters, $getParams);
				return $route->getRelativeUrl( !empty($getParams) );
			})
		);
		/**
		 * ACTION
		 * ------
		 * TODO
		 */
		$twig->addFunction(
			new TwigFunction( 'action', function (string $name, array $arguments = []) {

			})
		);
		/**
		 * LAYOUT HELPER
		 */
		$twig->addFunction(
			new TwigFunction("layoutHTMLAttributes", function () {
				return new \Twig\Markup( LayoutManager::renderHTMLAttributes(), "UTF-8" );
			})
		);
		$twig->addFunction(
			new TwigFunction("layoutMetaTags", function () {
				return new \Twig\Markup( LayoutManager::renderMetaTags(), "UTF-8" );
			})
		);
		$twig->addFunction(
			new TwigFunction("layoutAssetTags", function ($location, $type) {
				return new \Twig\Markup( LayoutManager::renderAssetTags($location, $type), "UTF-8" );
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
				if ( !Env::get("NANO_DEBUG", false) ) return;
				if ( Env::get("NANO_DEBUG_BAR", true) )
					Debug::dump( $data );
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
		// --------------------------------------------------------------------- STRING HELPERS
		$twig->addFilter(
			new TwigFilter( 'ucfirst', function ($string) {
				return ucfirst( $string );
			})
		);
		// --------------------------------------------------------------------- SPLIT TEXT
		$twig->addFilter(
			new TwigFilter( 'splitter', function ( $string, $type = 'br', $tag = 'span', $spanInSpan = false, $className = '', $insertIndexCssVar = false, $insertBreaks = true ) {

				$string = str_replace("\r\n", "\n", $string);
				$string = str_replace('<br>', '<br/>', $string);
				$string = str_replace('<br />', '<br/>', $string);

				if ( $type == 'br' || $type == 'word' )
					$lines = explode('<br/>', $string);
				else if ( $type == 'nl' )
					$lines = explode("\n", $string);
				else
					throw new \Exception("Invalid splitter type$type.");

				$outputLines = [];
				$index = 0;
				foreach ( $lines as $line ) {
					if ( $type == 'word' ) {
						$words = explode(' ', $line);
						$line = '';
						foreach ( $words as $word )
							$line .= _twigSplitter_renderSplitterPart( $word, $tag, $className, $spanInSpan, $insertIndexCssVar, $index++ );
						$outputLines[] = $line;
					}
					else
						$outputLines[] = _twigSplitter_renderSplitterPart( $line, $tag, $className, $spanInSpan, $insertIndexCssVar, $index++);
				}

				return implode( $insertBreaks ? "<br/>" : '', $outputLines );
			})
		);
		// --------------------------------------------------------------------- SHUFFLE
		// Shuffle an array
		$twig->addFunction(
			new TwigFunction('shuffle', function ($a) {
				shuffle($a);
				return $a;
			})
		);
		// --------------------------------------------------------------------- BLUR HASH
		// Convert a blurhash to a base64 png. To inline in raw HTML.
		$twig->addFilter(
			new TwigFilter("blurhash64", function ( $blurHashArray, $punch = 1.1, $disableCache = false ) {
				return BlurHashHelper::blurHashToBase64PNGCached( $blurHashArray, $punch, $disableCache );
			})
		);

		// --------------------------------------------------------------------- WP IMAGES
		function browseCompatibleFormats ( $formats, $filter = null ) {
			// Check if browser supports webp
			$supportsWebP = str_contains($_SERVER[ 'HTTP_ACCEPT' ], 'image/webp');
			// If it supports webp but the format set has no webp, disable webp filtering
			if ( $supportsWebP ) {
				$hasWebP = false;
				foreach ( $formats as $format ) {
					if ( $format['format'] === "webp" ) {
						$hasWebP = true;
						break;
					}
				}
				if ( !$hasWebP )
					$supportsWebP = false;
			}
			// Filter only webp images if the borwser support them
			$r = [];
			foreach ( $formats as $format ) {
				if ( !$supportsWebP && $format['format'] === "webp" ) continue;
				if ( $supportsWebP && $format['format'] !== "webp" ) continue;
				$r[] = is_null($filter) ? $format : $filter( $format );
			}
			return $r;
		}
		$twig->addFilter(
			new TwigFilter('imageSrcSet', function ( $image ) {
				$image = is_array($image) ? $image : $image->toArray();
				$sizes = browseCompatibleFormats($image['formats'], function ($format) {
					return parse_url( $format['href'], PHP_URL_PATH )." ".$format['width']."w";
				});
				return implode(",", $sizes);
			})
		);
		$twig->addFilter(
			new TwigFilter('imageSrc', function ( $image, string|int $size = null ) {
				$image = is_array($image) ? $image : $image->toArray();
				$formats = browseCompatibleFormats($image['formats']);
				$nearestFormat = null;
				foreach ( $formats as $format ) {
					if (
						$nearestFormat === null ||
						abs( $format['width'] - $size ) < abs( $nearestFormat['width'] - $size )
					)
						$nearestFormat = $format;
				}
				return parse_url($nearestFormat['href'], PHP_URL_PATH);
			})
		);
	}
}