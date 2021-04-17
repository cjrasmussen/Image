<?php
namespace cjrasmussen\Image;

class Resize
{
	/**
	 * Copy an image resource onto another image resource, resizing to fit the destination dimensions keeping source scale
	 *
	 * @param resource $dst_image
	 * @param resource $src_image
	 * @param int $crop
	 */
	public static function fitToImage(&$dst_image, &$src_image, $crop = 0)
	{
		$dst_w = imagesx($dst_image);
		$dst_h = imagesy($dst_image);

		$src_w = imagesx($src_image);
		$src_h = imagesy($src_image);

		if ($crop) {
			if (($src_w / $src_h) > ($dst_w / $dst_h)) {
				// TOO WIDE
				$use_width = $dst_w * ($src_h / $dst_h);
				$use_height = $src_h;
				$src_x = ($src_w - $use_width) / 2;
				$src_y = 0;
			} elseif (($src_w / $src_h) < ($dst_w / $dst_h)) {
				// TOO TALL
				$use_width = $src_w;
				$use_height = $dst_h * ($src_w / $dst_w);
				$src_x = 0;
				$src_y = ($src_h - $use_height) / 2;
			} else {
				$src_x = 0;
				$src_y = 0;
				$use_width = $dst_w;
				$use_height = $dst_h;
			}

			$src_w = $use_width;
			$src_h = $use_height;
		} else {
			if (($src_w / $src_h) > ($dst_w / $dst_h)) {
				// TOO WIDE
				$use_width = $dst_w;
				$use_height = ($src_h / $src_w) * $dst_w;
				$dst_x = 0;
				$dst_y = ($dst_h - $use_height) / 2;
			} elseif (($src_w / $src_h) < ($dst_w / $dst_h)) {
				// TOO TALL
				$use_width = ($src_w / $src_h) * $dst_h;
				$use_height = $dst_h;
				$dst_x = ($dst_w - $use_width) / 2;
				$dst_y = 0;
			} else {
				$src_x = 0;
				$src_y = 0;
				$use_width = $dst_w;
				$use_height = $dst_h;
			}

			$dst_w = $use_width;
			$dst_h = $use_height;
		}

		imagecopyresampled($dst_image, $src_image, (int)$dst_x, (int)$dst_y, (int)$src_x, (int)$src_y, $dst_w, $dst_h, $src_w, $src_h);
	}

	/**
	 * Get the width, height, and scale of an image resized to fit inside a set of dimensions
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $max_width
	 * @param int $max_height
	 * @return array
	 */
	public static function calculateFitDimensions($width, $height, $max_width, $max_height): array
	{
		$horiz_scale = $max_width / $width;
		$vert_scale = $max_height / $height;

		if (($width > $max_width) and ($height > $max_height)) {
			if ((abs($horiz_scale - 1)) > (abs($vert_scale - 1))) {
				$use_width = $max_width;
				$use_height = floor($height * $horiz_scale);
				$scale = floor($horiz_scale * 100);
			} else {
				$use_width = floor($width * $vert_scale);
				$use_height = $max_height;
				$scale = floor($vert_scale * 100);
			}
		} elseif ($width > $max_width) {
			$use_width = $max_width;
			$use_height = floor($height * $horiz_scale);
			$scale = floor($horiz_scale * 100);
		} else {
			$use_width = floor($width * $vert_scale);
			$use_height = $max_height;
			$scale = floor($vert_scale * 100);
		}

		return ['w' => $use_width, 'h' => $use_height, 's' => $scale];
	}
}
