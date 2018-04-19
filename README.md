# Kirby Rokka

[rokka](https://rokka.io) is digital image processing done right. Store, render and deliver images. Easy and blazingly fast.
This plugin automatically uploads your pictures to rokka and delivers them in the right format, as light and as fast as possible.
And you only pay what you use, no upfront and fixed costs. 

Free account plans are available. Just install the plugin, register and use it.

## WARNING

This is still alpha software. We  use this for our [Liip](https://liip.ch/) company webpage, so it's somehow battle tested. 
But there's still room for improvements and some behind the scene code will change, the kirby API will stay stable, hopefully.
And input is always welcome.

## Requirements

- [PHP 7.0](https://php.net) 
- [**Kirby**](https://getkirby.com/) 2.5+ 
- [Rokka API key](https://rokka.io/en/signup/) (trial available).
- [Rokka PHP Client](https://github.com/rokka-io/rokka-client-php) 1.1+. Installed via composer, see below.

## Installation

### [Kirby CLI](https://github.com/getkirby/cli)

```
kirby plugin:install rokka-io/rokka-kirby-plugin
```

### Git Submodule

```
$ git submodule add https://github.com/rokka-io/rokka-kirby-plugin.git site/plugins/rokka
```

### Copy and Paste

1. [Download](https://github.com/rokka-io/rokka-kirby-plugin/archive/master.zip) the contents of this repository as ZIP-file.
2. Rename the extracted folder to `rokka` and copy it into the `site/plugins/` directory in your Kirby project.

### Composer install

This is needed for all the libraries rokka needs. Do this in your kirby directory.
```
composer require rokka/client
```

## Usage

In your `site/config.php` activate the plugin and set the [ROKKA API key](https://rokka.io/en/signup/) .

```php
c::set('plugin.rokka.enabled', true); 
c::set('plugin.rokka.organization', 'YOUR_ORG_NAME_HERE'); 
c::set('plugin.rokka.apikey', 'YOUR_API_KEY_HERE');
```

The following is also recommended (see below in "Defining Stacks"):

```
c::set('plugin.rokka.stacks', [
    'noop' => 'www_noop',
    'resize' => 'www_resize',
    'raw' => 'www_raw',
    'kirbytext' => 'www_kirbytext', // default stack for kirbytext image includes
    'resize-800x10000' => 'www_kirbytext' //images in kirbytext will have a width of 800px
]);
```

The plugin adds a `$myFile->rokkaCropUrl($width, $height, $format = "jpg")`, `
$myFile->rokkaResizeUrl($width, $height, $format = "jpg")` and a `$myFile->rokkaOriginalSizeUrl($format="jpg")` function to [$file objects](https://getkirby.com/docs/cheatsheet#file).

You can use them like the following in your templates:

```php
// get any image/file object
$myFile = $page->file('image.jpg');

// get url (on your webserver) for optimized thumb
$url = $myFile->rokkaCropUrl(500,500);

```

There's also `$myFile->rokka($stackname, $extension)` for returning an html img tag with using a stack and
`$myFile->rokkaGetHash()` for getting the rokka hash of an image.

## Defining stacks

Rokka has a concept of [stacks](https://rokka.io/documentation/references/stacks.html), which allow to have  nicer URLs.

You can configure some stacks with the `plugin.rokka.stacks` configure option. If you for example use certain sizes a lot, you should use a stack. For example, if you do `$myFile->rokkaCropUrl(200,200)` and `$myFile->rokkaResizeUrl(300,300)`, then define a stack with 

```
c::set('plugin.rokka.stacks', [
    `crop-200x200' => 'www_thumbnail',
    `resize-300x300' => 'www_resized',

```

The value of the array (in this example www_thumbnail) can be an ascii text, you can use there whatever you want.

The `noop`, `resize` and `raw` keys have a special meaning, especially if you want to use SVG files, you should set the `raw` key.

After you defined your stacks, go to your panel and click the "Create Rokka Stacks" links on the box on the right side, this will create your stacks on the Rokka server.

You can also set stack options for those stacks with eg.

```
c::set('plugin.rokka.stacks.options', [
    'resize-300x300' => ['options' => [webp.quality' => 80]], 
    'crop-200x200' => ['options' => [jpg.quality' => 85]], 
    'resize-800x10000' => [['resize' => ['upscale' => false, 'options' => [webp.quality' => 80]] // don't upscale picture, if they're smaller than the width 
]);
```

And if you want different settings for retina screens you can add an 'options-retina' key
```
c::set('plugin.rokka.stacks.options', [
    'resize-300x300' => ['options' => [webp.quality' => 80], 'options-retina' => [webp.quality' => 60]], 
]);
```


All available Stack options can be found on the [rokka documentation](https://rokka.io/documentation/references/stacks.html).


## Retina images

To be more documented. 

Get html attribute snippets with 
`Rokka::getSrcAttributes($url)`
`Rokka::getBackgroundImageStyle($url)`
for `srcset` enabled tags with retina resolutions.

### kirbytext

This plugin overwrites the kirbytext `image` tag and serves pictures from rokka if that is used.

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/rokka/kirby-rokka/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)
