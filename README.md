# Kirby Rokka

## WARNING

This is more a proof of concept, than something production ready. Use it at your own risk and extend it to make it very useful.

## Requirements

- [**Kirby**](https://getkirby.com/) 2.5+ (??)
- [Rokka API key](https://rokka.io/en/signup/) (trial available).

## Installation

### [Kirby CLI](https://github.com/getkirby/cli)

FIXME: Not done yet ;)

```
kirby plugin:install rokka/kirby
```

### Git Submodule


```
$ git submodule add https://github.com/rokka-io/rokka-kirby-plugin.git site/plugins/rokka
```

### Copy and Paste

FIXME. Not done yet

1. [Download](https://github.com/rokka/kirby-rokka/archive/master.zip) the contents of this repository as ZIP-file.
2. Rename the extracted folder to `rokka` and copy it into the `site/plugins/` directory in your Kirby project.

### Composer install

```
cd site/plugins/rokka
composer install
```

## Usage

In your `site/config.php` activate the plugin and set the [ROKKA API key](https://rokka.io/en/signup/) .

```php
c::set('plugin.rokka.organisation', 'YOUR_ORG_NAME_HERE'); // default is false
c::set('plugin.rokka.apikey', 'YOUR_API_KEY_HERE');
```

The plugin adds a `$myFile->rokka()` function to [$file objects](https://getkirby.com/docs/cheatsheet#file).

FIXME: currently ->rokka() returns an img tag.. maybe have it just an URL is better

```php
// get any image/file object
$myFile = $page->file('image.jpg');

// get url (on your webserver) for optimized thumb
$url = $myFile->rokka($stackname, $extension);

// echo the url as image
// https://getkirby.com/docs/toolkit/api#brick
$img = brick('img')
	->attr('src', $url)
	->attr('alt', $myFile->filename());
echo $img;
```

### kirbytext

```
(rokka: foo.png stack:stackname extension:jpg)
```

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/rokka/kirby-rokka/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.
