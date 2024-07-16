<?php

use Nano\core\App;
use Pecee\SimpleRouter\SimpleRouter;

App::onNotFound("/api", function () {
	App::jsonNotFound();
});

SimpleRouter::group(["prefix" => "/api/1.0"], function () {
	SimpleRouter::get("/test", function () {
		App::json([
			"success" => true,
		]);
	});
});