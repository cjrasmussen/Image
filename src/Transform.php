<?php

namespace cjrasmussen\Image;

class Transform
{
	/**
	 * Vertically arch an image resource
	 *
	 * Useful for text images
	 *
	 * @param $img
	 * @param int $magnitude
	 * @return void
	 */
	public static function applyArch(&$img, $magnitude): void
	{
		$src_w = imagesx($img);
		$src_h = imagesy($img);
		
		$dst_h = $src_h - self::calculateArchHeight(0, $magnitude, $src_w);
		$tmp = Create::transparent($src_w, $dst_h);
		
		for ($x = 0; $x < $src_w; $x++) {
			$y = self::calculateArchHeight($x, $magnitude, $src_w);
			imagecopyresampled($tmp, $img, $x, abs($y), $x, 0, 1, $src_h, 1, $src_h);
		}

		$img = $tmp;
	}

	/**
	 * @param int $x
	 * @param int $magnitude
	 * @param int $width
	 * @return int
	 */
	private static function calculateArchHeight($x, $magnitude, $width): int
	{
		return (-1 * (($magnitude / 100) / ($width * .8)) * pow(($x - ($width / 2)), 2));
	}
}