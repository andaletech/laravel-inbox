<?php

namespace Andaletech\Inbox\Services;

use Andaletech\Inbox\Libs\Utils;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Contracts\Models\IHasInbox;

class MessageService
{
  public function getInboxMessages(IHasInbox $owner)
  {
    $baseQuery = Utils::getMessageQueryBuilder(get_class($owner), $owner->id);
    $query = QueryBuilder::for($baseQuery)->allowedFilters([
      AllowedFilter::callback('take', fn ($query, $value) => $query->take($value)),
      AllowedFilter::callback('skip', fn ($query, $value) => $query->offset(intval($value))),
    ]);
    $queryWithoutSkipAndTake = QueryBuilder::for($baseQuery)->allowedFilters([
      AllowedFilter::callback('take', fn ($query) => $query),
      AllowedFilter::callback('skip', fn ($query) => $query),
    ]);

    return ['messages' => $query->get(), 'total' => $queryWithoutSkipAndTake->count()];
  }

  public function getInboxMessageParticipants(Model|IHasInbox $owner, $messageId)
  {
    $query = Utils::getMessageQueryBuilder(get_class($owner), $owner->id);
    if (empty($query)) {
      return null;
    }
    $message = $query->find($messageId);
    if (empty($message)) {
      return null;
    }

    return $message->participants;
  }
}
