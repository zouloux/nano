<?php

namespace Nano\renderers\twig;

use Nano\core\Nano;
use Nano\renderers\AbstractRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigRenderer extends AbstractRenderer
{
	// ------------------------------------------------------------------------- INIT

	protected FilesystemLoader $_loader;
	protected Environment $_environment;

	protected function init () {
		$this->_loader = new FilesystemLoader( $this->_templateRootPath );
		$this->_environment = new Environment( $this->_loader );
		TwigRendererHelpers::injectHelpers( $this->_environment );
	}

	public function getTwig ():Environment { return $this->_environment; }

	public function injectHelpers ( callable $handler ) {
		$handler( $this->_environment );
	}

	// ------------------------------------------------------------------------- RENDER

	/**
	 * Render a template to a string.
	 * First render will be returned as string, other renders from first render
	 * will be echoed to be injected into the first render stream.
	 * @param string $templateName Path to template, without extension,
	 *                               starting from template root path.
	 *                               Ex : "pages/login"
	 *                               $templateName will be sanitized.
	 * @param array $vars Vars to give to template along with App::$globalVars.
	 * @return array|false|mixed|string|string[]|null
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	function render ( string $templateName, array $vars = [] ) {
		// Inject template name
		$vars['templateName'] = $templateName;
		// Inject back current theme variables
		$vars = array_merge( $this->_themeVariables, $vars );
		// Store theme variables for root template
		$this->_themeVariables = $vars;
		// Before render middleware
		Nano::action("App", "beforeView", [$templateName, &$vars], false);
		// Load template
		$template = $this->_environment->load( $templateName.'.twig');
		// Render with twig and filter it
		$stream = $template->render( $vars );
		return $this->filterCapturedStream( $stream );
	}
}