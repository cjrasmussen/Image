<?php

namespace cjrasmussen\Image;

class Resize
{
	public const int BlueskyThumbWidth = 1000;
	public const int BlueskyThumbHeight = 1000;
	public const int BlueskyThumbMaxFileSize = 976560;

	/**
	 * @param string $path
	 * @return object{contents: string, mimeType: string}
	 */
	public static function resizeForBlueskyThumbnail(string $path): object
	{
		$quality = 100;
		$outputMimeType = 'image/jpeg';

		$file = tempnam(sys_get_temp_dir(), 'bsky');

		$src = new Image($path);
		$dst = new Image(self::BlueskyThumbWidth, self::BlueskyThumbHeight);

		$src->fitToImage($dst);

		do {
			$src->saveFile($file, $outputMimeType, $quality);

			$filesize = filesize($file);
			$quality--;
		} while ((!$filesize) || ($filesize > self::BlueskyThumbMaxFileSize));

		$contents = file_get_contents($file);

		unlink($file);

		return (object)[
			'contents' => $contents,
			'mimeType' => $outputMimeType,
		];
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
