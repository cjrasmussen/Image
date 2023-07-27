<?php
namespace cjrasmussen\Image;

class Resize
{
	/**
	 * Copy an image resource onto another image resource, resizing to fit the destination dimensions keeping source scale
	 *
	 * @param resource $dst_image
	 * @param resource $src_image
	 * @param bool $overflow
	 */
	public static function fitToImage(&$dst_image, &$src_image, $overflow = false)
	{
		$src_w = imagesx($src_image);
		$src_h = imagesy($src_image);

		$dst_w = imagesx($dst_image);
		$dst_h = imagesy($dst_image);

		$dims = self::calculateFitDimensions($src_w, $src_h, $dst_w, $dst_h, $overflow);

		imagecopyresampled($dst_image, $src_image, (int)$dims['dst_x'], (int)$dims['dst_y'], (int)$dims['src_x'], (int)$dims['src_y'], (int)$dims['dst_w'], (int)$dims['dst_h'], (int)$dims['src_w'], (int)$dims['src_h']);
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
	public static function calculateFitDimensions($src_w, $src_h, $dst_w, $dst_h, $overflow = false): array
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

			$src_w = $use_width;
			$src_h = $use_height;
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

			$dst_w = $use_width;
			$dst_h = $use_height;
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
