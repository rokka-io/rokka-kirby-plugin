<?php


use Rokka\Client\Core\SourceImage;
use Rokka\Client\LocalImage\LocalImageAbstract;
use Rokka\Client\TemplateHelperCallbacksAbstract;

class rokkacallbacks extends TemplateHelperCallbacksAbstract {

  public function getHash(LocalImageAbstract $image) {
    return Rokka::getRokkaHash($image->getContext());
  }

  public function saveHash(LocalImageAbstract $file, SourceImage $sourceImage) {
    $file->getContext()->update([rokka::getRokkaHashKey() => $sourceImage->shortHash], rokka::DEFAULT_TXT_LANG);
    return $sourceImage->shortHash;
  }
  public function getMetadata(LocalImageAbstract $image): array {
    return ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($image->getContext()->url(), PHP_URL_PATH))]];
  }
}
