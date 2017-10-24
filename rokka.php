<?php

use GuzzleHttp\Exception\GuzzleException;
use Rokka\Client\Factory;
use Rokka\Client\Image\SplFileInfo;
use Rokka\Client\TemplateHelper;

require(kirby()->roots()->index() . "/vendor/autoload.php");

if (c::get('plugin.rokka.enabled')) {
  include_once ("rokkacallbacks.php");
    rokka::$rokka = new TemplateHelper(c::get('plugin.rokka.organization'), c::get('plugin.rokka.apikey'), new rokkacallbacks());
}
$kirby->set('widget', 'rokka-create-stacks', __DIR__ . '/widgets');
$kirby->set('route', array(
  'pattern' => 'rokka-create-stacks',
  'action'  => function() {
    rokka::createStacks();
  }
));
rokka::$previousImageKirbyTag = Kirbytext::$tags['image'];
kirbytext::$tags['image'] = array(
  'attr' => array(
    'stack',
    'format',
    'width',
    'height',
    'alt',
    'text',
    'title',
    'class',
    'imgclass',
    'linkclass',
    'caption',
    'link',
    'target',
    'popup',
    'rel'
  ),
  'html' => function (Kirbytag $tag) {

    //Fallback to original kirby image kirby tag, if rokka is not enabled
    if (!c::get('plugin.rokka.enabled')) {
      return rokka::$previousImageKirbyTag['html']($tag);
    }

    /** @var File $file */
    $file = $tag->file($tag->attr()['image']);
    if ($file == null) {
      if (url::isAbsolute($tag->attr()['image'])){
        //use kirby image tag impl, if we have an absolute url
         return rokka::$previousImageKirbyTag['html']($tag);
      } else {
        // don't return any image tag, if the file doesn't exist
        return "";
      }
    }

    $stacks = c::get('plugin.rokka.stacks');
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
        $stack = $stacks['resize']."/$options";
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

    //FIXME: We should use self::$previousImageKirbyTag['html']($tag) and self::getImgSrc($file, $stack, $ext) here to have things like links for images
    // but I didn't find an easy way to change the attributes of a $tag object. Will investigate further
    return rokka::getImgTag($file, $stack, $ext, $tag->attr());
  }
);

$kirby->set('file::method', 'rokkaGetHash', 'self::getRokkaHash');

$kirby->set('file::method', 'rokkaCropUrl', function($file, $width, $height = 10000, $format = 'jpg') {
return rokka::getStackUrl('crop', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height-mode-fill--crop-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
});

$kirby->set('file::method', 'rokkaResizeUrl', function ($file, $width, $height = 10000, $format = 'jpg') {
  return rokka::getStackUrl('resize', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
});

$kirby->set('file::method', 'rokkaOriginalSizeUrl', function ($file, $format = 'jpg') {
    //FIXME: check for noop stack
    if (!$hash = Rokka::getHashOrUpload($file)) {
        return $file->url();
    }
    return Rokka::composeRokkaUrl($file, "dynamic/noop--options-autoformat-true-jpg.transparency.autoformat-true", $hash, $format);
});

$kirby->set('file::method', 'rokka',
  function ($file, $stack, $extension = null) {
    return rokka::getImgTag($file, $stack, $extension);
  });

kirby()->hook(['panel.file.upload', 'panel.file.replace'], function(Kirby\Panel\Models\File $file) {
    rokka::panelUpload($file);
});

class rokka {

  const DEFAULT_TXT_LANG = 'en';
  public static $previousImageKirbyTag = null;

  /**
   * @var TemplateHelper
   */
  public static $rokka = null;
  public static function panelUpload(Kirby\Panel\Models\File $file) {
    $file->update([self::getRokkaHashKey() => ""]);
  }

  public static function getSrcAttributes($url) {
      return self::$rokka->getSrcAttributes($url);
  }

  public static function getBackgroundImageStyle($url) {
      return self::$rokka->getBackgroundImageStyle($url);
  }

  public static function getImgTag(File $file = null, string $stack = null, string $extension = null, array $attr = null) {
    $attr['src'] = self::$rokka->getImageUrl(self::getRokkaImageObject($file), $stack, $extension);
    unset($attr['image']);
    return html::img($attr['src'],$attr);
  }

  public static function getStackUrl(string $operation, File $file, $width, $height, $format, $dynamicStack) {
    if (!c::get('plugin.rokka.enabled')) {
      return $file->$operation($width, $height)->url();
    }
    $rokkaImageObject = self::getRokkaImageObject($file);

    if (!$hash = self::$rokka->getHashOrUpload($rokkaImageObject)) {
      return $file->$operation($width, $height)->url();
    }
    $stacks = c::get('plugin.rokka.stacks');
    $extension = $file->extension();
    if ($extension == 'svg') {
      $stack = $stacks['raw'];
      $format = $extension;
    } else if (isset($stacks["${operation}-${width}x${height}"])) {
      $stack = $stacks["${operation}-${width}x${height}"];
    } else {
      $stack = $dynamicStack;
    }
    return self::$rokka->composeRokkaUrlWithImage($hash, $stack, $format, $rokkaImageObject);
  }

  public static function getRokkaHash($file) {
    $var = self::getRokkaHashKey();
    return $file->$var()->value(self::DEFAULT_TXT_LANG);
  }

  public static function getRokkaHashKey() {
    return "Rokkahash_". str_replace("-","_",c::get('plugin.rokka.organization'));
  }

  public static function createStacks() {
    $logged_in_user = site()->user();
    if (!$logged_in_user || !$logged_in_user->hasRole('admin')) {
      go('/');
    }
    $stacks = c::get('plugin.rokka.stacks');
    $stacksoptions = c::get('plugin.rokka.stacks.options');

    $imageClient = self::getRokkaClient();
    print '<h1>Create stacks on rokka</h1>';
    print '<h2>For organisation: ' . c::get('plugin.rokka.organization') . '</h2>';
    foreach ($stacks as $key => $rokkaStackName) {
      @list($name, $options) = explode("-",$key,2);
      print '<h2>Create stack named: '. $rokkaStackName .'</h2>';
        if (!isset($stacksoptions[$key]['resize'])) {
            $stacksoptions[$key]['resize'] = [];
        }
        if (!isset($stacksoptions[$key]['crop'])) {
            $stacksoptions[$key]['crop'] = [];
        }
      switch ($name) {
        case "crop":
        list($width,$height) = explode("x", $options);
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
          list($width,$height) = explode("x", $options);
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
        $stackoptions = array_merge ($stackoptions, $stacksoptions[$key]['options']);
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
        var_dump($e->getResponse()->getBody()->getContents());die;
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
  protected static function getRokkaClient(): \Rokka\Client\Image {
    return self::$rokka->getRokkaClient();
  }

  private static function getRokkaImageObject(File $file): SplFileInfo {
    return new SplFileInfo(new \SplFileInfo($file->root()), null, $file);
  }
}
