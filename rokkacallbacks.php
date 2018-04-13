<?php


use Rokka\Client\Core\SourceImage;
use Rokka\Client\LocalImage\AbstractLocalImage;
use Rokka\Client\TemplateHelper\AbstractCallbacks;

class rokkacallbacks extends AbstractCallbacks {

  public function getHash(AbstractLocalImage $image) {
    return Rokka::getRokkaHash($image->getContext());
  }

  public function saveHash(AbstractLocalImage $file, SourceImage $sourceImage) {
    $file->getContext()->update([rokka::getRokkaHashKey() => $sourceImage->shortHash], rokka::DEFAULT_TXT_LANG);
    return $sourceImage->shortHash;
  }
  public function getMetadata(AbstractLocalImage $image): array {
    return ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($image->getContext()->url(), PHP_URL_PATH))]];
  }
}
