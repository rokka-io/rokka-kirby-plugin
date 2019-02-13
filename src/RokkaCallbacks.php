<?php

namespace Rokka\Kirby;

use Kirby\Exception\LogicException;
use Rokka\Client\Core\SourceImage;
use Rokka\Client\LocalImage\AbstractLocalImage;
use Rokka\Client\TemplateHelper\AbstractCallbacks;


class RokkaCallbacks extends AbstractCallbacks
{

  public function getHash(AbstractLocalImage $image)
  {
    return Rokka::getRokkaHash($image->getContext());
  }

  public function saveHash(AbstractLocalImage $file, SourceImage $sourceImage)
  {
    try {
      $file->getContext()->update([Rokka::getRokkaHashKey() => $sourceImage->shortHash], Rokka::DEFAULT_TXT_LANG);
    } catch (LogicException $e) {
      // happens when for example an image can't be updated
      // just return the shortHash
      return $sourceImage->shortHash;
    }
    return $sourceImage->shortHash;
  }

  public function getMetadata(AbstractLocalImage $image): array
  {
    return ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($image->getContext()->url(), PHP_URL_PATH))]];
  }
}
