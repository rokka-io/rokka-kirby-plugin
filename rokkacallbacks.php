<?php


use Rokka\Client\Image\ImageAbstract;
use Rokka\Client\TemplateHelperCallbacksAbstract;

class rokkacallbacks extends TemplateHelperCallbacksAbstract {

  public function getHash(ImageAbstract $image) {
    return Rokka::getRokkaHash($image->getContext());
  }

  public function saveHash(ImageAbstract $image, string $hash) {
    $image->getContext()->update([Rokka::getRokkaHashKey() => $hash], Rokka::DEFAULT_TXT_LANG);
  }
  public function getMetadata(ImageAbstract $image): array {
    return ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($image->getContext()->url(), PHP_URL_PATH))]];
  }
}
