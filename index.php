<?php

use Kirby\Cms\KirbyTag;
use Rokka\Kirby\Rokka;

@include_once __DIR__ . '/vendor/autoload.php';

Rokka::$previousImageKirbyTag = Kirby\Text\KirbyTag::$types['image'];
Kirby::plugin(
  'rokka/kirby',
  [
    'options' => [
      'enabled' => true,
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
        ]
      ];
    },
    'fileMethods' => [
      'rokkaCropUrl' => function ($width, $height = 10000, $format = 'jpg') {
        return Rokka::getStackUrl('crop', $this, $width, $height, $format, "dynamic/resize-width-$width-height-$height-mode-fill--crop-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
      },
      'rokkaResizeUrl' => function ($width, $height = 10000, $format = 'jpg') {
        return Rokka::getStackUrl('resize', $this, $width, $height, $format, "dynamic/resize-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
      },
      'rokkaOriginalSizeUrl' => function ($format = 'jpg') {
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
      'image' => [
        'attr' => array_merge(
          ['stack', 'format'],
          Rokka::$previousImageKirbyTag['attr']
        ),
        'html' => function (KirbyTag $tag) {
          //Fallback to original kirby image kirby tag, if rokka is not enabled
          if (!Rokka::isEnabled()) {
            return Rokka::$previousImageKirbyTag['html']($tag);
          }

          $file = $tag->file($tag->attr('image'));
          if ($file == null) {
            if (url::isAbsolute($tag->attr('image'))) {
              //use kirby image tag impl, if we have an absolute url
              return Rokka::$previousImageKirbyTag['html']($tag);
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
  ]
);

