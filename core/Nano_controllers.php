<?php

namespace Nano\core;

use Exception;
use Nano\debug\NanoDebug;

trait Nano_controllers {

	// ------------------------------------------------------------------------- CONTROLLERS & ACTIONS
	// Implementation status : 100%

	// Directories to load controllers from
	protected static array $__controllerDirectories;

	// Mandatory suffix added when loading controller classes.
	// This helps to avoid class name collision
	protected static string $__controllersSuffix;

	/**
	 * Add a directory containing Nano Controllers.
	 */
	public static function addControllerDirectory ( string $directoryRelativeToAppRoot ) {
		Nano::$__controllerDirectories[] = $directoryRelativeToAppRoot;
	}

	// List of all instantiated controllers
	protected static array $__controllers = [];

	/**
	 * Get a controller instance by its name.
	 * Controller class will be loaded and instantiated from app/controllers.
	 * File name needs to be the same as the class name. No need to extend anything.
	 * Controllers will be instantiated on demand, and only one instance will be created.
	 * @param string $controllerName "TestController" will load and instantiate class in "app/controllers/TestController"
	 * @param bool $throw Will throw an exception if anything fail (ex : controller not found). Otherwise, null will be returned.
	 * @return mixed Instance of controller if found, otherwise null.
	 * @throws Exception
	 */
	static function getController ( string $controllerName, bool $throw = true ):mixed {
		// Patch controller name by adding NanoController suffix
		// Check if this controller already has an instance registered
		if ( !isset(Nano::$__controllers[$controllerName]) ) {
			// Compute class name with suffix
			$className = $controllerName.self::$__controllersSuffix;
			// If class has not already been loaded
			if ( !class_exists($className) ) {
				$foundPath = null;
				foreach ( Nano::$__controllerDirectories as $controllerPath ) {
					$path = Nano::path($controllerPath, $className, "php");
					if ( file_exists($path) )
						$foundPath = $path;
				}
				// Do not continue if it does not exist
				if ( is_null($foundPath) ) {
					if ( !$throw ) return null;
					throw new Exception("App::action // Controller $className file not found.");
				}
				// Require this controller once (it can already be required but not instantiated)
				require_once( $foundPath );
			}
			// Class still does not exist
			if ( !class_exists($className) ) {
				if ( !$throw ) return null;
				throw new Exception("App::action // Controller $className invalid. Class not found.");
			}
			// Instantiate controller and register its instance
			$instance = new $className;
			Nano::$__controllers[ $controllerName ] = $instance;
		}
		// Target instance from cache
		return Nano::$__controllers[ $controllerName ];
	}

	/**
	 * Call an action on a controller.
	 * Will use Nano::getController internally.
	 * @param string $controllerName "TestController" will load and instantiate class in "app/controllers/TestController"
	 * @param string $actionName This is the name of the called public method on the instantiated controller.
	 * @param array $arguments List of arguments passed to this method. Needs to matched required arguments.
	 * @param bool $throw Will throw an exception if anything fail (ex : controller not found). Otherwise, null will be returned.
	 * @return false|mixed|null Return of called controller, or null.
	 * @throws Exception
	 */
	static function action ( string $controllerName, string $actionName, array $arguments = [], bool $throw = true ):mixed {
		$actionProfile = NanoDebug::profile("Action - $controllerName.$actionName");
		// Get controller instance
		$instance = Nano::getController( $controllerName, $throw );
		// Check if method exists
		if ( is_null($instance) || !method_exists($instance, $actionName) ) {
			if ( !$throw ) {
				$actionProfile();
				return null;
			}
			throw new Exception("App::action // Action ${actionName} not found on controller ${controllerName}.");
		}
		// Call method and return result. Do not catch errors
		$result = call_user_func_array( [ $instance, $actionName ], $arguments );
		$actionProfile();
		return $result;
	}
}