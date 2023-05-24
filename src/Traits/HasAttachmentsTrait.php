<?php

namespace Andaletech\Inbox\Traits;

use Andaletech\Inbox\Libs\Utils;

trait HasAttachmentsTrait
{
  public function getAttachmentsMediaAttribute()
  {
    $clearMedia = !$this->relationLoaded('media');
    $media = $this->getMedia(Utils::getAttachmentCollectionName());
    if ($clearMedia) {
      $this->unsetRelation('media');
    }

    return $media;
  }

  public function getAttachmentsAttribute()
  {
    $attachments = [];
    if ($this->attachments_media) {
      foreach ($this->attachments_media as  $anAttachment) {
        $attachmentArray = $this->getMediaArray($anAttachment);
        $attachments[] = $attachmentArray;
      }
    }

    return $attachments;
  }

  protected function getMediaArray($media)
  {
    $description = $media->getCustomProperty('description');

    return [
      'description' => $description ?? $media->file_name,
      'id' => $media->id,
      'media' => [
        'file_name' => $media->file_name,
        'description' => $description,
        'custom_properties' => $media->custom_properties,
        'ext' => Utils::mimeToExt($media->mime_type),
        'mime_type' => $media->mime_type,
        'id' => $media->id,
        'url' => Utils::getAttachmentUrl($media->id),
        'size_raw' => $media->size,
        'size' => Utils::humanFileSize($media->size),
      ],
    ];
  }
}
