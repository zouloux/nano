<?php

namespace Nano\core;

use Cocur\Slugify\Slugify;

class Utils {
	// ------------------------------------------------------------------------- SLUGIFY

	// Singleton instance of slugifier
	static protected Slugify $__slugifier;

	/**
	 * Slugify a string.
	 * Uses a singleton instance of Cocur\Slugify\Slugify
	 */
	static function slugify ( string $string, string $separator = "-" ) {
		self::$__slugifier ??= new Slugify();
		return self::$__slugifier->slugify( $string, $separator );
	}

	// ------------------------------------------------------------------------- DOT GET / SET / PUSH

	/**
	 * Traverse an array to get a value with dot notation.
	 * Ex : dotGet(["a" => ["b" => 1]], "a.b") == 1
	 *
	 * @param array|null $array The associative array to traverse.
	 * @param string|null $path The path to the wanted value to get.
	 *
	 * @return mixed|null value if found, else null.
	 */
	static function dotGet ( array|null &$array, string|null $path ) : mixed
	{
		// Check if our object is null
		if ( is_null( $array ) )
			return null;
		// Split the first part of the path
		$explodedPath = explode( '.', $path, 2 );
		// One element in path selector
		// Check if this element exists and return it if found
		if ( !isset( $explodedPath[ 1 ] ) )
			return $array[ $explodedPath[ 0 ] ] ?? null;
		// Nesting detected in path
		// Check if first part of the path is in object
		// Target child from first part of path and traverse recursively
		else if ( isset( $explodedPath[ 0 ] ) && isset( $array[ $explodedPath[ 0 ] ] ) )
			return self::dotGet( $array[ $explodedPath[ 0 ] ], $explodedPath[ 1 ] );
		// Not found
		else
			return null;
	}

	/**
	 * Traverse an array to set a value with dot notation.
	 *
	 * @param array|null $array The associative array to traverse.
	 * @param string $path The path to the wanted value to set.
	 * @param mixed $value The value to set at this path.
	 *
	 * @return void|null
	 */
	static function dotSet ( array|null &$array, string $path, mixed $value )
	{
		// Check if our object is null
		if ( is_null( $array ) )
			return null;
		// Split the first part of the path
		$explodedPath = explode( '.', $path, 2 );
		$key          = $explodedPath[ 0 ];
		// If we still have sub-arrays to explore
		if ( count( $explodedPath ) == 2 ) {
			// One element in path selector
			// Create sub-array if not existing
			if ( !isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) )
				$array[ $key ] = [];
			// Recursively create and inject value
			self::dotSet( $array[ $key ], $explodedPath[ 1 ], $value );
			return;
		}
		// If we are on last element, set value
		$array[ $key ] = $value;
	}

	/**
	 * Traverse an array to add a value with dot notation.
	 * Will merge arrays, concatenate strings, add numeric values.
	 * Will init undefined values with "", 0 or [] depending on $value type.
	 *
	 * @param array|null $array The associative array to traverse.
	 * @param string $path The path to the wanted value to set.
	 * @param int|float|string|array $value The value to add at this path.
	 *
	 * @return void|null
	 */
	static function dotAdd ( array|null &$array, string $path, int|float|string|array $value )
	{
		// Check if our object is null
		if ( is_null( $array ) )
			return null;
		// Split the first part of the path
		$explodedPath = explode( '.', $path, 2 );
		$key = $explodedPath[ 0 ];
		// If we still have sub-arrays to explore
		if ( count( $explodedPath ) == 2 ) {
			// One element in path selector
			// Create sub-array if not existing
			if ( !isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) )
				$array[ $key ] = [];
			// Recursively create and inject value
			self::dotAdd( $array[ $key ], $explodedPath[ 1 ], $value );
			return;
		}
		// If we are on last element, set value
		// Init value if not existing with correct type
		if ( ! isset( $array[ $key ] ) ) {
			if ( is_int( $value ) || is_float( $value ) )
				$array[ $key ] = 0;
			else if ( is_string( $value ) )
				$array[ $key ] = "";
			else if ( is_array( $value ) )
				$array[ $key ] = [];
		}
		// Add (will merge arrays or add numeric values)
		if ( is_string( $value ) )
			$array[ $key ] .= $value;
		else if ( is_numeric( $value ) )
			$array[ $key ] += $value;
		else if ( is_array( $value ) )
			$array[ $key ] = array_merge( $array[ $key ], $value );
	}

	// ------------------------------------------------------------------------- TEMPLATING

	/**
	 * Ultra simple template engine.
	 * Delimiters are double mustaches like this : {{myVar}}
	 * Compatible with nested variables thanks to dotGet
	 * Ex : quickMustache("{{a.b}}", ["a" => ["b" => "hello"]]);
	 * Will keep the placeholder if the property not found in $values.
	 *
	 * @param string $template Template string to process.
	 * @param array $values Parameters bag including variables to replace.
	 *
	 * @return mixed Templated string.
	 */
	static function stache ( string $template, array $values )
	{
		return preg_replace_callback(
			'/{{([a-zA-Z0-9.\-_]+)}}/',
			function ( $matches ) use ( $values ) {
				// Traverse the parameters bag with this path
				$traversedValue = self::dotGet( $values, $matches[ 1 ] );
				// Return the value if found, else keep the placeholder
				return is_null( $traversedValue ) ? $matches[ 0 ] : $traversedValue;
			},
			$template
		);
	}

	/**
	 * nl2br but only \n and not \\n or \r
	 * @param string $string
	 * @return string
	 */
	static function nl2br ( string $string )
	{
		return preg_replace('/(?<!\\\)\\n/', '<br>', $string);
	}

	// ------------------------------------------------------------------------- ARRAY UTILS

	/**
	 * Default options helper.
	 * Will set $defaults values into $options and return result.
	 * Will unset values to null to clean $options array.
	 *
	 * @param array $options List of options from function argument (associative array)
	 * @param array $defaults List of defaults (associative array)
	 *
	 * @return array Cleaned options with defaults.
	 */
	static function defaultOptions ( array $options, array $defaults )
	{
		$options = array_merge( $defaults, $options );
		// Remove null values
		foreach ( $options as $key => $value )
			if ( is_null( $value ) )
				unset( $options[ $key ] );
		return $options;
	}

	// ------------------------------------------------------------------------- BOOLEAN / STRING

	/**
	 * Convert booleanish input from inputs checkboxes to actual booleans
	 * @param string $input
	 * @return bool
	 */
	static function booleanInput ( string $input )
	{
		return in_array(strtolower($input), ["true", "on", "1"]);
	}

	// ------------------------------------------------------------------------- MINIFY HTML

	/**
	 * Minify html stream with a regex.
	 * From : https://stackoverflow.com/questions/5312349/minifying-final-html-output-using-regular-expressions-with-codeigniter
	 */
	static function minifyHTML ( string $stream )
	{
		$untouchedStream = $stream;
		// Remove HTML comments, but not SSI
		$stream = preg_replace('/<!--[^#](.*?)-->/s', '', $stream);
		// The content inside these tags will be spared:
		$doNotCompressTags = ['script', 'pre', 'textarea'];
		$matches = [];
		foreach ( $doNotCompressTags as $tag ) {
			$regex = "!<{$tag}[^>]*?>.*?</{$tag}>!is";
			// It is assumed that this placeholder could not appear organically in your
			// output. If it can, you may have an XSS problem.
			$placeholder = "@@<'-placeholder-$tag'>@@";
			// Replace all the tags (including their content) with a placeholder, and keep their contents for later.
			$stream = preg_replace_callback(
				$regex,
				function ($match) use ($tag, &$matches, $placeholder) {
					$matches[$tag][] = $match[0];
					return $placeholder;
				},
				$stream
			);
		}
		// Remove whitespace (spaces, newlines and tabs)
		$stream = trim(preg_replace('/[ \n\t]+/m', ' ', $stream));
		// Iterate the blocks we replaced with placeholders beforehand, and replace the placeholders
		// with the original content.
		foreach ( $matches as $tag => $blocks ) {
			$placeholder = "@@<'-placeholder-$tag'>@@";
			$placeholderLength = strlen($placeholder);
			$position = 0;
			foreach ( $blocks as $block ) {
				$position = strpos($stream, $placeholder, $position);
				if ($position === false) {
					// Do not throw but return original stream
					return $untouchedStream;
					//throw new \RuntimeException("Found too many placeholders of type $tag in input string");
				}
				$stream = substr_replace($stream, $block, $position, $placeholderLength);
			}
		}
		// Remove spaces between tags
		// FIXME : Disabled because it remove spaces inside jsons and script tags !
		/*$stream = preg_replace('/\s?<\s?/', '<', $stream);*/
		/*$stream = preg_replace('/\s?>\s?/', '>', $stream);*/
		return $stream;
	}
}
