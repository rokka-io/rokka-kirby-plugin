<?php
use Rokka\Client\Factory;

require(kirby()->roots()->index() . "/vendor/autoload.php");

$kirby->set('widget', 'rokka-create-stacks', __DIR__ . '/widgets');
$kirby->set('route', array(
  'pattern' => 'rokka-create-stacks',
  'action'  => function() {
    Rokka::createStacks();
  }
));
Rokka::$previousImageKirbyTag = Kirbytext::$tags['image'];
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
      return Rokka::$previousImageKirbyTag['html']($tag);
    }

    /** @var File $file */
    $file = $tag->file($tag->attr()['image']);
    if ($file == null) {
      if (url::isAbsolute($tag->attr()['image'])){
        //use kirby image tag impl, if we have an absolute url
         return Rokka::$previousImageKirbyTag['html']($tag);
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

    //FIXME: We should use Rokka::$previousImageKirbyTag['html']($tag) and Rokka::getImgSrc($file, $stack, $ext) here to have things like links for images
    // but I didn't find an easy way to change the attributes of a $tag object. Will investigate further
    return Rokka::getImgTag($file, $stack, $ext, $tag->attr());
  }
);

$kirby->set('file::method', 'rokkaGetHash', 'Rokka::getRokkaHash');

$kirby->set('file::method', 'rokkaCropUrl', function($file, $width, $height = 10000, $format = 'jpg') {
return Rokka::getStackUrl('crop', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height-mode-fill--crop-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
});

$kirby->set('file::method', 'rokkaResizeUrl', function ($file, $width, $height = 10000, $format = 'jpg') {
  return Rokka::getStackUrl('resize', $file, $width, $height, $format, "dynamic/resize-width-$width-height-$height--options-autoformat-true-jpg.transparency.autoformat-true");
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
    return Rokka::getImgTag($file, $stack, $extension);
  });

kirby()->hook(['panel.file.upload', 'panel.file.replace'], function(Kirby\Panel\Models\File $file) {
  Rokka::panelUpload($file);
});

class Rokka {

  const DEFAULT_TXT_LANG = 'en';
  public static  $previousImageKirbyTag = null;

  public static function panelUpload(Kirby\Panel\Models\File $file) {
    $file->update([Rokka::getRokkaHashKey() => ""]);
  }

  public static function getSrcAttributes($url) {
    $attrs = 'src="'.$url.'"';
    $urlx2 = \Rokka\Client\UriHelper::addOptionsToUriString($url, 'options-dpr-2');
    if ($urlx2 != $url) {
      $attrs .= ' srcset="' . $urlx2 .' 2x"';
    }
    return $attrs;
  }

  public static function getBackgroundImageStyle($url) {
    $style = "background-image:url('$url');";
    $urlx2 = \Rokka\Client\UriHelper::addOptionsToUriString($url, 'options-dpr-2');
    if ($urlx2 != $url) {
      $style .= " background-image: -webkit-image-set(url('$url') 1x, url('$urlx2') 2x);";
    }
    return $style;
  }

  public static function getHashOrUpload(\File $file) {
    if (!c::get('plugin.rokka.enabled')) {
        return null;
    }
    if (!$hash = $file->rokkaGetHash()) {
      $hash = Rokka::imageUpload($file);
    }
    return $hash;
  }

  public static function imageUpload(\File $file) {
    if (!c::get('plugin.rokka.enabled')) {
      return "";
    }
    if (!($file->extension() == 'svg' || strpos(F::mime($file->root()), "image/") === 0)) {
     return "";
    }
    $imageClient = self::getRokkaClient();
    $answer = $imageClient->uploadSourceImage(
      $file->content(),
      $file->safeName(),
      '',
      ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($file->url(), PHP_URL_PATH))]]
    );
    $hash = $answer->getSourceImages()[0]->hash;
    $file->update([Rokka::getRokkaHashKey() => $hash], self::DEFAULT_TXT_LANG);
    return $hash;
  }

  public static function getImgTag(File $file = null, string $stack = null, string $extension = null, array $attr = null) {
    $attr['src'] = self::getImgSrc($file, $stack, $extension, $attr);
    unset($attr['image']);
    return html::img($attr['src'],$attr);
  }

  public static function getImgSrc(File $file = null, string $stack = null, string $extension = null) {
    if ($file == null) {
      return "";
    }

    if (!$hash = self::getHashOrUpload($file)) {
      return "";
    }

    return self::composeRokkaUrl($file, $stack, $hash, $extension);
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
      try {
        $resp = $imageClient->createStack("$rokkaStackName", $operations, '', $stackoptions, true);
      } catch (\Exception $e) {
        var_dump($e->getResponse()->getBody()->getContents());die;
      }

      print '<p>Done</p>';
      print '<p>Operations: ';
      print json_encode($resp->getStackOperations());
      print '</p>';
      print '<p>Options: ';
      print json_encode($resp->getStackOptions());
      print '</p>';
    }
  }

  /**
   * @param File $file
   * @param string $stack
   * @param string $hash
   * @param string $format
   * @return string
   */
  public static function composeRokkaUrl(File $file, string $stack, string $hash, string $format = 'jpg'): string {
    if ($format === null) {
      $format = 'jpg';
    }

    return 'https://' . c::get('plugin.rokka.organization') . ".rokka.io/$stack/$hash/" . self::rokkaSafeSeoName($file) . ".$format";
  }

  public static function getStackUrl(string $operation, File $file, $width, $height, $format, $dynamicStack) {
    if (!$hash = Rokka::getHashOrUpload($file)) {
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
    return Rokka::composeRokkaUrl($file, $stack, $hash, $format);
  }

  /**
   * @return \Rokka\Client\Image
   */
  protected static function getRokkaClient(): \Rokka\Client\Image {
    $organization = c::get('plugin.rokka.organization');
    $apiKey = c::get('plugin.rokka.apikey');
    $imageClient = Factory::getImageClient($organization, $apiKey, '');
    return $imageClient;
  }

  /**
   * @param File $file
   * @return string
   */
  protected static function rokkaSafeSeoName(File $file): string {
    $slug = str_replace(["@","."], "-", f::safeName($file->name()));
    //remove all not allowed chars
    return preg_replace('/[^0-9a-z-]/', '', $slug);

  }
}
