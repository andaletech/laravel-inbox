<?php

namespace Andaletech\Inbox\Contracts\Models;

interface IHasInbox
{
  public function subject($subject) : IHasInbox;

  public function write($message, $tenantId = null) : IHasInbox;

  public function writeOnTenant($message, $tenantId) : IHasInbox;

  public function to($recipients) : IHasInbox;

  public function reply($message);

  /**
   * Send the message
   * @param mixed $sendingUser
   * @return \Andaletech\Inbox\Models\Message
   */
  public function send($sendingUser = null);
}
