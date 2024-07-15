<?php

use Nano\templates\TemplateRenderer;
use Pecee\SimpleRouter\SimpleRouter;

SimpleRouter::get('*', function ($path) {
	echo "-> $path";
});

SimpleRouter::get("/", function () {
	TemplateRenderer::render("templates/home", [
		"content" => "Var from data",
	]);
});
