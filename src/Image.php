<?php

namespace cjrasmussen\Image;

use cjrasmussen\Color\ColorType\Hex;
use cjrasmussen\Color\General;
use Elephox\Mimey\MimeTypes;
use GdImage;
use RuntimeException;
use Throwable;

class Image
{
	public const int TextAlignVerticalNone = 0;
	public const int TextAlignVerticalBottom = 1;
	public const int TextAlignVerticalMiddle = 2;
	public const int TextAlignVerticalTop = 3;
	public const int TextAlignHorizontalNone = 0;
	public const int TextAlignHorizontalLeft = 1;
	public const int TextAlignHorizontalCenter = 2;
	public const int TextAlignHorizontalRight = 3;

	private ?string $originalMimeType = null;

	private GdImage $image;

	/**
	 * @param int|string|GdImage $primary - file path or image width
	 * @param int|string|null $secondary - If file path provided as $primary, optional file type. If image width provided as $primary, image height.
	 * @param bool|null $transparent - If width and height provided, flag for if the new image should be transparent
	 */
	public function __construct(int|string|GdImage $primary, int|string|null $secondary = null, ?bool $transparent = null)
	{
		if (is_string($primary) && !is_int($secondary)) {
			// CREATE FROM FILE
			$this->image = $this->createImageFromPath($primary, $secondary);
		} elseif ((is_int($primary)) && (is_int($secondary))) {
			// CREATE NEW
			$transparent = is_bool($transparent) ? $transparent : true;
			$this->image = $this->createImageFromDimensions($primary, $secondary, $transparent);
		} elseif ($primary instanceof GdImage) {
			$this->image = $primary;
		} else {
			// INVALID
			throw new RuntimeException('Invalid image definition provided.');
		}

		if (!$this->image) {
			throw new RuntimeException('Image could not be created.');
		}
	}

	public function getImage(): GdImage
	{
		return $this->image;
	}

	public function getImageWidth(): int
	{
		return (int)imagesx($this->image);
	}

	public function getImageHeight(): int
	{
		return (int)imagesy($this->image);
	}

	public function getOriginalMimeType(): ?string
	{
		return $this->originalMimeType;
	}

	public function saveFile(string $path, ?string $type = null, int $quality = -1, int $filters = -1): bool
	{
		if (!$type) {
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			if ($extension) {
				$mimeTypes = new MimeTypes();
				$type = $mimeTypes->getMimeType($extension);
			}
		}

		if (in_array($type, ['image/jpg', 'image/jpeg'])) {
			return imagejpeg($this->image, $path, $quality);
		}

		if ($type === 'image/gif') {
			return imagegif($this->image, $path);
		}

		return imagepng($this->image, $path, $quality, $filters);
	}

	public function outputFile(string $type, int $quality = -1, int $filters = -1): void
	{
		if (in_array($type, ['image/jpg', 'image/jpeg'])) {
			imagejpeg($this->image, null, $quality);
		} elseif ($type === 'image/gif') {
			imagegif($this->image);
		} else {
			imagepng($this->image, null, $quality, $filters);
		}
	}

	/**
	 * Resize the image to the specified dimensions
	 *
	 * @param int $dst_w
	 * @param int $dst_h
	 * @param string|null $bg_hex
	 * @param bool $rounded
	 * @param int|string $gutter
	 * @return self
	 */
	public function resize(int $dst_w, int $dst_h, ?string $bg_hex = null, bool $rounded = false, int|string $gutter = 0): self
	{
		if ($gutter) {
			$this->addGutter($gutter);
		}

		$img = imagecreatetruecolor($dst_w, $dst_h);
		imagesavealpha($img, true);

		// START WITH TRANSPARENT BACKGROUND
		$bg_color = imagecolorallocatealpha($img, 0, 0, 0, 127);
		imagefill($img, 0, 0, $bg_color);

		if ($bg_hex) {
			// ADD A DEFINED BACKGROUND COLOR
			$color = (new Hex($bg_hex))->toRgb();
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
			} else {
				// FILL THE ENTIRE SPACE
				imagefill($img, 0, 0, $bg_color);
			}
		}

		$this->fitToImage(new self($img));

		return $this;
	}

	/**
	 * Copy the image onto a destination image, using the destination dimensions and keeping source scale
	 *
	 * @param self $template
	 * @param bool $overflow
	 * @return self
	 */
	public function fitToImage(self $template, bool $overflow = false): self
	{
		$src_w = $this->getImageWidth();
		$src_h = $this->getImageHeight();

		$dst_w = $template->getImageWidth();
		$dst_h = $template->getImageHeight();

		$dims = Resize::calculateFitDimensions($src_w, $src_h, $dst_w, $dst_h, $overflow);

		$template_img = $template->getImage();
		imagecopyresampled($template_img, $this->image, (int)$dims['dst_x'], (int)$dims['dst_y'], (int)$dims['src_x'], (int)$dims['src_y'], (int)$dims['dst_w'], (int)$dims['dst_h'], (int)$dims['src_w'], (int)$dims['src_h']);

		$this->resetImage($template_img);

		return $this;
	}

	/**
	 * Square an image by the smaller of its dimensions
	 *
	 * @return self
	 */
	public function squareImage(): self
	{
		$src_w = $this->getImageWidth();
		$src_h = $this->getImageHeight();

		$src_w = $src_h = min($src_w, $src_h);

		$img = new self($src_w, $src_h);

		return $this->fitToImage($img, true);
	}

	/**
	 * Add a gutter to the image
	 *
	 * @param int|string $gutter - Number of pixels or percentage of source dimensions
	 * @return self
	 */
	public function addGutter(int|string $gutter = '10%'): self
	{
		$src_w = imagesx($this->image);
		$src_h = imagesy($this->image);

		if ((is_string($gutter)) && (str_contains($gutter, '%'))) {
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
		imagesavealpha($dst, true);

		$bg_color = imagecolorallocatealpha($dst, 0, 0, 0, 127);
		imagefill($dst, 0, 0, $bg_color);

		imagecopyresampled($dst, $this->image, $gutter_x, $gutter_y, 0, 0, $src_w, $src_h, $src_w, $src_h);

		$this->resetImage($dst);

		return $this;
	}

	/**
	 * Add a block of text to an image resource
	 *
	 * @param string $text
	 * @param string $font
	 * @param int $size
	 * @param int|string $color
	 * @param int $x
	 * @param int $y
	 * @param int $alignment_horizontal
	 * @param int $alignment_vertical
	 * @param int|null $max_width
	 * @param int $stroke
	 * @param int|string|null $strokeColor
	 * @return self
	 */
	public function writeText(
		string $text,
		string $font,
		int $size,
		int|string $color,
		int $x,
		int $y,
		int $alignment_horizontal = self::TextAlignHorizontalLeft,
		int $alignment_vertical = self::TextAlignVerticalTop,
		?int $max_width = null,
		int $stroke = 0,
		int|string|null $strokeColor = null
	): self
	{
		if (General::isHexColor($color)) {
			$rgb = (new Hex($color))->toRgb();
			$allocatedColor = imagecolorallocate($this->image, $rgb->R, $rgb->G, $rgb->B);
			$color = $allocatedColor === false ? 0 : $allocatedColor;
		}

		if (($strokeColor !== null) && (General::isHexColor($strokeColor))) {
			$rgb = (new Hex($strokeColor))->toRgb();
			$allocatedColor = imagecolorallocate($this->image, $rgb->R, $rgb->G, $rgb->B);
			$strokeColor = $allocatedColor === false ? null : $allocatedColor;
		}

		$font_type = strrev(explode('.', strrev($font))[0]);
		$size_original = $size;
		$stroke_original = $stroke;

		$size++;
		do {
			$size--;
			$box_size = Text::getBoxSize($text, $font, $size);
			$stroke = (int)round($stroke_original * ($size / $size_original));
			$actual_width = $box_size['width'] + ($stroke * 2);
		} while (($max_width !== null) AND ($actual_width > $max_width));

		$x = $this->determineAlignmentPositionHorizontal($x, $box_size['width'], $alignment_horizontal);
		$y = $this->determineAlignmentPositionVertical($y, $box_size['height'], $box_size['baseline'], $alignment_vertical);

		if (($stroke) && ($strokeColor)) {
			if ($font_type === 'ttf') {
				$this->ttfStroke($size, 0, $x, $y, $color, $strokeColor, $font, $text, $stroke);
			} else {
				$this->ftStroke($size, 0, $x, $y, $color, $strokeColor, $font, $text, $stroke);
			}
		} elseif ($font_type === 'ttf') {
			imagettftext($this->image, $size, 0, $x, $y, $color, $font, $text);
		} else {
			imagefttext($this->image, $size, 0, $x, $y, $color, $font, $text);
		}

		return $this;
	}

	/**
	 * Add a block of text to an image resource, with the text horizontally condensed to a given percentage of the standard font size
	 *
	 * @param string $text
	 * @param string $font
	 * @param int $size
	 * @param int|string $color
	 * @param int $condensed
	 * @param int $x
	 * @param int $y
	 * @param int $alignment_horizontal
	 * @param int $alignment_vertical
	 * @return self
	 */
	public function writeTextCondensed(
		string $text,
		string $font,
		int $size,
		int|string $color,
		int $condensed,
		int $x,
		int $y,
		int $alignment_horizontal = self::TextAlignHorizontalLeft,
		int $alignment_vertical = self::TextAlignVerticalTop
	): self
	{
		$box_size = Text::getBoxSize($text, $font, $size);
		$tmp_w = $box_size['width'] + 2;
		$tmp_h = $box_size['height'] + 2;

		try {
			$tmp = new self($tmp_w, $tmp_h, true);
		} catch (Throwable) {
			return $this;
		}

		$tmp->writeText($text, $font, $size, $color, 1, 1);

		$dst_w = (int)floor($tmp_w * ($condensed / 100));

		$x = $this->determineAlignmentPositionHorizontal($x, $dst_w, $alignment_horizontal) - 1;

		if ($alignment_vertical !== self::TextAlignVerticalTop) {
			$y = $this->determineAlignmentPositionVertical($y, $box_size['height'], $box_size['baseline'], $alignment_vertical);
			$y -= $box_size['baseline'];
		}

		$y--;

		imagecopyresampled($this->image, $tmp->getImage(), $x, $y, 0, 0, $dst_w, $tmp_h, $tmp_w, $tmp_h);

		return $this;
	}

	/**
	 * Allocates a color in the image palette.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return false|int
	 */
	public function colorAllocate(int $red, int $green, int $blue): false|int
	{
		return imagecolorallocate($this->image, $red, $green, $blue);
	}

	/**
	 * Allocates a color with alpha support.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @param int $alpha
	 * @return false|int
	 */
	public function colorAllocateAlpha(int $red, int $green, int $blue, int $alpha): false|int
	{
		return imagecolorallocatealpha($this->image, $red, $green, $blue, $alpha);
	}

	/**
	 * Gets the color index at a pixel position.
	 *
	 * @param int $x
	 * @param int $y
	 * @return int
	 */
	public function colorAt(int $x, int $y): int
	{
		return imagecolorat($this->image, $x, $y);
	}

	/**
	 * Finds the closest matching color.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return int
	 */
	public function colorClosest(int $red, int $green, int $blue): int
	{
		return imagecolorclosest($this->image, $red, $green, $blue);
	}

	/**
	 * Finds the closest matching color including alpha.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @param int $alpha
	 * @return int
	 */
	public function colorClosestAlpha(int $red, int $green, int $blue, int $alpha): int
	{
		return imagecolorclosestalpha($this->image, $red, $green, $blue, $alpha);
	}

	/**
	 * Finds the closest color using hue, whiteness, and blackness.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return int
	 */
	public function colorClosestHwb(int $red, int $green, int $blue): int
	{
		return imagecolorclosesthwb($this->image, $red, $green, $blue);
	}

	/**
	 * Deallocates a color.
	 *
	 * @param int $color
	 * @return self
	 */
	public function colorDeallocate(int $color): self
	{
		imagecolordeallocate($this->image, $color);
		return $this;
	}

	/**
	 * Gets the exact color index for the given RGB values.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return int
	 */
	public function colorExact(int $red, int $green, int $blue): int
	{
		return imagecolorexact($this->image, $red, $green, $blue);
	}

	/**
	 * Gets the exact color index including alpha.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @param int $alpha
	 * @return int
	 */
	public function colorExactAlpha(int $red, int $green, int $blue, int $alpha): int
	{
		return imagecolorexactalpha($this->image, $red, $green, $blue, $alpha);
	}

	/**
	 * Matches colors from another image.
	 *
	 * @param self $image
	 * @return self
	 */
	public function colorMatch(self $image): self
	{
		imagecolormatch($this->image, $image->getImage());
		return $this;
	}

	/**
	 * Resolves a color to the closest available index.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return int
	 */
	public function colorResolve(int $red, int $green, int $blue): int
	{
		return imagecolorresolve($this->image, $red, $green, $blue);
	}

	/**
	 * Resolves a color including alpha.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @param int $alpha
	 * @return int
	 */
	public function colorResolveAlpha(int $red, int $green, int $blue, int $alpha): int
	{
		return imagecolorresolvealpha($this->image, $red, $green, $blue, $alpha);
	}

	/**
	 * Sets a color index to a new color.
	 *
	 * @param int $color
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @param int $alpha
	 * @return self
	 */
	public function colorSet(int $color, int $red, int $green, int $blue, int $alpha = 0): self
	{
		imagecolorset($this->image, $color, $red, $green, $blue, $alpha);
		return $this;
	}

	/**
	 * Returns the RGB and alpha values for a color index.
	 *
	 * @param int $color
	 * @return array{red:int, green:int, blue:int, alpha:int}
	 */
	public function colorsForIndex(int $color): array
	{
		return imagecolorsforindex($this->image, $color);
	}

	/**
	 * Returns the total number of colors in an image.
	 *
	 * @return int
	 */
	public function colorsTotal(): int
	{
		return imagecolorstotal($this->image);
	}

	/**
	 * Gets or sets the transparent color index.
	 *
	 * @param int|null $color
	 * @return int
	 */
	public function colorTransparent(?int $color = null): int
	{
		return imagecolortransparent($this->image, $color);
	}

	/**
	 * Draws an arc.
	 *
	 * @param int $centerX
	 * @param int $centerY
	 * @param int $width
	 * @param int $height
	 * @param int $startAngle
	 * @param int $endAngle
	 * @param int $color
	 * @return self
	 */
	public function arc(int $centerX, int $centerY, int $width, int $height, int $startAngle, int $endAngle, int $color): self
	{
		imagearc($this->image, $centerX, $centerY, $width, $height, $startAngle, $endAngle, $color);
		return $this;
	}

	/**
	 * Flood fill.
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 * @return $this
	 */
	public function fill(int $x, int $y, int $color): self
	{
		imagefill($this->image, $x, $y, $color);
		return $this;
	}

	/**
	 * Draws a filled arc.
	 *
	 * @param int $centerX
	 * @param int $centerY
	 * @param int $width
	 * @param int $height
	 * @param int $startAngle
	 * @param int $endAngle
	 * @param int $color
	 * @param int $style
	 * @return self
	 */
	public function filledArc(int $centerX, int $centerY, int $width, int $height, int $startAngle, int $endAngle, int $color, int $style): self
	{
		imagefilledarc($this->image, $centerX, $centerY, $width, $height, $startAngle, $endAngle, $color, $style);
		return $this;
	}

	/**
	 * Draws an ellipse.
	 *
	 * @param int $centerX
	 * @param int $centerY
	 * @param int $width
	 * @param int $height
	 * @param int $color
	 * @return self
	 */
	public function ellipse(int $centerX, int $centerY, int $width, int $height, int $color): self
	{
		imageellipse($this->image, $centerX, $centerY, $width, $height, $color);
		return $this;
	}

	/**
	 * Draws a filled ellipse.
	 *
	 * @param int $centerX
	 * @param int $centerY
	 * @param int $width
	 * @param int $height
	 * @param int $color
	 * @return self
	 */
	public function filledEllipse(int $centerX, int $centerY, int $width, int $height, int $color): self
	{
		imagefilledellipse($this->image, $centerX, $centerY, $width, $height, $color);
		return $this;
	}

	/**
	 * Draw a filled polygon
	 *
	 * @param array $points
	 * @param int|null $color
	 * @return $this
	 */
	public function filledPolygon(array $points, ?int $color = null): self
	{
		imagefilledpolygon($this->image, $points, $color);
		return $this;
	}

	/**
	 * Draws a single character.
	 *
	 * @param int $font
	 * @param int $x
	 * @param int $y
	 * @param string $char
	 * @param int $color
	 * @return self
	 */
	public function char(int $font, int $x, int $y, string $char, int $color): self
	{
		imagechar($this->image, $font, $x, $y, $char, $color);
		return $this;
	}

	/**
	 * Draws a character with vertical orientation.
	 *
	 * @param int $font
	 * @param int $x
	 * @param int $y
	 * @param string $char
	 * @param int $color
	 * @return self
	 */
	public function charUp(int $font, int $x, int $y, string $char, int $color): self
	{
		imagecharup($this->image, $font, $x, $y, $char, $color);
		return $this;
	}

	/**
	 * Draws a line between two points.
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $color
	 * @return self
	 */
	public function line(int $x1, int $y1, int $x2, int $y2, int $color): self
	{
		imageline($this->image, $x1, $y1, $x2, $y2, $color);
		return $this;
	}

	/**
	 * Draws a polygon.
	 *
	 * @param array<int, int> $points Flattened points as [x1, y1, x2, y2, ...]
	 * @param int $color
	 * @return self
	 */
	public function polygon(array $points, int $color): self
	{
		imagepolygon($this->image, $points, $color);
		return $this;
	}

	/**
	 * Draws a rectangle.
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $color
	 * @return self
	 */
	public function rectangle(int $x1, int $y1, int $x2, int $y2, int $color): self
	{
		imagerectangle($this->image, $x1, $y1, $x2, $y2, $color);
		return $this;
	}

	/**
	 * Draws a filled rectangle.
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $color
	 * @return self
	 */
	public function filledRectangle(int $x1, int $y1, int $x2, int $y2, int $color): self
	{
		imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $color);
		return $this;
	}

	/**
	 * Sets a single pixel.
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 * @return self
	 */
	public function setPixel(int $x, int $y, int $color): self
	{
		imagesetpixel($this->image, $x, $y, $color);
		return $this;
	}

	/**
	 * Draws a horizontal string using a built-in font.
	 *
	 * @param int $font
	 * @param int $x
	 * @param int $y
	 * @param string $text
	 * @param int $color
	 * @return self
	 */
	public function string(int $font, int $x, int $y, string $text, int $color): self
	{
		imagestring($this->image, $font, $x, $y, $text, $color);
		return $this;
	}

	/**
	 * Draws a string vertically.
	 *
	 * @param int $font
	 * @param int $x
	 * @param int $y
	 * @param string $text
	 * @param int $color
	 * @return self
	 */
	public function stringUp(int $font, int $x, int $y, string $text, int $color): self
	{
		imagestringup($this->image, $font, $x, $y, $text, $color);
		return $this;
	}

	/**
	 * Returns the width of a built-in font.
	 *
	 * @param int $font
	 * @return int
	 */
	public function fontWidth(int $font): int
	{
		return imagefontwidth($font);
	}

	/**
	 * Returns the height of a built-in font.
	 *
	 * @param int $font
	 * @return int
	 */
	public function fontHeight(int $font): int
	{
		return imagefontheight($font);
	}

	/**
	 * Loads a built-in GD font.
	 *
	 * @param string $font
	 * @return int|false
	 */
	public function loadFont(string $font): false|int
	{
		return imageloadfont($font);
	}

	/**
	 * Draws TrueType text.
	 *
	 * @param float $size
	 * @param float $angle
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 * @param string $font_filename
	 * @param string $text
	 * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}|false
	 */
	public function ttfText(float $size, float $angle, int $x, int $y, int $color, string $font_filename, string $text): false|array
	{
		return imagettftext($this->image, $size, $angle, $x, $y, $color, $font_filename, $text);
	}

	/**
	 * Draws FreeType text.
	 *
	 * @param float $size
	 * @param float $angle
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 * @param string $font_filename
	 * @param string $text
	 * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}|false
	 */
	public function ftText(float $size, float $angle, int $x, int $y, int $color, string $font_filename, string $text): false|array
	{
		return imagefttext($this->image, $size, $angle, $x, $y, $color, $font_filename, $text);
	}

	/**
	 * Returns the bounding box for TTF text.
	 *
	 * @param float $size
	 * @param float $angle
	 * @param string $font_filename
	 * @param string $string
	 * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}|false
	 */
	public function ttfBBox(float $size, float $angle, string $font_filename, string $string): false|array
	{
		return imagettfbbox($size, $angle, $font_filename, $string);
	}

	/**
	 * Returns the bounding box for FreeType text.
	 *
	 * @param float $size
	 * @param float $angle
	 * @param string $font_filename
	 * @param string $string
	 * @param array $options
	 * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}|false
	 */
	public function ftBBox(float $size, float $angle, string $font_filename, string $string, array $options = []): false|array
	{
		return imageftbbox($size, $angle, $font_filename, $string, $options);
	}

	/**
	 * Affine transforms the image with a matrix.
	 *
	 * @param array<int, float> $matrix
	 * @return GdImage|false
	 */
	public function affine(array $matrix): GdImage|false
	{
		return imageaffine($this->image, $matrix);
	}

	/**
	 * Concatenates affine transformation matrices.
	 *
	 * @param array<int, array<int, float>> $matrix
	 * @return array<int, float>|false
	 */
	public function affineMatrixConcat(array $matrix): false|array
	{
		return imageaffinematrixconcat($matrix[0], $matrix[1]);
	}

	/**
	 * Gets an affine transformation matrix.
	 *
	 * @param int $type
	 * @param mixed $options
	 * @return array<int, float>|false
	 */
	public function affineMatrixGet(int $type, mixed $options): false|array
	{
		return imageaffinematrixget($type, $options);
	}

	/**
	 * Enables or disables alpha blending.
	 *
	 * @param bool $enabled
	 * @return self
	 */
	public function alphaBlending(bool $enabled): self
	{
		imagealphablending($this->image, $enabled);
		return $this;
	}

	/**
	 * Enables or disables anti-aliasing.
	 *
	 * @param bool $enabled
	 * @return self
	 */
	public function antialias(bool $enabled): self
	{
		imageantialias($this->image, $enabled);
		return $this;
	}

	/**
	 * Copies a region from one image into this image.
	 *
	 * @param self $source
	 * @param int $dstX
	 * @param int $dstY
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @return self
	 */
	public function copy(self $source, int $dstX, int $dstY, int $srcX, int $srcY, int $srcWidth, int $srcHeight): self
	{
		imagecopy($this->image, $source->getImage(), $dstX, $dstY, $srcX, $srcY, $srcWidth, $srcHeight);
		return $this;
	}

	/**
	 * Copies and merges a source image with alpha blending.
	 *
	 * @param self $source
	 * @param int $dstX
	 * @param int $dstY
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @param int $pct
	 * @return self
	 */
	public function copyMerge(self $source, int $dstX, int $dstY, int $srcX, int $srcY, int $srcWidth, int $srcHeight, int $pct): self
	{
		imagecopymerge($this->image, $source->getImage(), $dstX, $dstY, $srcX, $srcY, $srcWidth, $srcHeight, $pct);
		return $this;
	}

	/**
	 * Copies and merges a source image in grayscale.
	 *
	 * @param self $source
	 * @param int $dstX
	 * @param int $dstY
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @param int $pct
	 * @return self
	 */
	public function copyMergeGray(self $source, int $dstX, int $dstY, int $srcX, int $srcY, int $srcWidth, int $srcHeight, int $pct): self
	{
		imagecopymergegray($this->image, $source->getImage(), $dstX, $dstY, $srcX, $srcY, $srcWidth, $srcHeight, $pct);
		return $this;
	}

	/**
	 * Resizes a region using nearest-neighbor interpolation.
	 *
	 * @param self $source
	 * @param int $dstX
	 * @param int $dstY
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $dstWidth
	 * @param int $dstHeight
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @return self
	 */
	public function copyResized(self $source, int $dstX, int $dstY, int $srcX, int $srcY, int $dstWidth, int $dstHeight, int $srcWidth, int $srcHeight): self
	{
		imagecopyresized($this->image, $source->getImage(), $dstX, $dstY, $srcX, $srcY, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
		return $this;
	}

	/**
	 * Resizes a region using resampling.
	 *
	 * @param self $source
	 * @param int $dstX
	 * @param int $dstY
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $dstWidth
	 * @param int $dstHeight
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @return self
	 */
	public function copyResampled(self $source, int $dstX, int $dstY, int $srcX, int $srcY, int $dstWidth, int $dstHeight, int $srcWidth, int $srcHeight): self
	{
		imagecopyresampled($this->image, $source->getImage(), $dstX, $dstY, $srcX, $srcY, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
		return $this;
	}

	/**
	 * Crops the image to a rectangle.
	 *
	 * @param array{0:int,1:int,2:int,3:int} $rect
	 * @return resource|false
	 */
	public function crop(array $rect)
	{
		return imagecrop($this->image, $rect);
	}

	/**
	 * Automatically crops an image based on background color.
	 *
	 * @param int $mode
	 * @param int $threshold
	 * @param int $color
	 * @return resource|false
	 */
	public function cropAuto(int $mode, int $threshold = 0, int $color = -1)
	{
		return imagecropauto($this->image, $mode, $threshold, $color);
	}

	/**
	 * Flips the image vertically or horizontally.
	 *
	 * @param int $mode
	 * @return self
	 */
	public function flip(int $mode): self
	{
		imageflip($this->image, $mode);
		return $this;
	}

	/**
	 * Applies a GD filter.
	 *
	 * @param int $filterType
	 * @param mixed ...$args
	 * @return self
	 */
	public function filter(int $filterType, ...$args): self
	{
		imagefilter($this->image, $filterType, ...$args);
		return $this;
	}

	/**
	 * Corrects gamma on the image.
	 *
	 * @param float $inputGamma
	 * @param float $outputGamma
	 * @return self
	 */
	public function gammaCorrect(float $inputGamma, float $outputGamma): self
	{
		imagegammacorrect($this->image, $inputGamma, $outputGamma);
		return $this;
	}

	/**
	 * Enables or disables interlacing.
	 *
	 * @param bool $enabled
	 * @return self
	 */
	public function interlace(bool $enabled): self
	{
		imageinterlace($this->image, $enabled ? 1 : 0);
		return $this;
	}

	/**
	 * Rotates the image by a given angle.
	 *
	 * @param float $angle
	 * @param int $bgColor
	 * @return self
	 */
	public function rotate(float $angle, int $bgColor): self
	{
		imagerotate($this->image, $angle, $bgColor);
		return $this;
	}

	/**
	 * Scales the image.
	 *
	 * @param int $newWidth
	 * @param int $newHeight
	 * @param int $mode
	 * @return self
	 */
	public function scale(int $newWidth, int $newHeight = -1, int $mode = IMG_BILINEAR_FIXED): self
	{
		imagescale($this->image, $newWidth, $newHeight, $mode);
		return $this;
	}

	/**
	 * Converts a true-color image to palette format.
	 *
	 * @param bool $dither
	 * @param int $nColors
	 * @return self
	 */
	public function trueColorToPalette(bool $dither, int $nColors): self
	{
		imagetruecolortopalette($this->image, $dither, $nColors);
		return $this;
	}

	/**
	 * Converts a palette image to true-color.
	 *
	 * @return self
	 */
	public function paletteToTrueColor(): self
	{
		imagepalettetotruecolor($this->image);
		return $this;
	}

	/**
	 * Applies a convolution matrix.
	 *
	 * @param array<int, float> $matrix
	 * @param float $divisor
	 * @param float $offset
	 * @return self
	 */
	public function convolution(array $matrix, float $divisor, float $offset): self
	{
		imageconvolution($this->image, $matrix, $divisor, $offset);
		return $this;
	}

	/**
	 * Gets the current clipping rectangle.
	 *
	 * @return array{0:int,1:int,2:int,3:int}|false
	 */
	public function getClip(): array|false
	{
		return imagegetclip($this->image);
	}

	/**
	 * Sets the clipping rectangle.
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $width
	 * @param int $height
	 * @return self
	 */
	public function setClip(int $x, int $y, int $width, int $height): self
	{
		imagesetclip($this->image, $x, $y, $width, $height);
		return $this;
	}

	/**
	 * Copies the palette from another image.
	 *
	 * @param self $source
	 * @return self
	 */
	public function paletteCopy(self $source): self
	{
		imagepalettecopy($this->image, $source->getImage());
		return $this;
	}

	/**
	 * Saves the alpha channel when writing PNGs.
	 *
	 * @param bool $saveAlpha
	 * @return self
	 */
	public function saveAlpha(bool $saveAlpha): self
	{
		imagesavealpha($this->image, $saveAlpha);
		return $this;
	}

	/**
	 * Sets the brush to use for line drawing.
	 *
	 * @param self $brush
	 * @return self
	 */
	public function setBrush(self $brush): self
	{
		imagesetbrush($this->image, $brush->getImage());
		return $this;
	}

	/**
	 * Sets the tile to use for filling.
	 *
	 * @param self $tile
	 * @return self
	 */
	public function setTile(self $tile): self
	{
		imagesettile($this->image, $tile->getImage());
		return $this;
	}

	/**
	 * Sets the style for line drawing.
	 *
	 * @param array<int, int> $style
	 * @return self
	 */
	public function setStyle(array $style): self
	{
		imagesetstyle($this->image, $style);
		return $this;
	}

	/**
	 * Sets the line thickness.
	 *
	 * @param int $thickness
	 * @return self
	 */
	public function setThickness(int $thickness): self
	{
		imagesetthickness($this->image, $thickness);
		return $this;
	}

	/**
	 * Vertically arch the image
	 *
	 * Useful for text images
	 *
	 * @param int $magnitude
	 * @return self
	 */
	public function applyArch(int $magnitude): self
	{
		$src_w = imagesx($this->image);
		$src_h = imagesy($this->image);

		$dst_h = $src_h - self::calculateArchHeight(0, $magnitude, $src_w);
		$tmp = $this->createImageFromDimensions($src_w, $dst_h, true);

		for ($x = 0; $x < $src_w; $x++) {
			$y = (int)self::calculateArchHeight($x, $magnitude, $src_w);
			imagecopyresampled($tmp, $this->image, $x, abs($y), $x, 0, 1, $src_h, 1, $src_h);
		}

		$this->resetImage($tmp);

		return $this;
	}

	/**
	 * Set the opacity of the image
	 *
	 * @param int $opacity
	 * @return self
	 */
	public function setOpacity(int $opacity): self
	{
		$w = imagesx($this->image);
		$h = imagesy($this->image);

		$tmp = $this->createImageFromDimensions($w, $h, true);
		imagealphablending($tmp, false);

		$white = imagecolorallocate($tmp, 255, 255, 255);
		imagefill($tmp, 0, 0, $white);

		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$color = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));
				$color['alpha'] = (127 - round((127 - $color['alpha']) * ($opacity / 100)));
				imagesetpixel($tmp, $x, $y,
					imagecolorallocatealpha($this->image, $color['red'], $color['green'], $color['blue'], $color['alpha']));
			}
		}

		$this->resetImage($tmp);

		return $this;
	}

	/**
	 * Create a GdImage from the specified file
	 *
	 * @param string $path
	 * @param string|null $type
	 * @return GdImage|null
	 */
	private function createImageFromPath(string $path, ?string $type = null): ?GdImage
	{
		if (!$type) {
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			if ($extension) {
				$mimeTypes = new MimeTypes();
				$type = $mimeTypes->getMimeType($extension);
			}
		}

		$this->originalMimeType = $type;

		$allow_transparency = false;

		if (in_array($type, ['image/jpg', 'image/jpeg'])) {
			$src = imagecreatefromjpeg($path);
		} elseif ($type === 'image/gif') {
			$src = imagecreatefromgif($path);
			$allow_transparency = true;
		} else {
			$src = imagecreatefrompng($path);
			$allow_transparency = true;
		}

		if ($allow_transparency) {
			imagealphablending($src, false);
			imagesavealpha($src, true);
		}

		if ($src) {
			return $src;
		}

		return null;
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @param bool $transparent
	 * @return GdImage|null
	 */
	private function createImageFromDimensions(int $width, int $height, bool $transparent = false): ?GdImage
	{
		$img = imagecreatetruecolor($width, $height);

		if ($img) {
			if ($transparent) {
				imagesavealpha($img, true);
				imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
			}

			return $img;
		}

		return null;
	}

	/**
	 * Reset the image with new data
	 *
	 * @param GdImage $img
	 * @return void
	 */
	private function resetImage(GdImage $img): void
	{
		$width = imagesx($img);
		$height = imagesy($img);

		$this->image = $this->createImageFromDimensions($width, $height, true);

		imagecopy($this->image, $img, 0, 0, 0, 0, $width, $height);
	}

	/**
	 * Calculate the height of an arch based on its x-coordinate, width, and magnitude
	 *
	 * @param int $x
	 * @param float|int $magnitude
	 * @param float|int $width
	 * @return int|float
	 */
	private static function calculateArchHeight(int $x, float|int $magnitude, float|int $width): float|int
	{
		return (-1 * (($magnitude / 100) / ($width * .8)) * (($x - ($width / 2)) ** 2));
	}

	/**
	 * Writes the given text with a border into the image using TrueType fonts.
	 *
	 * @param int $size
	 * @param float $angle
	 * @param int $x
	 * @param int $y
	 * @param int $textcolor
	 * @param int $strokecolor
	 * @param string $fontfile
	 * @param string $text
	 * @param int $px
	 * @see http://johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
	 */
	private function ttfStroke(int $size, float $angle, int $x, int $y, int $textcolor, int $strokecolor, string $fontfile, string $text, int $px): void
	{

		for ($c1 = ($x - abs($px)); $c1 <= ($x + abs($px)); $c1++) {
			for ($c2 = ($y - abs($px)); $c2 <= ($y + abs($px)); $c2++) {
				imagettftext($this->image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			}
		}

		imagettftext($this->image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
	}

	/**
	 * Writes the given text with a border into the image
	 *
	 * @param int $size
	 * @param float $angle
	 * @param int $x
	 * @param int $y
	 * @param int $textcolor
	 * @param int $strokecolor
	 * @param string $fontfile
	 * @param string $text
	 * @param int $px
	 * @see http://johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
	 */
	private function ftStroke(int $size, float $angle, int $x, int $y, int $textcolor, int $strokecolor, string $fontfile, string $text, int $px): void
	{

		for ($c1 = ($x - abs($px)); $c1 <= ($x + abs($px)); $c1++) {
			for ($c2 = ($y - abs($px)); $c2 <= ($y + abs($px)); $c2++) {
				imagefttext($this->image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			}
		}

		imagefttext($this->image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
	}

	/**
	 * Determine the actual X coordinate for text based on the alignment
	 *
	 * @param int $x
	 * @param int $width
	 * @param int $alignment
	 * @return int
	 */
	private function determineAlignmentPositionHorizontal(int $x, int $width, int $alignment): int
	{
		if ($alignment === self::TextAlignHorizontalCenter) {
			$x -= round($width / 2);
		} elseif ($alignment === self::TextAlignHorizontalRight) {
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
	private function determineAlignmentPositionVertical(int $y, int $height, int $baseline, int $alignment): int
	{
		if ($alignment === self::TextAlignVerticalMiddle) {
			$y += round($baseline / 2);
		} elseif ($alignment === self::TextAlignVerticalTop) {
			$y += $baseline;
		} else {
			$y -= $height - $baseline;
		}

		return $y;
	}
}
