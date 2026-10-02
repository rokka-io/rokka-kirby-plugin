<?php

namespace Rokka\Kirby;

use Kirby\Exception\LogicException;
use Kirby\Exception\PermissionException;
use Rokka;
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
    $hash = $sourceImage->shortHash;
    $model = $file->getContext();
    Rokka::rememberHash($model, $hash);

    // Update the latest instance: in Kirby 5, updating an older one deletes its content file
    $latest = $model->parent()->file($model->filename()) ?? $model;
    try {
      kirby()->impersonate('kirby');
      $latest->update([Rokka::getRokkaHashKey() => $hash], Rokka::DEFAULT_TXT_LANG);
    } catch (LogicException|PermissionException $e) {
      // happens when for example an image can't be updated
    } finally {
      kirby()->impersonate(null);
    }
    return $hash;
  }

  public function getMetadata(AbstractLocalImage $image): array
  {
    return ['meta_user' => ['kirby_location_on_upload' => dirname(parse_url($image->getContext()->url(), PHP_URL_PATH))]];
  }
}
