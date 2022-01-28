<?php

namespace Andaletech\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Traits\DateTimeCastTrait;

class GenericParticipant extends Model /* implements IMessageRecipient */
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
