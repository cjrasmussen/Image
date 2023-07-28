<?php

namespace cjrasmussen\Image;

class Create
{
	/**
	 * Create image resources with transparent background
	 * 
	 * @param int $width
	 * @param int $height
	 * @return false|\GdImage|resource
	 */
	public static function transparent(int $width, int $height)
	{
		$img = imagecreatetruecolor($width, $height);
		imagesavealpha($img, true);
		imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

		return $img;
	}
}