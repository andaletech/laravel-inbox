<?php

namespace Andaletech\Inbox\Contracts\Models;

interface IHasInbox
{
  public function subject($subject) : IHasInbox;

  public function write($message, $tenantId = null) : IHasInbox;

  public function writeOnTenant($message, $tenantId) : IHasInbox;

  public function to($recipients) : IHasInbox;

  public function reply($message);

  public function attach($attachments) : IHasInbox;

  public function setAttachments($attachments) : IHasInbox;

  public function clearAttachments() : IHasInbox;

  public function setContentIdsMap(?array $cidMap) : IHasInbox;

  /**
   * Send the message
   * @param mixed $sendingUser
   * @return \Andaletech\Inbox\Models\Message
   */
  public function send($sendingUser = null);
}
