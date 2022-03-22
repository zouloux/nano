<?php

namespace Nano\renderers\native;

use Exception;
use Nano\core\Nano;
use Nano\debug\NanoDebug;
use Nano\helpers\NanoUtils;
use Nano\renderers\AbstractRenderer;

require_once __DIR__."/NativeRendererHelpers.php";

class NativeRenderer extends AbstractRenderer
{
	// ----
	// On first render, process template with ob to capture rendering string
	// Keep track of render index to store scoped vars
	protected int $_renderIndex = 0;

	// Scoped variables are theme variables but scoped to a specific sub-template.
	// Those vars are given by parent template only.
	// Indexes are from $_renderIndex
	// Note that it start to index 1, (0 is in fact stored to &_themeVariables)
	protected array $_scopedVariables = [];

	/**
	 * Get theme variables array.
	 */
	public function getThemeVariables ( string $key = null ) {
		// Get scoped vars. If null or empty, use root scoped vars
		$currentScopedVars = $this->_scopedVariables[ $this->_renderIndex - 1 ] ?? [];
		// Merge with theme vars. Scoped vars override theme vars obviously.
		$currentScopedVars = array_merge( $this->_themeVariables, $currentScopedVars );
		return (
			// No key, return root data for current template index
			is_null( $key ) ? $currentScopedVars
			// Otherwise, traverse with dot notation key
			: NanoUtils::dotGet( $currentScopedVars, $key )
		);
	}

	/**
	 * Render a template to a string.
	 * First render will be returned as string, other renders from first render
	 * will be echoed to be injected into the first render stream.
	 * @param string $templateName Path to template, without extension,
	 * 							   starting from template root path.
	 * 							   Ex : "pages/login"
	 * 							   $templateName will be sanitized.
	 * @param array $vars Vars to give to template along with App::$globalVars.
	 * @return false|string|null
	 * @throws Exception If template is not found
	 */
	function render ( string $templateName, array $vars = [] ): bool|string|null {
		$profiling = NanoDebug::profile("Render $templateName");
		// Capture rendered string with ob and call middleware if first render
		// This is to differentiate between responder rendering and sub-template rendering
		$capture = false;
		if ( $this->_renderIndex == 0 ) {
			$capture = true;
			// Inject template name
			$vars['templateName'] = $templateName;
			// Inject back current theme variables
			$vars = array_merge( $this->_themeVariables, $vars );
			// Before render middleware
			Nano::action("App", "beforeView", [$templateName, &$vars], false);
			// Store theme variables for root template
			$this->_themeVariables = $vars;
		}
		else {
			// Filter template vars
			Nano::action("App", "filterTemplateVars", [$templateName, &$vars], false);
			// Store scoped vars
			$this->_scopedVariables[ $this->_renderIndex ] = $vars;
		}
		// Next render
		$this->_renderIndex ++;
		// Target template file
		$path = Nano::path($this->_templateRootPath, $templateName, "php");
		// Do not continue if it does not exist
		if ( !file_exists($path) )
			throw new Exception("App::render // Template $templateName file not found.");
		// Start capture if needed
		$capture && ob_start();
		// Load and execute it
		require( $path );
		// Go back to previous render index
		$this->_renderIndex --;
		// Not captured
		if ( !$capture ) {
			$profiling();
			return null;
		}
		// Get captured stream and filter it
		$capturedStream = ob_get_clean();
		$profiling();
		return $this->filterCapturedStream( $capturedStream );
	}
}