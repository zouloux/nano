<?php

use Nano\core\Nano;

class WebsiteNanoController
{
	// ----------------------------------------------------------------------------- WEBSITE RESPONDERS

	function printRobots ( array $allow = ['*'], array $disallow = [], string $sitemap = "sitemap.xml" ) {
		$host = Nano::getAbsoluteHost();
		$lines = [];
		foreach ( $allow as $a )
			$lines[] = 'Allow: '.$a;
		foreach ( $disallow as $d )
			$lines[] = 'Disallow: '.$d;
		if ( !empty($sitemap) )
			$lines[] = "Sitemap: $host/$sitemap";
		Nano::raw([
			"User-agent: *",
			...$lines,
		]);
	}

	function printSitemap ( array $pages ) {
		$stream = [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
		];
		foreach ( $pages as $page ) {
			$stream[] = '<url>';
			$stream[] = '	<loc>'.$page['href'].'</loc>';
			$stream[] = '	<priority>'.($page['priority'] ?? 1).'</priority>';
			$stream[] = '	<lastmod>'.(date('c', $page['lastModified'] ?? time())).'</lastmod>';
			$stream[] = '	<changefreq>'.($page['frequency'] ?? 'daily').'</changefreq>';
			$stream[] = '</url>';
		}
		$stream[] = '</urlset>';
		Nano::raw( $stream, 200, null, 'text/xml');
	}
}