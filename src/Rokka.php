<?php


use GuzzleHttp\Exception\GuzzleException;
use Kirby\Cms\File;
use Kirby\Text\KirbyTag;
use Rokka\Client\LocalImage\FileInfo;
use Rokka\Client\TemplateHelper;

class Rokka
{
  const DEFAULT_TXT_LANG = 'en';
  public static $previousImageKirbyTag = null;

  // mainly copied from https://rokka.io/documentation/guides/best-practices-for-stack-configurations.html  for maximum flexibility and small urls
  private const DEFAULT_RESIZE_STACK = [
    'operations' => [
      'resize' => [
        'expressions' => [
          'width' => '$finalWidth',
          'height' => '$finalHeight'
        ],
      ]
    ],
    'variables' => [
      'defaultWidth' => '(image.width)', // will be overwritten if width is explicitely set
      'defaultHeight' => '(image.height)', // will be overwritten if width is explicitely set
      'w' => 0,
      'h' => 0,
      'r' => '$defaultHeight > 0 ? ($defaultWidth / $defaultHeight) : (image.width / image.height)', // this is all a little overkill for just resize, but it works for all use cases (just h set, just w set, both not set, etc)
      'finalWidth' => '$w == 0 ? ($h == 0 ? $defaultWidth : ($h * $r)) : $w',
      'finalHeight' => '$h == 0 ? $finalWidth / $r : $h',
    ],
    'options' => self::DEFAULT_BASE_STACK['options'],
    'expressions' => self::DEFAULT_BASE_STACK['expressions']

  ];

  // copied from https://rokka.io/documentation/guides/best-practices-for-stack-configurations.html for maximum flexibility and small urls
  private const DEFAULT_CROP_STACK = [
    'operations' => [
      'resize' => [
        'options' => [
          'mode' => 'fill'
        ],
        'expressions' => [
          'width' => '$finalWidth',
          'height' => '$finalHeight'
        ],
      ],
      'crop' => [
        'expressions' => [
          'width' => '$finalWidth',
          'height' => '$finalHeight'
        ],
      ],
    ],
    'variables' => [
      'defaultWidth' => '$w == 0 ? (image.width) : $w', // will be overwritten if width is explicitely set
      'defaultHeight' => '$h == 0 ? (image.height) : $h', // will be overwritten if width is explicitely set
      'w' => 0,
      'h' => 0,
      "r" => '$defaultWidth / $defaultHeight',
      'finalWidth' => '$w == 0 ? ($h == 0 ? $defaultWidth : ($h * $r)) : $w',
      'finalHeight' => '$h == 0 ? $finalWidth / $r : $h',
    ],
    'options' => self::DEFAULT_BASE_STACK['options'],
    'expressions' => self::DEFAULT_BASE_STACK['expressions']

  ];

  private const DEFAULT_BASE_STACK = [
    'options' => [
      'autoformat' => true,
      'jpg.transparency.autoformat' => true,
    ],
    'expressions' => [[
      "expression" => "options.dpr >= 2",
      "overrides" => [
        "options" => [
          "optim.quality" => 2,
        ],
      ],
    ]],
  ];

  /**
   * @var TemplateHelper
   */
  public static $rokka = null;

  public static function panelUpload(Kirby\Panel\Models\File $file)
  {
    $file->update([self::getRokkaHashKey() => ""]);
  }

  public static function getSrcAttributes($url, $sizes = ['2x'])
  {
    if (!option('rokka.kirby.enabled')) {
      return 'src="' . $url . '"';
    }
    return self::getRokkaInstance()::getSrcAttributes($url, $sizes);
  }

  public static function getBackgroundImageStyle($url, $sizes = ['2x'])
  {

    if (!option('rokka.kirby.enabled')) {
      return "background-image:url('$url');";
    }
    return self::getRokkaInstance()::getBackgroundImageStyle($url, $sizes);
  }

  public static function getImgTag(
    File $file = null,
    string $stack = null,
    string $extension = null,
    KirbyTag $tag = null
  )
  {
    $rokkaImageObject = self::getRokkaImageObject($file);
    try {
      if (!$hash = self::getRokkaInstance()->getHashMaybeUpload($rokkaImageObject)) {
        return self::getOriginalImageTag()['html']($tag);

      }
    } catch (\Exception $e) {
      return self::getOriginalImageTag()['html']($tag);
    }

    $tag->value = self::getRokkaInstance()->getStackUrl($rokkaImageObject, $stack, $extension);
    return self::getOriginalImageTag()['html']($tag);
  }

  public static function getStackUrl(string $operation, File $file, $width, $height, $format, $dynamicStack)
  {
    if (!option('rokka.kirby.enabled')) {
      return $file->$operation($width, $height)->url();
    }

    if ($height === null) {
      $height = 10000;
    }
    if ($width === null) {
      $width = 10000;
    }
    if ($format === null) {
      if ($file->mime() === 'image/gif') {
        $format = 'gif';
      } else {
        $format = 'jpg';
      }
    }

    $rokkaImageObject = self::getRokkaImageObject($file);
    if (!$hash = self::getRokkaInstance()->getHashMaybeUpload($rokkaImageObject)) {
      return $file->$operation($width, $height)->url();
    }
    $stacks = option('rokka.kirby.stacks');
    $extension = $file->extension();
    if ($extension == 'svg') {
      $format = $extension;
    }
    if (isset($stacks["${operation}-${width}x${height}"])) {
      $stack = $stacks["${operation}-${width}x${height}"];
    } else {
      // check if we have a stack configuration for this variable with w setting, then we can use the shorter URLs.
      $config = self::getStackConfiguration($operation, $operation);
      if (isset($config['variables']['w'])) {
        $stack = $stacks[$operation];
        $variables = [];
        if ($width < 10000) {
          $variables['w'] = $width;
        }
        if ($height < 10000) {
          $variables['h'] = $height;
        }
        if (count($variables) > 0) {
          $stack .= '/v';
          foreach ($variables as $_k => $_v) {
            $stack .= '-' . $_k . '-' . $_v;
          }

        }
      } else {
        // otherwise just use the dynamic stack config
        $stack = $dynamicStack;
      }
    }


    return self::getRokkaInstance()->generateRokkaUrl($hash, $stack, $format, self::getRokkaInstance()->getImagename($rokkaImageObject));
  }

  public static function generateRokkaUrl(File $file, $stack, $format = 'jpg') {

    $rokkaImageObject = self::getRokkaImageObject($file);
    if (!$hash = self::getRokkaInstance()->getHashMaybeUpload($rokkaImageObject)) {
        return $file->url();
    }
    return self::getRokkaInstance()->generateRokkaUrl($hash, $stack, $format, self::getRokkaInstance()->getImagename($rokkaImageObject));
  }

  public static function getOriginalSizeUrl(File $file, $format = null)
  {
    //FIXME: check for noop stack
    if (!option('rokka.kirby.enabled')) {
      return $file->url();
    }

    $rokkaImageObject = self::getRokkaImageObject($file);

    if (!$hash = self::getRokkaInstance()->getHashMaybeUpload($rokkaImageObject)) {
      return $file->url();
    }

    if ($format === null) {
      if ($file->mime() === 'image/gif') {
        $format = 'gif';
      } else {
        $format = 'jpg';
      }
    }

    return self::$rokka->generateRokkaUrl(
      $hash,
      "dynamic/noop--options-autoformat-true-jpg.transparency.autoformat-true",
      $format,
      self::getRokkaInstance()->getImagename($rokkaImageObject)
    );
  }

  public static function getRokkaHash(File $file): ?string
  {
    $var = self::getRokkaHashKey();
    return $file->$var()->value();
  }

  public static function getRokkaHashKey()
  {
    return "Rokkahash_" . str_replace("-", "_", option('rokka.kirby.organization'));
  }

  public static function getResizeUrl($file, $width, $height = null, $format = null)
  {
    return self::getStackUrl('resize', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
  }

  public static function getCropUrl($file, $width, $height = null, $format = null)
  {
    return self::getStackUrl('crop', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height-mode-fill--crop-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
  }

  public static function getGrayscaleUrl($file, $format = null)
  {
    return self::getStackUrl('grayscale', $file, null, null, $format, "dynamic/grayscale--options-autoformat-true-jpg.transparency.autoformat-true");
  }

  public static function getBlurUrl($file, $pixels, $format = null)
  {
    $stack = 'dynamic/blur';
    if (is_numeric($pixels)) {
      $stack .= '-sigma-' . $pixels;
    }
    $stack .= '--options-autoformat-true-jpg.transparency.autoformat-true';
    return self::getStackUrl('blur', $file, null, null, $format, $stack);
  }

  public static function createStacks($kirby)
  {
    $logged_in_user = $kirby->user();
    if (!$logged_in_user || !$logged_in_user->role()->isAdmin()) {
      go('/');
      return false;
    }
    $stacks = option('rokka.kirby.stacks');
    $stacksoptions = option('rokka.kirby.stacks.options');
    $imageClient = self::getRokkaClient();
    print '<h1>Create stacks on rokka</h1>';
    print '<h2>For organisation: ' . option('rokka.kirby.organization') . '</h2>';
    foreach ($stacks as $key => $rokkaStackName) {
      @list($name, $options) = explode("-", $key, 2);
      print '<h2>Create stack named: ' . $rokkaStackName . '</h2>';
      if (!isset($stacksoptions[$key]['operations']['resize'])) {
        $stacksoptions[$key]['operations']['resize'] = [];
      }
      if (isset($stacksoptions[$key]['resize'])) {
        print "<h3>ERROR, please change config for '$key'</h3>";
        print "<p> The config for individual resize options in rokka.stack.options changed, please move the 'resize' key to 'operations' => 'resize' => 'options'</p>";
        $newOptions = $stacksoptions[$key];
        unset($newOptions['resize']);
        $newOptions['operations']['resize']['options'] = $stacksoptions[$key]['resize'];
        print "<pre>'". $key ."' => ".var_export($newOptions,true) ."</pre>";
        die;

      }

      $stackConfig = self::DEFAULT_BASE_STACK;
      switch ($name) {
        case "crop":
          $stackConfig = self::getStackConfiguration('crop', $key);

          if ($options) {
            list($width, $height) = explode("x", $options);

            if (isset($stackConfig['variables']['defaultWidth'])) {
              $stackConfig['variables']['defaultWidth'] = $width;
            }
            if (isset($stackConfig['variables']['defaultHeight'])) {
              $stackConfig['variables']['defaultHeight'] = $height;
            }
            if (isset($stackConfig['resize']['variables']['width'])) {
              $stackConfig['crop']['variables']['width'] = $width;
            }
            if (isset($stackConfig['resize']['variables']['height'])) {
              $stackConfig['crop']['variables']['height'] = $height;
            }
          }
          break;
        case "noop":
        case "raw":
          break;
        case "resize":
          $stackConfig = self::getStackConfiguration('resize', $key);
          if ($options) {
            list($width, $height) = explode("x", $options);
            if (isset($stackConfig['variables']['defaultWidth'])) {
              $stackConfig['variables']['defaultWidth'] = $width;
            }
            if (isset($stackConfig['variables']['defaultHeight'])) {
              if ($height > 9999) {
                $height = 0;
              }
              $stackConfig['variables']['defaultHeight'] = $height;
            }

            if (isset($stackConfig['resize']['variables']['width'])) {
              $stackConfig['resize']['variables']['width'] = $width;
            }
            if (isset($stackConfig['resize']['variables']['height'])) {
              $stackConfig['resize']['variables']['height'] = $height;
            }
          }
          break;
        default;
          print "Nothing done, no rules for $key";
          continue 2;
      }

      $operations = [];
      if (isset($stackConfig['operations'])) {
        foreach ($stackConfig['operations'] as $operationName => $opValues) {
          $op = new \Rokka\Client\Core\StackOperation($operationName, $opValues['options'] ?? [], $opValues['expressions'] ?? []);
          $operations[] = $op;
        }
      }

      if ($name == "raw") {
        $stackConfig['options'] = ['source_file' => true];
      }

      if (isset($stacksoptions[$key]['options'])) {
        $stackConfig['options']  = array_merge($stackConfig['options'] , $stacksoptions[$key]['options']);
      }



      $startTime = (new \DateTime())->sub(new \DateInterval("PT1S"));
      try {
        $stack = new \Rokka\Client\Core\Stack('', $rokkaStackName);
        $stack->setStackOperations($operations);
        if (isset($stackConfig['options'])) {
          $stack->setStackOptions($stackConfig['options']);
        }
        if (isset($stackConfig['variables'])) {
          $stack->setStackVariables($stackConfig['variables']);
        }
        print(json_encode($stack->getConfig()));
        print "\n";

        if (isset($stackConfig['expressions'])) {
          foreach($stackConfig['expressions'] as $expression ) {
            $expr = new \Rokka\Client\Core\StackExpression($expression['expression'], $expression['overrides']['options']);
            $stack->addStackExpression($expr);
          }
        }
        if (isset($stacksoptions[$key]['options-retina'])) {
          $expr = new \Rokka\Client\Core\StackExpression("options.dpr > 1.5", $stacksoptions[$key]['options-retina']);
          $stack->addStackExpression($expr);
        }
        $resp = $imageClient->saveStack($stack, ['overwrite' => true]);
      } catch (GuzzleException $e) {
        print "<h2>ERROR!</h2>";
        var_dump($e->getResponse()->getBody()->getContents());
        die;
      }
      print '<p>Done</p>';
      print '<p>Operations: ';
      print json_encode($resp->getStackOperations());
      print '</p>';
      print '<p>Options: ';
      print json_encode($resp->getStackOptions());
      print '<p>Expressions: ';
      print json_encode($resp->getStackExpressions());
      print '<p>Variables: ';
      print json_encode($resp->getStackVariables());
      print '</p>';
      print '<p>';
      if ($startTime <= $resp->getCreated()) {
        print "Stack was updated.";
      } else {
        print "Stack didn't change.";
      }
      print '</p>';
    }
    return '<p>finished</p>';
  }

  /**
   * @return \Rokka\Client\Image
   */
  protected static function getRokkaClient(): \Rokka\Client\Image
  {
    return self::getRokkaInstance()->getRokkaClient();
  }

  private static function getRokkaImageObject(File $file): FileInfo
  {

    return new FileInfo(new \SplFileInfo($file->root()), null, $file);

  }

  private static function getRokkaInstance(): TemplateHelper
  {
    if (self::$rokka === null) {
      self::$rokka = new TemplateHelper(
        option('rokka.kirby.organization'),
        option('rokka.kirby.apikey'),
        new \Rokka\Kirby\RokkaCallbacks()
      );
    }
    return self::$rokka;
  }

  public static function isEnabled()
  {
    return option('rokka.kirby.enabled');
  }

  public static function getOriginalImageTag(): array
  {
    return Kirby\Text\KirbyTag::$types['image'];
  }

  private static function getStackConfiguration(string $operation, string $key): array
  {
    $stacksoptions = option('rokka.kirby.stacks.options');
    $stacks = option('rokka.kirby.stacks');
    if(!isset($stacks[$key])) {
      return [];
    }
    $options = $stacksoptions[$key] ?? [];
    switch ($operation) {
      case 'crop':
        $options['operations']['crop'] = $options['operations']['crop'] ?? [];
        return array_replace_recursive(self::DEFAULT_CROP_STACK, $options);
      case 'resize':
        $options['operations']['resize'] = $options['operations']['resize'] ?? [];
        return array_replace_recursive(self::DEFAULT_RESIZE_STACK, $options);
    }

    return self::DEFAULT_BASE_STACK;
  }
}
