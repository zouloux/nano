<?php

namespace Nano\core;

use Pecee\Http\Input\InputHandler;
use Pecee\Http\Response;
use Pecee\Http\Url;
use Pecee\SimpleRouter\Route\ILoadableRoute;
use Pecee\SimpleRouter\SimpleRouter;

trait Nano_routes {
	// ------------------------------------------------------------------------- ROUTE & RESPONSE UTILS
	// Implementation status : ~90%

	/**
	 * Get and URL to a named route. Route need to be named with ->setName( $name );
	 * Ex : Nano::getRoute("gallery", ["id" => 12, "slug" => "birds"], ["test" => true);
	 * Can generate a route going to : "/gallery/12-birds.html?test=true"
	 * Note : You ca use R() from helpers to generate a string and not a route object.
	 * @param string $routeName Name of route
	 * @param array $parameters List of parameters as associative array. Need to match requrest parameters.
	 * @param array $getParams Added get parameters to the end of the URL.
	 * @return Url
	 */
	static function getURL ( string $routeName, array $parameters = [], array $getParams = [] ): Url {
		return SimpleRouter::getUrl($routeName, $parameters, $getParams);
	}

	/**
	 * Get current matching Route.
	 * @return ILoadableRoute|null
	 */
	static function getCurrentRoute (): ?ILoadableRoute {
		return SimpleRouter::request()->getLoadedRoute();
	}

	/**
	 * Redirect user to an URL.
	 * Will exit code. TODO : ExitPoint * https://stackoverflow.com/questions/52412606/how-do-i-declare-that-a-function-dies-so-it-is-detected-by-phpstorm
	 * @param string $url Absolute URL to redirect to. Use Nano::getURL to redirect to an app endpoint.
	 * @param int|null $code Redirect HTTP Code. Default is not redirect code.
	 * @param Response|null $response
	 * @return void
	 */
	static function redirect ( string $url, int $code = null, Response $response = null ) {
		$response ??= SimpleRouter::response();
		$response->redirect( $url, $code );
	}

	/**
	 * Get inputs from form request.
	 * @return InputHandler
	 */
	static function getInputs (): InputHandler {
		return SimpleRouter::request()->getInputHandler();
	}

	/**
	 * Echo some json to the browser.
	 * Will alter headers
	 * Will exit code. TODO : ExitPoint
	 * @param mixed $object Object to return as JSON.
	 * @param int $code HTTP code, default is OK 200.
	 * @param Response|null $response
	 * @param int|null $jsonOptions
	 * @param int $jsonDepth
	 * @return void
	 */
	static function json ( mixed $object, int $code = 200, Response $response = null, int $jsonOptions = null, int $jsonDepth = 512 ) {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->json( $object, $jsonOptions, $jsonDepth );
	}

	/**
	 * Print some lines to the browser.
	 * Will exit code. TODO : ExitPoint
	 * @param string|array $lines String or array of lines to return as text.
	 * @param int $code HTTP code, default is OK 200.
	 * @param Response|null $response
	 * @param string $contentType
	 * @return void
	 */
	static function raw ( string|array $lines, int $code = 200, Response $response = null, string $contentType = "text/plain" ) {
		$response ??= SimpleRouter::response();
		$response->httpCode( $code );
		$response->header("Content-Type: $contentType; charset=utf-8");
		print (is_array($lines) ? implode("\n", $lines) : $lines);
	}

	/**
	 * Will return $data as JSON if the GET parameters "json" is set to 1 or true.
	 * If $data is null, will return $notFoundData as JSON with a 404 http code.
	 * Will exit if returned JSON. Will return false otherwise
	 * @param mixed $data Data to convert in JSON
	 * @param mixed $notFoundData Returned data with 404 http code if $data is null.
	 * @param string $getKey $_GET parameter to return JSON.
	 * @return bool
	 */
	static function routeAsJSON ( $data, $notFoundData, $getKey = "json" ) {
		if ( isset($_GET[$getKey]) && ($_GET[$getKey] == "1" || $_GET[$getKey] == "true" || $_GET[$getKey] == "") ) {
			if ( is_null($data) )
				Nano::json( $notFoundData, 404 );
			else
				Nano::json( $data );
		}
		return false;
	}
}
