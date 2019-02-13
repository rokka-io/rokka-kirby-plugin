<?php

namespace Rokka\Kirby;

use GuzzleHttp\Exception\GuzzleException;
use Kirby\Cms\File;
use Kirby\Cms\KirbyTag;
use Rokka\Client\LocalImage\FileInfo;
use Rokka\Client\TemplateHelper;

class Rokka
{
  const DEFAULT_TXT_LANG = 'en';
  public static $previousImageKirbyTag = null;

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
        return Rokka::$previousImageKirbyTag['html']($tag);

      }
    } catch (\Exception $e) {
      return Rokka::$previousImageKirbyTag['html']($tag);
    }

    $tag->value = self::getRokkaInstance()->getStackUrl($rokkaImageObject, $stack, $extension);
    return Rokka::$previousImageKirbyTag['html']($tag);
  }

  public static function getStackUrl(string $operation, File $file, $width, $height, $format, $dynamicStack)
  {
    if (!option('rokka.kirby.enabled')) {
      return $file->$operation($width, $height)->url();
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
      $stack = $dynamicStack;
    }


    return self::getRokkaInstance()->generateRokkaUrl($hash, $stack, $format, self::getRokkaInstance()->getImagename($rokkaImageObject));
  }

  public static function getOriginalSizeUrl(File $file, $format = 'jpg')
  {
    //FIXME: check for noop stack
    if (!option('rokka.kirby.enabled')) {
      return $file->url();
    }

    $rokkaImageObject = self::getRokkaImageObject($file);

    if (!$hash = self::getRokkaInstance()->getHashMaybeUpload($rokkaImageObject)) {
      return $file->url();
    }

    return index::$rokka->generateRokkaUrl(
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

  public static function createStacks()
  {
    $logged_in_user = site()->user();
    if (!$logged_in_user || !$logged_in_user->hasRole('admin')) {
      go('/');
    }
    $stacks = option('rokka.kirby.stacks');
    $stacksoptions = option('rokka.kirby.stacks.options');

    $imageClient = self::getRokkaClient();
    print '<h1>Create stacks on rokka</h1>';
    print '<h2>For organisation: ' . option('rokka.kirby.organization') . '</h2>';
    foreach ($stacks as $key => $rokkaStackName) {
      @list($name, $options) = explode("-", $key, 2);
      print '<h2>Create stack named: ' . $rokkaStackName . '</h2>';
      if (!isset($stacksoptions[$key]['resize'])) {
        $stacksoptions[$key]['resize'] = [];
      }
      if (!isset($stacksoptions[$key]['crop'])) {
        $stacksoptions[$key]['crop'] = [];
      }
      switch ($name) {
        case "crop":
          list($width, $height) = explode("x", $options);
          $resize = new \Rokka\Client\Core\StackOperation('resize', array_merge(['width' => $width, 'height' => $height, 'mode' => 'fill'], $stacksoptions[$key]['resize']));
          $crop = new \Rokka\Client\Core\StackOperation('crop', array_merge(['width' => $width, 'height' => $height], $stacksoptions[$key]['crop']));
          $operations = [$resize, $crop];
          break;
        case "noop":
        case "raw":
          $operations = [];
          break;
        case "resize":
          if ($options) {
            list($width, $height) = explode("x", $options);
            $resize = new \Rokka\Client\Core\StackOperation('resize', array_merge(['height' => $height, 'width' => $width], $stacksoptions[$key]['resize']));
          } else {
            $resize = new \Rokka\Client\Core\StackOperation('resize', array_merge(['width' => 9999], $stacksoptions[$key]['resize']));
          }
          $operations = [$resize];
          break;
        default;
          print "Nothing done, no rules for $key";
          continue 2;
      }
      if ($name == "raw") {
        $stackoptions = ['source_file' => true];
      } else {
        $stackoptions = ['autoformat' => true, 'jpg.transparency.autoformat' => 'true'];
      }
      if (isset($stacksoptions[$key]['options'])) {
        $stackoptions = array_merge($stackoptions, $stacksoptions[$key]['options']);
      }
      $startTime = (new \DateTime())->sub(new DateInterval("PT1S"));
      try {
        $stack = new \Rokka\Client\Core\Stack('', $rokkaStackName);
        $stack->setStackOperations($operations);
        $stack->setStackOptions($stackoptions);
        if (isset($stacksoptions[$key]['options-retina'])) {
          $expr = new \Rokka\Client\Core\StackExpression("options.dpr > 1.5", $stacksoptions[$key]['options-retina']);
          $stack->setStackExpressions([$expr]);
        }
        $resp = $imageClient->saveStack($stack, ['overwrite' => true]);
      } catch (GuzzleException $e) {
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
      print '</p>';
      print '<p>';
      if ($startTime <= $resp->getCreated()) {
        print "Stack was updated.";
      } else {
        print "Stack didn't change.";
      }
      print '</p>';
    }
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
      self::$rokka = new TemplateHelper(option('rokka.kirby.organization'), option('rokka.kirby.apikey'), new \Rokka\Kirby\RokkaCallbacks());
    }
    return self::$rokka;
  }

  public static function isEnabled()
  {
    return option('rokka.kirby.enabled');
  }
}
