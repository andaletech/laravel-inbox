<?php

namespace Andaletech\Inbox\Contracts\Models;

interface IGenericParticipant
{
  public function getRecipientDisplayNameAttribute();

  public function getRecipientIdAttribute();

  public function getRecipientIdPrefixAttribute();
}
