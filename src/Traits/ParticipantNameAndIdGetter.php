<?php

namespace Andaletech\Inbox\Traits;

trait ParticipantNameAndIdGetter
{
  public function getParticipantName()
  {
    $nameGetter = config('andale-inbox.eloquent.participant.name_attibute', 'inbox_participant_name');
    if ($nameGetter) {
      return is_callable($nameGetter) ? $nameGetter($this->participant) : $this->participant->${$nameGetter};
    }
  }

  public function getParticipantId()
  {
    $idGetter = config('andale-inbox.eloquent.participant.name_attibute', 'inbox_participant_id');
    if ($idGetter) {
      return is_callable($idGetter) ? $idGetter($this->participant) : $this->participant->${$idGetter};
    }
  }
}
