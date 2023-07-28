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
	public static function applyArch(&$img, int $magnitude): void
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
	 * Set the opacity of an image resource
	 *
	 * @param resource $img
	 * @param int $opacity
	 */
	public static function setOpacity(&$img, int $opacity): void
	{
		$w = imagesx($img);
		$h = imagesy($img);
		imagealphablending($img, false);

		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
				$color['alpha'] = (127 - round((127 - $color['alpha']) * ($opacity / 100)));
				imagesetpixel($img, $x, $y,
					imagecolorallocatealpha($img, $color['red'], $color['green'], $color['blue'], $color['alpha']));
			}
		}
	}

	/**
	 * @param int $x
	 * @param int $magnitude
	 * @param int $width
	 * @return int
	 */
	private static function calculateArchHeight(int $x, int $magnitude, int $width): int
	{
		return (-1 * (($magnitude / 100) / ($width * .8)) * pow(($x - ($width / 2)), 2));
	}
}