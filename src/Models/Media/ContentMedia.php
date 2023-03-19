<?php

namespace Andaletech\Inbox\Models\Media;

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ContentMedia extends Media
{
  public readonly string $COLLECTION_NAME;

  public function __construct()
  {
    $this->COLLECTION_NAME = Utils::getContentMediaCollectionName();
    static::addGlobalScope('onlyContentImagesCollection', function (Builder $builder) {
      $builder->where('collection_name', $this->COLLECTION_NAME);
    });
  }
}
