# Image

Simple tooling for image manipulation, wrapping native GD functions.


## Usage

```php
use cjrasmussen\Image\Image;

$img = new Image(1000, 500);
$img->saveAlpha(true);

$img->writeText('MyFont.ttf', 32, '880000', 20, 20)

$img->resize(500, 250);

$img->saveFile('/path/to/file.png', 'image/png');
```

## Installation

Simply add a dependency on cjrasmussen/image to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require cjrasmussen/image
```

Although it's recommended to use Composer, you can actually include the file(s) any way you want.


## License

Image is [MIT](http://opensource.org/licenses/MIT) licensed.