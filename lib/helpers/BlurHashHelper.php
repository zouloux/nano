<?php

namespace Nano\helpers;


class BlurHashHelper
{
	public static function blurHashToBase64PNG ( $blurHashArray, $punch = 1.1 ) {
		$width = $blurHashArray[0];
		$height = $blurHashArray[1];
		$pixels = \kornrunner\Blurhash\Blurhash::decode($blurHashArray[2], $width, $height, $punch);
		$image  = imagecreatetruecolor($width, $height);
		for ($y = 0; $y < $height; ++$y) {
			for ($x = 0; $x < $width; ++$x) {
				[$r, $g, $b] = $pixels[$y][$x];
				imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
			}
		}
		ob_start();
		imagepng($image);
		$contents = ob_get_contents();
		ob_end_clean();
		return "data:image/jpeg;base64," . base64_encode($contents);
	}

	public static function blurHashToBase64PNGCached ( $blurHashArray, $punch = 1.1, $disableCache = false ) {
		$cacheKey = "__blurCache__".json_encode($blurHashArray)."__".$punch;
		return Cache::define($cacheKey, function () use ( $blurHashArray, $punch ) {
			return BlurHashHelper::blurHashToBase64PNG( $blurHashArray, $punch );
		}, null, $disableCache);
	}
}