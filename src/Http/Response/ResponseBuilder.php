<?php

namespace Andaletech\Inbox\Http\Response;

use Andaletech\Inbox\Libs\Utils;
use Andaletech\Inbox\Http\Contracts\Response\IResponseBuilder;

class ResponseBuilder implements IResponseBuilder
{
  public function toResponse(array $data, $code = 200)
  {
    $data = [
      'inbox_version' => Utils::VERSION,
      'data' => $data,
    ];

    return response()->json($data, $code);
  }
  // public function toResponse(array $data, $code = 200)
  // {
  // }
}
