<?php

namespace Andaletech\Inbox\Libs\Dom\Parsers;

use Andaletech\Inbox\Libs\Utils;
use Andaletech\Inbox\Contracts\DOMParser\IMessageDOMParser;

abstract class BaseDOMParser implements IMessageDOMParser
{
  public static function getContentMediaUrl(int $mediaId) : string
  {
    return Utils::getContentMediaUrl($mediaId);
  }
}
