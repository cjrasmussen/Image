<?php

namespace cjrasmussen\Image;

use Mimey\MimeTypes;

class Create
{
	/**
	 * Create an image resource from the specified file
	 *
	 * @param string $path
	 * @param string|null $type
	 * @return false|\GdImage|resource
	 */
	public static function imageResourceFromPath(string $path, ?string $type = null)
	{
		if (!$type) {
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			if ($extension) {
				$mimeTypes = new MimeTypes();
				$type = $mimeTypes->getMimeType($extension);
			}
		}

		if (in_array($type, ['image/jpg', 'image/jpeg'])) {
			$src = imagecreatefromjpeg($path);
		} elseif ($type === 'image/gif') {
			$src = imagecreatefromgif($path);
		} else {
			$src = imagecreatefrompng($path);
		}

		return $src;
	}

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