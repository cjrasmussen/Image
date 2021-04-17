# Image

Simple functions for image manipulation.


## Usage

```php
use cjrasmussen\Image\Resize;
use cjrasmussen\Image\Text;

Resize::fitToImage($dst, $src);

Text::write($dst, 'Hello World', 'MyFont.ttf', 32, '880000'. 20, 20);
```

## Installation

Simply add a dependency on cjrasmussen/image to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require cjrasmussen/image
```

Although it's recommended to use Composer, you can actually include the file(s) any way you want.


## License

Image is [MIT](http://opensource.org/licenses/MIT) licensed.