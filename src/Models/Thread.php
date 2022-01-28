<?php

namespace Andaletech\Inbox\Models;

use Andaletech\Inbox\Libs\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Andaletech\Inbox\Contracts\Models\IThread;

/**
 * Model for a message thread.
 *
 * @property int $id The id of the thread.
 * @property string $nano_id
 * @property string $subject
 * @copyright 2022 Andale Technologies, SARL.
 * @license MIT
 */
class Thread extends Model implements IThread
{
  protected $isInboxMultiTenant;

  /**
   * The attributes that can be set with Mass Assignment.
   *
   * @var array
   */
  protected $fillable = ['subject', 'owner_type', 'owner_id'];

  // protected $hidden = [
  //   'owner_type',
  //   'owner_id',
  // ];

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
    /**
     * Make sure to set multi-tenancy field as fillable before calling the parent constructor. Or else, it won't be filled.
     */
    $this->isInboxMultiTenant = config('andale-inbox.tenancy.multi_tenant');
    if ($this->isInboxMultiTenant) {
      $this->fillable[] = config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
    }
    parent::__construct($attributes);
    $this->table = config('andale-inbox.tables.threads', 'inbox_threads');
    $this->setDatetimeCasts();
  }

  protected static function boot()
  {
    parent::boot();
    static::creating(function ($thread) {
      if (empty($thread->nano_id)) {
        $thread->nano_id = Utils::getNanoId();
      }
    });
  }

  protected function setDatetimeCasts()
  {
    if ($datetimeFormat = config('andale-inbox.eloquent.serialization.datetime_format')) {
      $casts = [];
      foreach ($this->datetimeFields as $aField) {
        $casts[$aField] = 'datetime:' . $datetimeFormat;
      }
      $this->casts = array_merge(
        (array) $this->casts,
        $casts
      );
    }
  }

  /**
   * The messages of this thread
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function messages()
  {
    return $this->hasMany(config('andale-inbox.eloquent.models.message'));
  }

  /**
   * The messages of this thread
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function latestMessage()
  {
    return $this->hasOne(config('andale-inbox.eloquent.models.message'))->latest();
  }

  public function participants()
  {
    return $this->hasMany($relation = $this->hasMany(config('andale-inbox.eloquent.models.participant')));
  }

  #region query scopes

  public function scopeSummaryFor(Builder $query, Model $model)
  {
    return $query->withMessagesCountFor($model)->withLatestMessageFor($model)/* ->orderBy('latestMessage') */;
  }

  public function scopeWithLatestMessage(Builder $query)
  {
    return $query->withCount('messages')->with(['latestMessage']);
  }

  public function scopeWithLatestMessageFor(Builder $query, Model $model, $includeParticipants = true)
  {
    return $query->with([
      'latestMessage' => function ($subQuery) use ($model) {
        return $subQuery->for($model)->with(['participants.participant']);
      },
    ]);
  }

  public function scopeWithMessagesCountFor(Builder $query, Model $model)
  {
    $query->withCount(['messages' => function (Builder $subQuery) use ($model) {
      if (empty($model)) {
        return $subQuery;
      }

      return $subQuery->for($model);
    }]);
  }

  public function scopeForParticipant(Builder $query, Model $model)
  {
  }

  #endregion query scopes
}
