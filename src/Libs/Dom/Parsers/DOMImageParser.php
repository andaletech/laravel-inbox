<?php

namespace Andaletech\Inbox\Libs\Dom\Parsers;

use DOMDocument;
use Illuminate\Support\Arr;
use Andaletech\Inbox\Libs\Utils;
use Andaletech\Inbox\Contracts\Models\IMessage;
use Andaletech\Inbox\Contracts\DOMParser\IMessageDOMParser;

class DOMImageParser implements IMessageDOMParser
{
  public function parse(IMessage &$message, DOMDocument &$dom, ?array $contentIdMap = []) : array
  {
    $processedContentIds = [];
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $k => $img) {
      $imageSrc = $img->getAttribute('src');
      $cidPattern = '/cid:(?<contentId>\S+)/';
      $matches = [];
      preg_match($cidPattern, $imageSrc, $matches);
      if ($matches && $matchedContentId = Arr::get($matches, 'contentId')) {
        /**
         * @var \Illuminate\Http\UploadedFile
         */
        if ($contentMedia = Arr::get($contentIdMap, $matchedContentId)) {
          $fileName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $contentMedia->getClientOriginalName());
          $customProperties = [];
          if (Utils::isMultiTenant()) {
            $customProperties[Utils::getTenantColumnName()] = $message->{Utils::getTenantColumnName()};
          }
          $media = $message->addMedia($contentMedia)->usingFileName($fileName)->withCustomProperties($customProperties)->toMediaCollection(Utils::getContentMediaCollectionName());
          $url = Utils::getContentMediaUrl($media->id);
          $processedContentIds[] = $matchedContentId;
        }
        $img->removeAttribute('src');
        $img->setAttribute('src', $url);
      }
    }
    $body = $dom->getElementsByTagName('body');
    if ($body->length > 0) {
      $message->body = Utils::getNodeElementContent($body[0]);
    }
    // $message->save();

    return $processedContentIds;
  }
}
