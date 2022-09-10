<?php
namespace cjrasmussen\Image;

use cjrasmussen\Color\Convert;
use cjrasmussen\Color\General;

class Text
{
	public const VerticalAlignNone = 0;
	public const VerticalAlignBottom = 1;
	public const VerticalAlignMiddle = 2;
	public const VerticalAlignTop = 3;
	public const HorizontalAlignNone = 0;
	public const HorizontalAlignLeft = 1;
	public const HorizontalAlignCenter = 2;
	public const HorizontalAlignRight = 3;

	/**
	 * Add a block of text to an image resource
	 *
	 * @param resource $img
	 * @param string $text
	 * @param string $font
	 * @param int $size
	 * @param resource|string $color
	 * @param int $x
	 * @param int $y
	 * @param int $alignment_horizontal
	 * @param int $alignment_vertical
	 * @param int|null $max_width
	 * @param int $stroke
	 * @param resource|string|null $strokeColor
	 */
	public static function write(
		&$img,
		$text,
		$font,
		$size,
		$color,
		$x,
		$y,
		$alignment_horizontal = self::HorizontalAlignLeft,
		$alignment_vertical = self::VerticalAlignTop,
		$max_width = null,
		$stroke = 0,
		$strokeColor = null
	): void
	{
		if (General::isHexColor($color)) {
			$rgb = Convert::hexToRgb($color);
			$color = imagecolorallocate($img, $rgb->R, $rgb->G, $rgb->B);
		}

		if (($strokeColor !== null) AND (General::isHexColor($strokeColor))) {
			$rgb = Convert::hexToRgb($strokeColor);
			$strokeColor = imagecolorallocate($img, $rgb->R, $rgb->G, $rgb->B);
		}

		$font_type = strrev(explode('.', strrev($font))[0]);
		$size_original = $size;
		$stroke_original = $stroke;

		$size++;
		do {
			$size--;
			$box_size = self::getBoxSize($text, $font, $size);
			$stroke = round($stroke_original * ($size / $size_original));
			$actual_width = $box_size['width'] + ($stroke * 2);
		} while (($max_width !== null) AND ($actual_width > $max_width));

		$x = self::determineAlignmentPositionHorizontal($x, $box_size['width'], $alignment_horizontal);
		$y = self::determineAlignmentPositionVertical($y, $box_size['height'], $box_size['baseline'], $alignment_vertical);

		if (($stroke) AND ($strokeColor)) {
			if ($font_type === 'ttf') {
				self::ttfStroke($img, $size, 0, $x, $y, $color, $strokeColor, $font, $text, $stroke);
			} else {
				self::ftStroke($img, $size, 0, $x, $y, $color, $strokeColor, $font, $text, $stroke);
			}
		} elseif ($font_type === 'ttf') {
			imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
		} else {
			imagefttext($img, $size, 0, $x, $y, $color, $font, $text);
		}
	}

	/**
	 * Return the size of the box required for the given text
	 *
	 * @param string $text
	 * @param string $font
	 * @param int $size
	 * @return array
	 */
	public static function getBoxSize($text, $font, $size): array
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

	/**
	 * Writes the given text with a border into the image using TrueType fonts.
	 *
	 * @param resource $image
	 * @param int $size
	 * @param int $angle
	 * @param int $x
	 * @param int $y
	 * @param resource $textcolor
	 * @param resource $strokecolor
	 * @param string $fontfile
	 * @param string $text
	 * @param int $px
	 * @see http://johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
	 */
	private static function ttfStroke(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px): void
	{

		for ($c1 = ($x - abs($px)); $c1 <= ($x + abs($px)); $c1++) {
			for ($c2 = ($y - abs($px)); $c2 <= ($y + abs($px)); $c2++) {
				imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			}
		}

		imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
	}

	/**
	 * Writes the given text with a border into the image
	 *
	 * @param resource $image
	 * @param int $size
	 * @param int $angle
	 * @param int $x
	 * @param int $y
	 * @param resource $textcolor
	 * @param resource $strokecolor
	 * @param string $fontfile
	 * @param string $text
	 * @param int $px
	 * @see http://johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
	 */
	private static function ftStroke(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px): void
	{

		for ($c1 = ($x - abs($px)); $c1 <= ($x + abs($px)); $c1++) {
			for ($c2 = ($y - abs($px)); $c2 <= ($y + abs($px)); $c2++) {
				imagefttext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			}
		}

		imagefttext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
	}

	/**
	 * Determine the actual X coordinate for text based on the alignment
	 *
	 * @param int $x
	 * @param int $width
	 * @param int $alignment
	 * @return int
	 */
	private static function determineAlignmentPositionHorizontal($x, $width, $alignment): int
	{
		if ($alignment === self::HorizontalAlignCenter) {
			$x -= round($width / 2);
		} elseif ($alignment === self::HorizontalAlignRight) {
			$x -= $width;
		}

		return $x;
	}

	/**
	 * Determine the actual Y coordinate for text based on the alignment
	 *
	 * @param int $y
	 * @param int $height
	 * @param int $baseline
	 * @param int $alignment
	 * @return int
	 */
	private static function determineAlignmentPositionVertical($y, $height, $baseline, $alignment): int
	{
		if ($alignment === self::VerticalAlignMiddle) {
			$y += round($baseline / 2);
		} elseif ($alignment === self::VerticalAlignTop) {
			$y += $baseline;
		} else {
			$y -= $height - $baseline;
		}

		return $y;
	}
}