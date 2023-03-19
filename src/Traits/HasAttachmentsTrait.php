<?php

namespace Andaletech\Inbox\Traits;

use Illuminate\Support\Arr;
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
    // $conversions = (array) Arr::get((array) $media->custom_properties, 'generated_conversions');
    // $actuallyGeneratedConversion = [];
    $previews = [];
    // foreach ($conversions as $name => $hasBeenGenerated) {
    //   if ($hasBeenGenerated) {
    //     $actuallyGeneratedConversion[] = $name;
    //     if ($name === 'preview' || $name === 'preview-md') {
    //       $splitName = explode('-', $name);
    //       $previewSuffix = null;
    //       if ($splitName && count($splitName) > 1) {
    //         $previewSuffix = $splitName[1];
    //       }
    //       // $previews[$name] = route(
    //       //   'media.get_attachment_preview',
    //       //   ['attachment' => $media->id, 'size' => $previewSuffix]
    //       // );
    //     }
    //   }
    // }

    return [
      'description' => $description ?? $media->file_name,
      'id' => $media->id,
      'media' => [
        'file_name' => $media->file_name,
        'description' => $description ? $media->getCustomProperty('description') : null,
        'custom_properties' => $media->custom_properties,
        'ext' => Utils::mimeToExt($media->mime_type),
        'mime_type' => $media->mime_type,
        'id' => $media->id,
        'url' => Utils::getAttachmentUrl($media->id), //route('media.download_attachment', ['attachment' => $media->id]),
        'size' => Utils::humanFileSize($media->size),
        // 'conversions' => $actuallyGeneratedConversion,
      ],
      'previews' => $previews,
    ];
  }
}
