<?php

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Text\KirbyTag;


Kirby::plugin(
  'rokka/kirby',
  [
    'options' => [
      'enabled' => false,
      'organization' => 'YOUR_ORG',
      'apikey' => 'YOUR_API_KEY',

    ],
    'routes' => function ($kirby) {
      return [
        [
          'pattern' => '_rokka/create-stacks',
          'action' => function () use ($kirby) {
            return Rokka::createStacks($kirby);
          },
        ],
      ];
    },
    'fileMethods' => [
      'rokkaCropUrl' => function ($width, $height = 10000, $format = null) {
        return Rokka::getCropUrl($this, $width, $height, $format);
      },
      'rokkaResizeUrl' => function ($width, $height = 10000, $format = null) {
        return Rokka::getResizeUrl($this, $width, $height, $format);
      },
      'rokkaOriginalSizeUrl' => function ($format = null) {
        return Rokka::getOriginalSizeUrl($this, $format);
      },
      'rokkaGetHash' => function () {
        return Rokka::getRokkaHash($this);
      },
      'rokka' => function ($stack, $extension = null) {
        return Rokka::getImgTag($this, $stack, $extension);
      },
    ],
    'tags' => [
      'imageRokka' => [
        'attr' => [
          'stack',
          'format',
          //from here on, these are the default attr from kirby/config/tags.php for images
          'alt',
          'caption',
          'class',
          'height',
          'imgclass',
          'link',
          'linkclass',
          'rel',
          'target',
          'text',
          'title',
          'width'
        ],
        'html' => function (KirbyTag $tag) {
          //Fallback to original kirby image kirby tag, if rokka is not enabled
          if (!Rokka::isEnabled()) {
            return Rokka::getOriginalImageTag()['html']($tag);
          }

          $file = $tag->file($tag->attr('imageRokka'));
          if ($file == null) {
            if (url::isAbsolute($tag->attr('imageRokka'))) {
              //use kirby image tag impl, if we have an absolute url
              return Rokka::getOriginalImageTag()['html']($tag);
            } else {
              // don't return any image tag, if the file doesn't exist
              return "";
            }
          }

          $stacks = option('rokka.kirby.stacks');
          $extension = $file->extension();
          $ext = null;
          if ($extension == 'svg') {
            $stack = $stacks['raw'];
            $ext = $extension;
          } else if ($width = $tag->attr('width')) {
            $options = "resize-width-$width";
            if ($height = $tag->attr('height')) {
              $options .= "-height-$height";
            }
            if (isset($stacks['resize'])) {
              $stack = $stacks['resize'] . "/$options";
            } else {
              $stack = "dynamic/$options--options-autoformat-true";
            }
          } else if (isset($stacks['kirbytext'])) {
            $stack = $stacks['kirbytext'];
          } else if (isset($stacks['noop'])) {
            $stack = $stacks['noop'];
          } else {
            $stack = "dynamic/options-autoformat-true";
          }

          $stack = $tag->attr('stack', $stack);
          if (!$ext) {
            $ext = $tag->attr('format', 'jpg');
          }
          if ($file == false) {
            $file = null;
          }
          return Rokka::getImgTag($file, $stack, $ext, $tag);
        },
      ],
    ],
    'hooks' => [
      'kirbytags:before' => function ($text, $data, $options) {
        // replace all (image: with (imageRokka:
        if (Rokka::isEnabled()) {
          // regex taken from \Kirby\Text\KirbyTags::parse
          return preg_replace_callback('!(?=[^\]])\(image:(.*?\))!is', function ($match) {
            return '(imageRokka:' . $match[1];
          }, $text);
        }
        return $text;
      },
    ],
    'components' => [
      'file::version' => function (App $kirby, File $file, array $options) {
        if (!Rokka::isEnabled()) {
          // fallback to the default one
          $components = include $kirby->root('kirby') . '/config/components.php';
          return $components['file::version']($kirby, $file, $options);
        }

        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;
        $format = $options['format'] ?? $file->extension();

        $format = strtolower($format);
        if ($format !== 'png') {
          $format = 'jpg';
        }
        if (isset($options['grayscale']) && $options['grayscale']) {
          $url = Rokka::getGrayscaleUrl($file, $format);
        } else if (isset($options['crop']) && $options['crop']) {
          $url = Rokka::getCropUrl($file, $width, $height, $format);
        } else if (isset($options['blur']) && $options['blur']) {
          $url = Rokka::getBlurUrl($file, $options['blur'], $format);
        } else {
          $url = Rokka::getResizeUrl($file, $width, $height, $format);
        }
        return new FileVersion([
          'modifications' => $options,
          'original' => $file,
          'root' => $file->root(),
          'url' => $url,
        ]);
      },
    ],
  ]
);

