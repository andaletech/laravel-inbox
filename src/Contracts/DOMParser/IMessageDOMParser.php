<?php

namespace Andaletech\Inbox\Contracts\DOMParser;

use DOMDocument;
use Andaletech\Inbox\Contracts\Models\IMessage;

interface IMessageDOMParser
{
  public function parse(IMessage &$message, DOMDocument &$dom, ?array $contentIdMap = []) : array;
}
