<?php

namespace cjrasmussen\Image;

use cjrasmussen\Color\Convert;

class Resize
{
	public const BlueskyThumbWidth = 1000;
	public const BlueskyThumbHeight = 1000;
	public const BlueskyThumbMaxFileSize = 976560;

	/**
	 * Take an image file and return an image resource resized to the specified dimensions
	 *
	 * @param string $path
	 * @param int $dst_w
	 * @param int $dst_h
	 * @param string|null $type
	 * @param string|null $bg_hex
	 * @param int $rounded
	 * @param int|string $gutter
	 * @return resource
	 */
	public static function resize(string $path, int $dst_w, int $dst_h, ?string $type = null, ?string $bg_hex = null, int $rounded = 0, $gutter = 0)
	{
		$src = Create::imageResourceFromPath($path, $type);

		if ($gutter) {
			$src = self::addGutter($src, $gutter);
		}

		$img = imagecreatetruecolor($dst_w, $dst_h);
		imagesavealpha($img, true);

		// START WITH TRANSPARENT BACKGROUND
		$bg_color = imagecolorallocatealpha($img, 0, 0, 0, 127);
		imagefill($img, 0, 0, $bg_color);

		if ($bg_hex) {
			// ADD A DEFINED BACKGROUND COLOR
			$color = Convert::hexToRgb($bg_hex);
			$bg_color = imagecolorallocate($img, $color->R, $color->G, $color->B);

			if ($rounded) {
				$bg_w = $dst_w * 10;
				$bg_h = $dst_h * 10;

				// MAKE BACKGROUND IMAGE
				$bg_img = imagecreatetruecolor($bg_w, $bg_h);
				imagesavealpha($bg_img, true);

				$trans = imagecolorallocatealpha($bg_img, 0, 0, 0, 127);
				imagefill($bg_img, 0, 0, $trans);

				// MAKE A ROUNDED SHAPE TO FILL
				$diameter = round((($bg_w > $bg_h) ? $bg_w : $bg_h) * .26);
				$radius = round($diameter / 2);

				// FOUR CORNERS
				imagearc($bg_img, $radius, $radius, $diameter, $diameter, 180, 270, $bg_color);
				imagearc($bg_img, ($bg_w - $radius), $radius, $diameter, $diameter, 270, 0, $bg_color);
				imagearc($bg_img, ($bg_w - $radius), ($bg_h - $radius), $diameter, $diameter, 0, 90, $bg_color);
				imagearc($bg_img, $radius, ($bg_h - $radius), $diameter, $diameter, 90, 180, $bg_color);

				// FOUR LINES
				imageline($bg_img, 0, $radius, 0, ($bg_w - $radius), $bg_color);
				imageline($bg_img, $radius, 0, ($bg_h - $radius), 0, $bg_color);
				imageline($bg_img, ($bg_h - 1), $radius, ($bg_h - 1), ($bg_w - $radius), $bg_color);
				imageline($bg_img, $radius, ($bg_w - 1), ($bg_h - $radius), ($bg_w - 1), $bg_color);

				// FILL THE ENTIRE SPACE FROM THE MIDDLE
				imagefill($bg_img, round($bg_w / 2), round($bg_h / 2), $bg_color);

				// COPY BACKGROUND TO IMAGE
				imagecopyresampled($img, $bg_img, 0, 0, 0, 0, $dst_w, $dst_h, $bg_w, $bg_h);

				imagedestroy($bg_img);
			} else {
				// FILL THE ENTIRE SPACE
				imagefill($img, 0, 0, $bg_color);
			}
		}

		self::fitToImage($img, $src);

		imagedestroy($src);

		return $img;
	}

	/**
	 * Copy an image resource onto another image resource, resizing to fit the destination dimensions keeping source scale
	 *
	 * @param resource $dst_image
	 * @param resource $src_image
	 * @param bool $overflow
	 */
	public static function fitToImage(&$dst_image, &$src_image, bool $overflow = false): void
	{
		$src_w = imagesx($src_image);
		$src_h = imagesy($src_image);

		$dst_w = imagesx($dst_image);
		$dst_h = imagesy($dst_image);

		$dims = self::calculateFitDimensions($src_w, $src_h, $dst_w, $dst_h, $overflow);

		imagecopyresampled($dst_image, $src_image, (int)$dims['dst_x'], (int)$dims['dst_y'], (int)$dims['src_x'], (int)$dims['src_y'], (int)$dims['dst_w'], (int)$dims['dst_h'], (int)$dims['src_w'], (int)$dims['src_h']);
	}

	/**
	 * @param string $path
	 * @return object{contents: string, mimeType: string}
	 */
	public static function resizeForBlueskyThumbnail(string $path): object
	{
		$quality = 100;

		$file = tempnam(sys_get_temp_dir(), 'bsky');
		$src = Create::imageResourceFromPath($path);

		$dst = imagecreatetruecolor(self::BlueskyThumbWidth, self::BlueskyThumbHeight);
		self::fitToImage($dst, $src);

		do {
			imagejpeg($dst, $file, $quality);

			$filesize = filesize($file);
			$quality--;
		} while ((!$filesize) || ($filesize > self::BlueskyThumbMaxFileSize));

		$contents = file_get_contents($file);

		imagedestroy($src);
		imagedestroy($dst);
		unlink($file);

		return (object)[
			'contents' => $contents,
			'mimeType' => 'image/jpeg',
		];
	}

	/**
	 * Add a gutter to an image resource
	 *
	 * @param resource $img
	 * @param string|int $gutter - Number of pixels or percentage of source dimensions
	 * @return false|resource
	 */
	public static function addGutter($img, $gutter = '10%')
	{
		$src_w = imagesx($img);
		$src_h = imagesy($img);

		if (false !== strpos($gutter, '%')) {
			$gutter = (int)$gutter;
			$gutter_x = round($src_w * ($gutter / 100));
			$gutter_y = round($src_h * ($gutter / 100));
		} else {
			$gutter = (int)$gutter;
			$gutter_x = $src_w + $gutter;
			$gutter_y = $src_h + $gutter;
		}

		$dst_w = $src_w + ($gutter_x * 2);
		$dst_h = $src_h + ($gutter_y * 2);

		$dst = imagecreatetruecolor($dst_w, $dst_h);
		imagesavealpha($img, true);

		$bg_color = imagecolorallocatealpha($dst, 0, 0, 0, 127);
		imagefill($dst, 0, 0, $bg_color);

		imagecopyresampled($dst, $img, $gutter_x, $gutter_y, 0, 0, $src_w, $src_h, $src_w, $src_h);

		imagedestroy($img);
		return $dst;
	}

	/**
	 * Get the width, height, and scale of an image resized to fit inside a set of dimensions
	 *
	 * @param int $src_w
	 * @param int $src_h
	 * @param int $dst_w
	 * @param int $dst_h
	 * @param bool $overflow
	 * @return array
	 */
	public static function calculateFitDimensions(int $src_w, int $src_h, int $dst_w, int $dst_h, bool $overflow = false): array
	{
		$src_x = $src_y = $dst_x = $dst_y = 0;

		if ($overflow) {
			if (($src_w / $src_h) > ($dst_w / $dst_h)) {
				// TOO WIDE
				$use_width = $dst_w * ($src_h / $dst_h);
				$use_height = $src_h;
				$src_x = ($src_w - $use_width) / 2;
				$src_y = 0;
				$scale = ($use_width / $src_w) * 100;
			} elseif (($src_w / $src_h) < ($dst_w / $dst_h)) {
				// TOO TALL
				$use_width = $src_w;
				$use_height = $dst_h * ($src_w / $dst_w);
				$src_x = 0;
				$src_y = ($src_h - $use_height) / 2;
				$scale = ($use_height / $src_h) * 100;
			} else {
				$src_x = 0;
				$src_y = 0;
				$use_width = $dst_w;
				$use_height = $dst_h;
				$scale = 100;
			}

			$src_w = (int)$use_width;
			$src_h = (int)$use_height;
		} else {
			if (($src_w / $src_h) > ($dst_w / $dst_h)) {
				// TOO WIDE
				$use_width = $dst_w;
				$use_height = ($src_h / $src_w) * $dst_w;
				$dst_x = 0;
				$dst_y = ($dst_h - $use_height) / 2;
				$scale = ($use_height / $src_h) * 100;
			} elseif (($src_w / $src_h) < ($dst_w / $dst_h)) {
				// TOO TALL
				$use_width = ($src_w / $src_h) * $dst_h;
				$use_height = $dst_h;
				$dst_x = ($dst_w - $use_width) / 2;
				$dst_y = 0;
				$scale = ($use_width / $src_w) * 100;
			} else {
				$src_x = 0;
				$src_y = 0;
				$use_width = $dst_w;
				$use_height = $dst_h;
				$scale = 100;
			}

			$dst_w = (int)$use_width;
			$dst_h = (int)$use_height;
		}

		return [
			'dst_w' => $dst_w,
			'dst_h' => $dst_h,
			'src_w' => $src_w,
			'src_h' => $src_h,
			'src_x' => $src_x,
			'src_y' => $src_y,
			'dst_x' => $dst_x,
			'dst_y' => $dst_y,
			'scale' => $scale,
		];
	}
}
