<?php

namespace Andaletech\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Andaletech\Inbox\Traits\DateTimeCastTrait;
use Andaletech\Inbox\Contracts\Models\IGenericParticipant;

/**
 * Model for a generic participant.
 *
 * @param string $display_name
 * @param string $recipient_id
 * @param string $recipient_id_prefix
 */
class GenericParticipant extends Model implements IGenericParticipant
{
  use DateTimeCastTrait;

  protected $isInboxMultiTenant;

  protected $tenantIdColumn;

  protected $fillable = ['name', 'email', 'phone_number'];

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
    $this->table = config('andale-inbox.tables.generic_participants', 'inbox_generic_participants');
    $this->isInboxMultiTenant = config('andale-inbox.tenancy.multi_tenant');
    $this->tenantIdColumn = config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }

  public function getDisplayNameAttribute()
  {
    $name = $this->name ? $this->name : '';
    if ($this->email) {
      $name .= join('', ['<', $this->email, '>']);
    }

    return  $name;
  }

  #region query scopes

  public function scopeForTenant(Builder $query, int $tenantId)
  {
    if ($this->isInboxMultiTenant && $this->tenantIdColumn) {
      return $query->where($this->tenantIdColumn, $tenantId);
    }

    return $query;
  }

  #endregion query scopes

  #region IMessageRecipient

  public function getRecipientDisplayNameAttribute()
  {
    return $this->display_name;
  }

  public function getRecipientIdAttribute()
  {
    return $this->recipient_id_prefix . $this->id;
  }

  /**
   * @inheritDoc
   */
  public function getRecipientIdPrefixAttribute()
  {
    return 'generic_';
  }
  #endregion IMessageRecipient

  #region static/utility methods

  public static function isMultiTenant()
  {
    return config('andale-inbox.tenancy.multi_tenant');
  }

  public static function getTenantColumnId()
  {
    return config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }

  #endregion static/utility methods

  #region override parent methods.

  public function toArray()
  {
    $arr = parent::toArray();
    $arr['_id'] = join(
      '_',
      ['generic', $this->id]
    );
    $arr['_name'] = $this->display_name;

    return $arr;
  }

  #endregion override parent methods.
}
