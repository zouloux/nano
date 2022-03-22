<?php

use Nano\core\Nano;
use Nano\debug\NanoDebug;
use Nano\helpers\NanoUtils;

// ----------------------------------------------------------------------------- STRINGS

/**
 * STRING
 * ------
 * Process a string with Sanitize and Templates.
 * Can return
 * @param string|int|float|bool|null $string String to process.
 * @param int|bool $sanitize If true or 1, will sanitize output (htmlentities with quotes)
 * @param array $templateVars If this array is not empty, will use UtilsHelper::quickMustache to process template variables with {{var}}.
 */
function S ( string|int|float|bool|null $string, int|bool $sanitize = true, array $templateVars = [] ) {
	// Convert numbers and boolean to string
	if ( (is_numeric($string) || is_bool($string)) && !is_string($string) )
		$string = (string) $string;
	// Mustache string if we have template vars
	if ( !empty($templateVars) && is_string($string) )
		$string = NanoUtils::stache( $string, $templateVars );
	// Return sanitized string if needed
	return (
		// Empty string if null after default
		is_null( $string ) ? ""
		// Sanitize html chars and quotes
		: ( $sanitize ? htmlentities( $string, ENT_QUOTES ) : $string )
	);
}

/**
 * VARIABLE
 * --------
 * Use a variable with its path.
 * TODO : DOC
 */
function V ( string $key = null, mixed $default = null, int|bool $sanitize = false, array $templateVars = [] ) {
	$value = Nano::$renderer->getThemeVariables( $key ) ?? $default;
	if ( is_array($value) ) return $value;
	// Sanitize only if needed. Will force null values to be "".
	return ($sanitize || !empty($templateVars)) ? S( $value, $sanitize, $templateVars ) : $value;
}

/**
 * DATA
 * ----
 * Get an injected static data with its path.
 * Data are injected with Nano::injectAppData();
 */
function D ( string $key = null, mixed $default = null, int|bool $sanitize = false, array $templateVars = [] ) {
	$value = Nano::getAppData( $key ) ?? $default;
	if ( is_array($value) ) return $value;
	// Sanitize only if needed. Will force null values to be "".
	return ($sanitize || !empty($templateVars)) ? S( $value, $sanitize, $templateVars ) : $value;
}

// ----------------------------------------------------------------------------- STRING MANIPULATIONS

/**
 * TEMPLATE
 * --------
 * Render another template
 */
function T ( string $templateName, array $vars = [] ) {
	Nano::$renderer->render( $templateName, $vars );
}

/**
 * JSON
 * ----
 */
function J ( mixed $object, $jsonOptions = null, int $jsonDepth = 512 ) {
	return json_encode( $object, $jsonOptions, $jsonDepth );
}

/**
 * SLUGIFY
 * -------
 */
function G ( string $string ) {
	return NanoUtils::slugify( $string );
}

// ----------------------------------------------------------------------------- PATHS & ROUTES

/**
 * Http path to a resource with base.
 * If $path is empty or null, base will be returned.
 * Base always finish with a slash.
 * Return path is sanitized.
 */
function P ( string|null $path = "" ) {
	return S( Nano::getBase().ltrim($path ?? "", "/") );
}

/**
 * ROUTE
 * -----
 * Generate an href for a named route.
 */
function R ( string $routeName, array $parameters = [], bool $absolute = false, array $getParams = [] ) {
	$route = Nano::getURL( $routeName, $parameters, $getParams );
	return ($absolute ? Nano::getAbsoluteHost() : "").$route->getRelativeUrl( !empty($getParams) );
}

// ----------------------------------------------------------------------------- ACTIONS

/**
 * ACTION
 * ------
 * Just a template shortcut to call an action on a controller.
 * Alias of Nano::action
 */
function A ( string $controllerName, string $actionName, array $arguments = [] ) {
	return Nano::action( $controllerName, $actionName, $arguments );
}

// ----------------------------------------------------------------------------- DEBUG

/**
 * VAR DUMP
 * --------
 * Dump any variable to the nano debug bar.
 * NANO_DEBUG needs to be enabled
 * NANO_DEBUG_BAR needs to be not disabled
 * Otherwise will be dumped into template with dump()
 */
function VD ( mixed $data ) {
	if ( !Nano::getEnv("NANO_DEBUG", false) ) return;
	if ( Nano::getEnv("NANO_DEBUG_BAR", true) )
		NanoDebug::dump( $data );
	else
		dump( $data );
}