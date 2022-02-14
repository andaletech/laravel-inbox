<?php

namespace Andaletech\Inbox\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Andaletech\Inbox\Contracts\Models\IMessage;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageCreated
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $message;

  /**
   * Create a new event instance.
   *
   * @return void
   */
  public function __construct(IMessage $message)
  {
    $this->message = $message;
  }
}
