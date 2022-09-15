<?php

namespace Andaletech\Inbox\Models;

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Contracts\Models\IParticipant;

/**
 * @property Carbon\Carbon $seen_at
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

  #endregion class specific methods
}
