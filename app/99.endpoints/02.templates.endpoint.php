<?php

use Nano\core\App;
//use Nano\templates\LayoutManager;
use Nano\templates\TemplateRenderer;
use Pecee\SimpleRouter\SimpleRouter;

App::onNotFound("/", function () {
	TemplateRenderer::render("templates/not-found");
});

// Start vite assets proxy in dev mode
//LayoutManager::autoViteRedirect();

SimpleRouter::get("/", function () {
	TemplateRenderer::render("templates/home", [
		"content" => "Var from data",
	]);
});
