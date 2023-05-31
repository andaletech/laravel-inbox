<?php

namespace Andaletech\Inbox\Models;

use DateTime;
use Andaletech\Inbox\Libs\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Andaletech\Inbox\Contracts\Models\IParticipant;

/**
 * @property Carbon\Carbon $read_at
 * @property Carbon\Carbon $trashed_at
 * @property array $tags
 * @property array $extra
 */
class Participant extends Model implements IParticipant
{
  // protected $isInboxMultiTenant;

  protected $tenantIdColumn;

  /**
   * @var string
   */
  protected $threadClass;

  /**
   * @var string
   */
  protected $messageClass;

  /**
   * The attributes that can be set with Mass Assignment.
   *
   * @var array
   */
  // protected $fillable = ['subject', 'body', 'body_plain_text', 'owner_type', 'owner_id'];

  protected $hidden = [
    'participant_type',
    'participant_id',
    'created_at',
    'updated_at',
    'deleted_at',
  ];

  /**
   * Create a new Eloquent model instance.
   *
   * @param  array $attributes
   *
   * @return void
   */
  public function __construct(array $attributes = [])
  {
    parent::__construct($attributes);
    $this->table = config('andale-inbox.tables.participants', 'inbox_participants');
    $this->threadClass = config('andale-inbox.eloquent.models.thread');
    $this->messageClass = config('andale-inbox.eloquent.models.message');
    $this->tenantIdColumn = config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }

  protected static function boot()
  {
    parent::boot();
    static::creating(function ($participant) {
      if (empty($participant->nano_id)) {
        $participant->nano_id = Utils::getNanoId();
      }
    });
  }

  #region relationships

  public function thread()
  {
    return $this->belongsTo($this->threadClass);
  }

  public function message()
  {
    return $this->belongsTo($this->messageClass);
  }

  public function participant()
  {
    return $this->morphTo()/** ->withDefault(Utils::getUnkonwnParticipantData()) **/;
  }

  #endregion relationships

  #region query scope

  public function scopeForParticipant(Builder $query, $type, $id)
  {
    return $query->where($query->qualifyColumn('participant_type'), $type)
      ->where(
        $query->qualifyColumn('participant_id'),
        $id
      );
  }

  public function scopeForMessageNanoId(Builder $query, $messageNanoId)
  {
    return $query->whereHas('message', function ($subQ) use ($messageNanoId) {
      return $subQ->where('nano_id', $messageNanoId);
    });
  }

  public function scopeForThreadId(Builder $query, $threadId)
  {
    return $query->where('thread_id', $threadId);
  }

  public function scopeWasRead(Builder $query)
  {
    return $query->whereNotNull('read_at');
  }

  public function scopeNotRead(Builder $query)
  {
    return $query->whereNull('read_at');
  }

  #endregion query scope

  #region override parent methods.

  public function toArray()
  {
    $arr = parent::toArray();
    $name = Utils::getParticipantName($this->participant);
    $id = Utils::getParticipantId($this->participant);
    $arr['_name'] = $name;
    $arr['_id'] = $id;

    return $arr;
  }

  #endregion override parent methods.

  #region class specific methods

  public function markAsRead(?DateTime $dateTime = null, $save = true)
  {
    if ($this->read_at) {
      return $this->read_at;
    }
    $this->read_at = ($dateTime ?? (new DateTime()))->format('Y-m-d H:i:s');
    if ($save) {
      $this->save();
    }

    return $this->read_at;
    ;
  }

  public function toShortArray()
  {
    $name = Utils::getParticipantName($this->participant);
    $id = Utils::getParticipantId($this->participant);

    return ['_name' => $name, '_id' => $id];
  }

  #endregion class specific methods
}
