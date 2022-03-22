<?php

namespace Nano\core;

class AbstractAppNanoController
{
	/**
	 * Envs are not loaded
	 * Responders are not required
	 */
	function afterInit () { /* noop */ }

	/**
	 * Envs are loaded
	 * Responders are ready
	 * Router is not started
	 */
	function beforeStart () { /* noop */ }

	/**
	 * Before view is rendered from responder.
	 * ---------------------------------------
	 * Envs are loaded
	 * Responders are ready
	 * Router is started
	 */
	function beforeView ( string $templateName, array &$vars ) { /* noop */}

	/**
	 * TODO : DOC
	 * @param string $templateName
	 * @param array $vars
	 * @return void
	 */
	function filterTemplateVars ( string $templateName, array &$vars ) { /* noop */ }

	/**
	 * After view is rendered, but before it's returned to responder.
	 */
	function processRenderStream ( string $capturedStream ) {
		return $capturedStream;
	}
}