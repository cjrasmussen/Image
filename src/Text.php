<?php

namespace cjrasmussen\Image;

class Text
{
	/**
	 * Return the size of the box required for the given text
	 *
	 * @param string $text
	 * @param string $font
	 * @param int $size
	 * @return array
	 */
	public static function getBoxSize(string $text, string $font, int $size): array
	{
		$font_type = strrev(explode('.', strrev($font))[0]);

		if ($font_type === 'ttf') {
			$box = imagettfbbox($size, 0, $font, $text);
		} else {
			$box = imageftbbox($size, 0, $font, $text);
		}

		$box_width = abs($box[0] - $box[2]);
		$box_height = abs($box[1] - $box[5]);

		$text = 'ABCDEF';
		if ($font_type === 'ttf') {
			$box = imagettfbbox($size, 0, $font, $text);
		} else {
			$box = imageftbbox($size, 0, $font, $text);
		}

		$default_height = abs($box[1] - $box[5]);

		return [
			'width' => $box_width,
			'height' => $box_height,
			'baseline' => $box_height - ($box_height - $default_height),
		];
	}
}