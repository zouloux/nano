<?php

namespace Nano\core;


class URL
{

	/**
	 * Removes the URL scheme (including '://') from a given URL string.
	 * If the URL does not have a scheme, it returns the URL as-is.
	 *
	 * @param string $href The URL from which the scheme should be removed.
	 *
	 * @return string The URL without the scheme.
	 */
	static function removeSchemeFromHref ( string $href ): string {
		return (
			stripos($href, '://') === false ? $href
			: substr($href, stripos($href, '://') + 3, strlen($href))
		);
	}

	/**
	 * Removes the base part of a given URL, including the scheme and host.
	 * If the base is not found in the URL, it returns the URL as-is.
	 *
	 * @param string $href The full URL from which the base should be removed.
	 * @param string $base The base URL to remove from the full URL.
	 *
	 * @return string The URL with the base removed.
	 */
	static function removeBaseFromHref ( string $href, string $base ): string {
		$href = self::removeSchemeFromHref($href);
		$base = self::removeSchemeFromHref($base);
		if ( stripos($href, $base) !== false )
			return substr($href, stripos($href, '/', strlen($base) - 1));
		else
			return $href;
	}

	/**
	 * Extracts the protocol and host part of a URL, discarding any path, query, or fragment.
	 * Example: Given "https://example.com/path?query#fragment", it will return "https://example.com".
	 *
	 * @param string $href The full URL from which the host part should be extracted.
	 *
	 * @return string The protocol and host part of the given URL.
	 */
	static function extractHost ( string $href ): string {
		if ( stripos($href, '://') === false )
			return $href;
		$split = explode("/", $href, 4);
		if ( count($split) >= 4 )
			array_pop($split);
		return implode("/", $split);
	}
}
