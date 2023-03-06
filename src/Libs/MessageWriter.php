<?php

namespace Andaletech\Inbox\Libs;

use Html2Text\Html2Text;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Events\MessageCreated;
use Andaletech\Inbox\Contracts\Models\IThread;
use Andaletech\Inbox\Contracts\Models\IMessage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 *
 * @method \Andaletech\Inbox\Libs\MessageWriter replyTo(int|IMessage $message)
 * @method static \Andaletech\Inbox\Libs\MessageWriter replyTo(int|IMessage $message)
 * @method \Andaletech\Inbox\Libs\MessageWriter subject(?string $subject = null)
 * @method static \Andaletech\Inbox\Libs\MessageWriter subject(?string $subject = null)
 * @method \Andaletech\Inbox\Libs\MessageWriter body(?string $bodyHtml = null)
 * @method static \Andaletech\Inbox\Libs\MessageWriter body(?string $bodyHtml = null)
 * @method \Andaletech\Inbox\Libs\MessageWriter recipients($recipients)
 * @method static \Andaletech\Inbox\Libs\MessageWriter recipients($recipients)
 * @method \Andaletech\Inbox\Libs\MessageWriter to($to)
 * @method static \Andaletech\Inbox\Libs\MessageWriter to($to)
 * @method \Andaletech\Inbox\Libs\MessageWriter forTenant(?int $tenantId)
 * @method static \Andaletech\Inbox\Libs\MessageWriter forTenant(?int $tenantId)
 *
 * @package Andaletech\Inbox\Libs
 */
class MessageWriter
{
  const STATICALLY_FORWARDABLES = [
    'body',
    'subject',
    'recipients',
    'replyTo',
    'from',
    'forTenant',
    'to',
    'attach',
    'setAttachments',
    'clearAttachments',
  ];

  protected ?IMessage $messageBeingRepliedTo;

  protected $from;

  /**
   * The attachments for the message being written.
   *
   * @var array
   */
  protected $attachments = [];

  public function __construct(
    protected ?string $body = null,
    protected ?string $subject = null,
    protected array|Model|string|null $recipients = null,
    protected ?int $tenantId = null
  ) {
  }

  public static function start(?string $body = null, ?string $subject = null, $recipients = null, ?int $tenantId = null)
  {
    return new static($body, $subject, $recipients, $tenantId);
  }

  public function setReplyTo(int|IMessage $message, $failIfNotFound = false)
  {
    // /**
    //  * @var \Illuminate\Database\Eloquent\Model|int|string $message
    //  */
    if (!is_object($message)) {
      $messageClassName = Utils::getMessageClassName();
      $findMethod = $failIfNotFound ? 'firstOrFail' : 'first';
      $message = $messageClassName::whereId($message)->{$findMethod}();
    }
    if (empty($message)) {
      return $this;
    }
    $this->messageBeingRepliedTo = $message;
    if (!$this->subject) {
      $this->subject = $message->subject;
    }

    return $this;
  }

  public function setSubject($subject)
  {
    $this->subject = $subject;

    return $this;
  }

  public function setBody(?string $body)
  {
    $this->body = $body;

    return $this;
  }

  public function setRecipients($recipients)
  {
    if (!is_array($recipients)) {
      $recipients = [$recipients];
    }
    $recipients = array_filter((array) $recipients, fn ($item) => $item);
    $this->recipients = $recipients;

    return $this;
  }

  public function setTo($to)
  {
    return $this->setRecipients($to);
  }

  public function setFrom($from)
  {
    $this->from = $from;

    return $this;
  }

  public function setFromGenericParticipant(string $email, ?string $name = null, ?string $phoneNumber = null, $tenantId = null)
  {
    $participant = Utils::getGenericParticipant($email, $name, $phoneNumber, $tenantId);
    $this->from($participant);

    return $this;
  }

  public function setForTenant(?int $tenantId)
  {
    $this->tenantId = $tenantId;

    return $this;
  }

  public function attach($attachments)
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
        $this->attachments[] = $anAttachment;
      }
    }

    return $this;
  }

  public function setAttachments($attachments)
  {
    $attachments = is_array($attachments) ? $attachments : [$attachments];
    $this->attachments = [];
    foreach ($attachments as $anAttachment) {
      if (($anAttachment instanceof UploadedFile) || (is_a($anAttachment, Media::class))) {
        $this->attachments[] = $anAttachment;
      }
    }

    return $this;
  }

  public function clearAttachments()
  {
    $this->attachments = [];

    return $this;
  }

  #region send

  /**
   * Send the message.
   *
   * @param Model|integer|null $sendingUser
   * @return \Andaletech\Inbox\Contracts\Models\IMessage|\Illuminate\Database\Eloquent\Model
   */
  public function send(Model|int|null $sendingUser = null, $useDbTransaction = true)
  {
    if ($useDbTransaction) {
      DB::beginTransaction();
    }
    $thread = $this->getThread();

    $message = $this->createMessage($thread, $sendingUser);
    if ($useDbTransaction) {
      DB::commit();
    }

    return $message;
  }

  #region thread

  /**
   * Return an istance of IThread (the thread) to which the new message belongs
   *
   * @return \Andaletech\Inbox\Contracts\Models\IThread
   */
  protected function getThread()
  {
    if ($this->messageBeingRepliedTo) {
      return $this->messageBeingRepliedTo->thread;
    }

    return $this->createThread();
  }

  /**
   * Create and return a IThread.
   *
   * @return \Andaletech\Inbox\Contracts\Models\IThread
   */
  protected function createThread()
  {
    $threadData = [
      'subject' => $this->subject,
      'owner_type' => get_class($this->from),
      'owner_id' => $this->from->getKey(),
      'nano_id' => Utils::getNanoId(),
    ];
    if ($this->tenantId && Utils::isMultiTenant()) {
      $threadData[Utils::getTenantColumnName() ?? 'tenant_id'] = $this->tenantId;
    }
    $threadClassName = Utils::getThreadClassName();
    $threadClassName::unguard();
    $thread = new $threadClassName($threadData);
    $threadClassName::unguard();
    $thread->save();

    return $thread;
  }

  #endregion thread

  #region message

  protected function createMessage(IThread $thread, Model|int|null $sendingUser = null)
  {
    $messageClassName = config('andale-inbox.eloquent.models.message');
    $messageData = [
      'subject' => $this->subject,
      'body' => $this->body,
      'body_plain_text' => (new Html2Text($this->body))->getText(),
      'from_type' => get_class($this->from),
      'from_id' => $this->from->getKey(),
    ];

    /**
     * @var \Andaletech\Inbox\Contracts\Models\IMessage|\Illuminate\Database\Eloquent\Model
     */
    $message = new $messageClassName($messageData);
    if ($sendingUser) {
      $message->user_id = $sendingUser instanceof Model ? $sendingUser->id : $sendingUser;
    }
    if (Utils::isMultiTenant()) {
      $tenantColumnName = Utils::getTenantColumnName() ?? 'tenant_id';
      $message->{$tenantColumnName} = $this->tenantId;
    }
    $thread->messages()->save($message);
    $message->addParticipants([$this->from, ...$this->recipients]);
    event(new MessageCreated($message));

    return $message;
  }

  #endregion message

  #endregion send

  public function _get($name)
  {
    if (in_array($name, ['from'])) {
      return $this->{$name};
    }
    $className = get_class($this);
    trigger_error("Undefined property: {$className}::\${$name}");
  }

  public function __call($methodName, $arguments)
  {
    if (self::isStaticForwardable($methodName)) {
      return $this->{static::getActualMethodName($methodName)}(...$arguments);
      // return call_user_func([$this, static::getActualMethodName($methodName)], ...$arguments);
    }
    $className = get_class($this);
    trigger_error("Undefined method: {$className}::\${$methodName}", E_USER_ERROR);
  }

  public static function __callStatic($methodName, $arguments)
  {
    if (self::isStaticForwardable($methodName)) {
      $writer = new MessageWriter();

      return $writer->{static::getActualMethodName($methodName)}(...$arguments);
      // call_user_func([$writer, static::getActualMethodName($methodName)], ...$arguments);
    }
  }

  public static function getActualMethodName($method)
  {
    return Str::camel(join('_', ['set', $method]));
  }

  public static function isStaticForwardable($methodName)
  {
    return in_array($methodName, self::STATICALLY_FORWARDABLES);
  }
}
