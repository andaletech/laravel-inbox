<?php

namespace Andaletech\Inbox\Models;

use Andaletech\Inbox\Libs\Utils;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Libs\MessageWriter;
use Illuminate\Database\Eloquent\Builder;
use Andaletech\Inbox\Traits\DateTimeCastTrait;
use Andaletech\Inbox\Contracts\Models\IMessage;

class Message extends Model implements IMessage
{
  use DateTimeCastTrait;

  protected $isInboxMultiTenant;

  protected $tenantIdColumn;

  protected $participantClass;

  protected $queryBuilderChunkSize;

  protected $perspectiveOf;

  /**
  * The attributes that can be set with Mass Assignment.
  *
  * @var array
  */
  protected $fillable = ['subject', 'body', 'body_plain_text', 'from_type', 'from_id'];

  protected $hidden = [
    'from_type',
    'from_id',
  ];

  protected $datetimeFields = [
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
    $this->table = config('andale-inbox.tables.messages', 'inbox_messages');
    $this->participantClass = config('andale-inbox.eloquent.models.participant');
    $this->isInboxMultiTenant = config('andale-inbox.tenancy.multi_tenant');
    $this->tenantIdColumn = config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
    $this->queryBuilderChunkSize = config('andale-inbox.query_builder_chunk_size', 100);
    $this->setDatetimeCasts();
  }

  protected static function boot()
  {
    parent::boot();

    static::addGlobalScope('withFrom', function (Builder $builder) {
      $builder->withFrom();
    });

    static::creating(function ($message) {
      if (empty($message->nano_id)) {
        $message->nano_id = Utils::getNanoId();
      }
    });
  }

  #region relationships

  public function thread()
  {
    return $this->belongsTo(config('andale-inbox.eloquent.models.thread'));
  }

  /**
  * Get the sender of the message
  *
  * @return \Illuminate\Database\Eloquent\Relations\MorphTo
  */
  public function from()
  {
    return $this->morphTo()/** ->withDefault(Utils::getUnkonwnParticipantData()) **/;
  }

  public function participants()
  {
    $relation = $this->hasMany(config('andale-inbox.eloquent.models.participant'));

    return $relation;
  }

  #endregion relationships

  #region query scopes

  public function scopeFor(Builder $query, Model $model)
  {
    return $query->whereHas('participants', function (Builder $subQuery) use ($model) {
      return $subQuery->where('participant_type', get_class($model))->where('participant_id', $model->id);
    });
  }

  public function scopeForThread(Builder $query, $thread)
  {
    $id = $thread instanceof Model ? $thread->id : $thread;

    return $query->where('thread_id', $id);
  }

  public function scopeWithFrom(Builder $query)
  {
    return $query->with(['from']);
  }

  public function scopeWithParticipants(Builder $query)
  {
    return $query->with(['participants']);
  }

  public function scopeForParticipant(Builder $query, $type, $id)
  {
    return $query->whereHas('participants', function ($subQuery) use ($type, $id) {
      return $subQuery->forParticipant($type, $id);
    });
  }

  #endregion query scopes

  #region override parent methods

  public function toArray()
  {
    $arr = parent::toArray();
    if ($this->relationLoaded('from')) {
      $name = Utils::getParticipantName($this->from, true);
      $id = Utils::getParticipantId($this->from, true);
      $arr['from']['_name'] = $name;
      $arr['from']['_id'] = $id;
    }

    return $this->addPerspective($arr);
  }

  protected function addPerspective(array $arr)
  {
    if ($this->perspectiveOf) {
      /**
        * @var \Andaletech\Inbox\Models\Participant
        */
      $participant = $this->participants->first(function ($aParticipant) {
        return $this->perspectiveOf->is($aParticipant->participant);
      });
      if ($participant) {
        $arr['meta'] = [
          'read_at' => $participant->read_at,
          'trashed_at' => $participant->trashed_at,
          'extra' => $participant->extra,
        ];
      }
    }

    return $arr;
  }

  #endregion override parent methods

  #region class specific methods
  /**
   * Return an instance of MessageWriter to reply to the given message.
   *
   * @return \Andaletech\Inbox\Libs\MessageWriter
   */
  public function reply()
  {
    $writer = MessageWriter::start();

    return $writer->replyTo($this);
  }

  public function addParticipants(array $participants)
  {
    $addedCount = 0;
    if ($participants) {
      foreach ($participants as $aParticipant) {
        if ($aParticipant instanceof Model) {
          $this->addParticipantEloquentModel($aParticipant);
          $addedCount++;
        } elseif (($aParticipant instanceof Builder) || ($aParticipant instanceof QueryBuilder)) {
          $aParticipant->chunk($this->queryBuilderChunkSize, function ($participantsChunk) use (&$addedCount) {
            foreach ($participantsChunk as $oneParticipant) {
              $this->addParticipantEloquentModel($oneParticipant);
              $addedCount++;
            }
          });
        } elseif ($this->addIfGenericParticipant($aParticipant)) {
          $addedCount++;
        }
      }
    }

    return $addedCount;
  }

  protected function addParticipantEloquentModel(Model $participatingModel)
  {
    $threadId = $this->thread_id;
    $messageId = $this->id;
    $participantClass = $this->participantClass;

    $payload = [
      'thread_id' => $threadId, 'message_id' => $messageId,
      'participant_type' => get_class($participatingModel), 'participant_id' => $participatingModel->id,
    ];
    $participant = $participantClass::unguarded(function () use ($participantClass, $payload) {
      return $participantClass::firstOrNew($payload);
    });
    if ($this->isInboxMultiTenant) {
      $participant->{$this->tenantIdColumn} = $this->{$this->tenantIdColumn};
    }
    $participant->save();

    return $participant;
  }

  protected function addIfGenericParticipant($participant)
  {
    if (is_string($participant)) {
      $trimmed = trim(trim($participant), '>');
      if ($trimmed) {
        $parts = explode('<', $trimmed);
        $count = count($parts);
        if ($count == 1 || $count == 2) {
          $name = $parts[0];
          $email = $parts[$count == 2 ? 1 : 0];
          if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Utils::createGenericRecipient($email, $name);
          }
        }
      }
    }
  }

  public static function isMultiTenant()
  {
    return config('andale-inbox.tenancy.multi_tenant');
  }

  public static function getTenantColumnId()
  {
    return config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }
  #endregion class specific methods

  /**
  * Set the value of perspectiveOf
  *
  * @return self
  */
  public function setPerspective(Model $model)
  {
    $this->perspectiveOf = $model;

    return $this;
  }
}
