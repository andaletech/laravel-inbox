<?php

namespace Andaletech\Inbox\Models\Media;

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Attachment extends Media
{
  public readonly string $COLLECTION_NAME;

  public function __construct()
  {
    $this->COLLECTION_NAME = Utils::getAttachmentCollectionName();
    static::addGlobalScope('onlyAttachmentsCollection', function (Builder $builder) {
      $builder->where('collection_name', $this->COLLECTION_NAME);
    });
  }
}
