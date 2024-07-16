<?php

use Nano\core\App;
use Nano\templates\TemplateRenderer;
use Pecee\SimpleRouter\SimpleRouter;

App::onNotFound("/", function () {
	TemplateRenderer::render("templates/not-found");
});

SimpleRouter::get("/", function () {
	TemplateRenderer::render("templates/home", [
		"content" => "Var from data",
	]);
});
