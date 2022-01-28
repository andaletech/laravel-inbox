<?php

namespace Andaletech\Inbox\Http\Contracts\Response;

interface IResponseBuilder
{
  /**
   *
   * @param array $data
   * @return mixed
   */
  public function toResponse(array $data, $code = 200);
}
