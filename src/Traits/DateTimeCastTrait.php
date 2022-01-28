<?php

namespace Andaletech\Inbox\Traits;

trait DateTimeCastTrait
{
  protected function setDatetimeCasts()
  {
    if ($datetimeFormat = config('andale-inbox.eloquent.serialization.datetime_format')) {
      $casts = [];
      foreach ((array) $this->datetimeFields as $aField) {
        $casts[$aField] = 'datetime:' . $datetimeFormat;
      }
      $this->casts = array_merge(
        (array) $this->casts,
        $casts
      );
    }
  }
}
