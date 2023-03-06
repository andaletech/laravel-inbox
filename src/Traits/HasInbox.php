<?php

namespace Andaletech\Inbox\Traits;

use Html2Text\Html2Text;
use Andaletech\Inbox\Libs\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Events\MessageCreated;
use Illuminate\Database\Eloquent\Collection;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Andaletech\Inbox\Contracts\Models\IHasInbox;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 *
 * @var Illuminate\Database\Eloquent\Model $this
 */
trait HasInbox
{
  /**
   * @var bool
   */
  protected $isMultiTenantInbox;

  /**
   * The subject of the new message being written
   *
   * @var string
   */
  protected $inboxNewMessageSubject;

  /**
   * The content/body of the new message being written
   *
   * @var string
   */
  protected $inboxNewMessageBody;

  /**
   * The tenant id of the new message being written
   *
   * @var int|string|null
   */
  protected $inboxNewMessageTenant;

  /**
   * The recipients of the new message being written
   *
   * @var array
   */
  protected $inboxNewMessageRecipients = [];

  /**
   * @var \Andaletech\Inbox\Contracts\ |null
   */
  protected $inboxMessageBeingReplyingTo;

  /**
   * The attachments for the message being written.
   *
   * @var array
   */
  protected $inboxAttachments = [];

  /**
   *
   * @return \Illuminate\Database\Eloquent\Relations\MorphMany
   * @throws BindingResolutionException
   * @throws NotFoundExceptionInterface
   * @throws ContainerExceptionInterface
   */
  public function threads()
  {
    /**
     * @var \Illuminate\Database\Eloquent\Model $this
     */
    $query = $this->belongsToMany(
      config('andale-inbox.eloquent.models.thread'),
      config('andale-inbox.tables.participants'),
      'participant_id',
      'thread_id'
    )->where('participant_type', get_class($this))->latest('created_at')->distinct();

    if ($this->isMultitenant()) {
      $query = $query->withPivot([config('andale-inbox.tenancy.tenant_id_column', 'tenant_id')]);
    }

    return $query;
  }

  public function messages()
  {
    $query = $this->morphMany(config('andale-inbox.eloquent.models.message'), 'from');
    if ($this->isMultitenant()) {
      $query = $query->with([config('andale-inbox.tenancy.tenant_id_column', 'tenant_id')]);
    }

    return $query;
  }

  #region writing

  public function subject($subject) : IHasInbox
  {
    $this->inboxNewMessageSubject = $subject;

    return $this;
  }

  public function write($message, $tenantId = null) : IHasInbox
  {
    $this->inboxNewMessageBody = $message;

    return $this;
  }

  public function writeOnTenant($message, $tenantId) : IHasInbox
  {
    $this->inboxNewMessageBody = $message;
    $this->inboxNewMessageTenant = $tenantId;

    return $this;
  }

  public function to($recipients) : IHasInbox
  {
    if (is_array($recipients)) {
      $this->inboxNewMessageRecipients = array_merge($this->inboxNewMessageRecipients, $recipients);
    } elseif ($recipients instanceof Collection) {
      $this->inboxNewMessageRecipients = array_merge($this->inboxNewMessageRecipients, $recipients->all());
    } else {
      $this->inboxNewMessageRecipients[] = $recipients;
    }

    return $this;
  }

  public function send($sendingUser = null)
  {
    $thread = $this->getInboxThread();

    return $this->createInboxMessage($thread, $sendingUser);
  }

  public function reply($message)
  {
    // /**
    //  * @var \Illuminate\Database\Eloquent\Model|int|string $message
    //  */
    if (!is_object($message)) {
      $messageClassName = $this->getMessageClassName();
      $message = $messageClassName::whereId($message)->firstOrFail();
    }
    /**
     * @var \Andaletech\Inbox\Models\Message $message
     */
    $this->inboxMessageBeingReplyingTo = $message;
    if (!$this->inboxNewMessageSubject) {
      $this->subject($message->subject);
    }
  }
  #region attachments

  public function attach($attachments) : IHasInbox
  {
    $attachments = is_array($attachments) ? $attachments : [$attachments];
    foreach ($attachments as $anAttachment) {
      if (
        is_string($anAttachment) ||
        ($anAttachment instanceof UploadedFile) ||
        is_subclass_of($anAttachment, UploadedFile::class) ||
        ($anAttachment instanceof Media) ||
        is_subclass_of($anAttachment, Media::class)
      ) {
        $this->inboxAttachments[] = $anAttachment;
      }
    }

    return $this;
  }

  public function setAttachments($attachments) : IHasInbox
  {
    $attachments = is_array($attachments) ? $attachments : [$attachments];
    $this->inboxAttachments = [];
    foreach ($attachments as $anAttachment) {
      if (($anAttachment instanceof UploadedFile) || (is_a($anAttachment, Media::class))) {
        $this->inboxAttachments[] = $anAttachment;
      }
    }

    return $this;
  }

  public function clearAttachments() : IHasInbox
  {
    $this->inboxAttachments = [];

    return $this;
  }

  #endregion attachments

  protected function getInboxThread()
  {
    if ($this->inboxMessageBeingReplyingTo) {
      return $this->inboxMessageBeingReplyingTo->thread;
    }

    return $this->createInboxThread();
  }

  protected function createInboxThread()
  {
    $threadData = [
      'subject' => $this->inboxNewMessageSubject,
      'owner_type' => get_class($this),
      'owner_id' => $this->getKey(),
    ];
    if ($this->isMultitenant()) {
      $threadData[config('andale-inbox.tenancy.tenant_id_column', 'tenant_id')] = $this->inboxNewMessageTenant;
    }
    $threadClassName = config('andale-inbox.eloquent.models.thread');
    $thread = new $threadClassName($threadData);
    $thread->owner_type = $threadData['owner_type'];
    $thread->owner_id = $threadData['owner_id'];
    $thread->save();

    return $thread;
  }

  protected function createInboxMessage($thread, $sendingUser = null)
  {
    $messageClassName = config('andale-inbox.eloquent.models.message');
    $messageData = [
      'subject' => $this->inboxNewMessageSubject,
      'body' => $this->inboxNewMessageBody,
      'body_plain_text' => (new Html2Text($this->inboxNewMessageBody))->getText(),
      'from_type' => get_class($this),
      'from_id' => $this->id,
    ];
    /**
     * @var \Andaletech\Inbox\Models\Message
     */
    $message = new $messageClassName($messageData);
    if ($sendingUser) {
      $message->user_id = $sendingUser instanceof Model ? $sendingUser->id : $sendingUser;
    }
    if ($this->isMultitenant()) {
      $message->{config('andale-inbox.tenancy.tenant_id_column', 'tenant_id')} = $this->inboxNewMessageTenant;
    }
    $thread->messages()->save($message);
    $message->addParticipants([$this, ...$this->inboxNewMessageRecipients]);
    Utils::addAttachmentsToMessage($message, $this->inboxAttachments);
    event(new MessageCreated($message));

    return $message;
  }

  #endregion writing

  #region internal methods

  protected function isMultitenant()
  {
    return  $this->isMultiTenantInbox ?: $this->isMultiTenantInbox = config('andale-inbox.tenancy.multi_tenant');
  }

  protected function addMultitenancyColumnIfNeeded(Relation $relationQuery) : Relation
  {
    if ($this->isMultitenant()) {
      return $relationQuery->with([config('andale-inbox.tenancy.tenant_id_column', 'tenant_id')]);
    }

    return $relationQuery;
  }

  /**
   * Return the message class name.
   * @return string
   * @throws BindingResolutionException
   * @throws NotFoundExceptionInterface
   * @throws ContainerExceptionInterface
   */
  protected function getMessageClassName()
  {
    return config('andale-inbox.eloquent.models.message');
  }

  #endregion internal methods
}
