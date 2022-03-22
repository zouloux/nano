<?php

namespace Nano\renderers;

use Nano\core\Nano;
use Nano\debug\NanoDebug;
use Nano\helpers\NanoUtils;
use PHPMailer\PHPMailer\Exception;

class AbstractRenderer
{
	// ------------------------------------------------------------------------- INIT

	// Template root path, relative to app root.
	protected string $_templateRootPath;

	/**
	 * Relayed from concrete classes.
	 * @param string $templateRootPath Template root path, relative to app root.
	 */
	public function __construct ( string $templateRootPath = "app/views" ) {
		$profiling = NanoDebug::profile("Init rendering layer");
		$this->_templateRootPath = $templateRootPath;
		$this->init();
		$profiling();
	}

	// Override init with concrete class
	protected function init () {}

	// ------------------------------------------------------------------------- THEME VARS

	// Theme variables, init first with empty array
	protected array $_themeVariables = [];

	/**
	 * Get theme variables array.
	 */
	public function getThemeVariables ( string $key = null ) {
		return (
			// No key, return root data for current template index
			is_null( $key ) ? $this->_themeVariables
			// Otherwise, traverse with dot notation key
			: NanoUtils::dotGet( $this->_themeVariables, $key )
		);
	}

	/**
	 * Inject a value into root scoped vars. Will merge arrays and add strings / numbers.
	 * This will use NanoUtils::dotAdd.
	 */
	public function addThemeVariable ( string $key, mixed $value ) {
		NanoUtils::dotAdd( $this->_themeVariables, $key, $value );
	}

	// ------------------------------------------------------------------------- RENDER

	// Need to be overridden by concrete
	public function render ( string $templateName, array $vars = [] ) {
		throw new Exception("AbstractRenderer.render // Do not use abstract renderer directly, or override render() method in concrete class.");
	}

	// Filter stream with AppController and minify it if needed
	protected function filterCapturedStream ( string $stream ) {
		$profiling = NanoDebug::profile("Filter captured stream");
		// Minify output if configured in env
		if ( Nano::getEnv("NANO_MINIFY_OUTPUT") )
			$stream = NanoUtils::minifyHTML( $stream );
		// Call middleware and filter captured stream
		$filteredCapturedStream = Nano::action("App", "processRenderStream", [$stream], false);
		// Inject debugger
		$profiling();
		if ( Nano::getEnv("NANO_DEBUG") && Nano::getEnv("NANO_DEBUG_BAR", true) )
			$stream .= NanoDebug::render();
		return $filteredCapturedStream ?? $stream;
	}
}