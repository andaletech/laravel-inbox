<?php

namespace Andaletech\Inbox\Libs;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Utils
{
  const VERSION = '1.0';

  public static function getMessageByNanoQuery($nanoId, ?int $tenantId = null)
  {
    $messageClassName = static::getMessageClassName();
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    $query = $messageClassName::whereNanoId($nanoId);
    if ($tenantId && static::isMultiTenant() && $tenantColumnName = static::getTenantColumnName()) {
      $query = $query->where($tenantColumnName, $tenantId);
    }

    return $query;
  }

  #region Message

  /**
   * Returns a mesage by nano id.
   *
   * @param string $nanoId
   * @param integer|null $tenantId
   * @return \Andaletech\Inbox\Contracts\Models\IMessage|\Illuminate\Database\Eloquent\Model|null
   */
  public static function getMessageByNanoId($nanoId, ?int $tenantId = null)
  {
    $messageClassName = static::getMessageClassName();
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    $query = $messageClassName::whereNanoId($nanoId);
    if ($tenantId && static::isMultiTenant() && $tenantColumnName = static::getTenantColumnName()) {
      $query = $query->where($tenantColumnName, $tenantId);
    }

    return $query->first();
  }

  /**
   * Return the message class name.
   *
   * @return string
   */
  public static function getMessageClassName()
  {
    return config('andale-inbox.eloquent.models.message');
  }

  public static function addAttachmentsToMessage(HasMedia &$message, $attachments = null)
  {
    $mediaAdded = new Collection();
    $attachments = Arr::where(
      is_array($attachments) ? $attachments : [$attachments],
      function ($anAttachment) {
        return $anAttachment ? true : false;
      }
    );
    foreach ($attachments as $anAttachment) {
      if ($anAttachment && (($anAttachment instanceof Media) || is_subclass_of($anAttachment, Media::class))) {
        $mediaAdded->add($anAttachment->move($message, 'attachments'));
      } elseif (
        is_string($anAttachment) ||
        ($anAttachment instanceof UploadedFile) || is_subclass_of($anAttachment, UploadedFile::class)
      ) {
        $mediaAdded->add($message->addMedia($anAttachment)->toMediaCollection('attachments'));
      }
      // $message->addMedia()
    }

    return $mediaAdded;
  }

  #endregion Message

  /**
   * Return the message class name.
   *
   * @return string
   */
  public static function getThreadClassName()
  {
    return config('andale-inbox.eloquent.models.thread');
  }

  public static function getNanoId($size = 21)
  {
    return resolve('Andaletech\Inbox\NanoIdClient')->generateId($size ?? 21);
  }

  public static function getMappingSlugs()
  {
    $configMappings = (array) config('andale-inbox.routing.slug_to_model_map');
    $mappings = [];
    foreach ($configMappings as $slug => $model) {
      $mappings[] = $slug;
    }

    return $mappings;
  }

  public static function getParticipantName(?Model $model, $returnDefault = false)
  {
    if (empty($model)) {
      return $returnDefault ? Str::title(__('Unknown entity')) : null;
    }
    $nameGetter = config('andale-inbox.eloquent.participant.name_attibute', 'inbox_participant_name');
    if ($nameGetter) {
      return is_callable($nameGetter) ? $nameGetter($model) : $model->{$nameGetter};
    }

    return $returnDefault ? Str::title(__('Unknown entity')) : null;
  }

  public static function getParticipantId(?Model $model, $returnDefault = false)
  {
    if (empty($model)) {
      return $returnDefault ? 'unknown_0' : null;
    }
    $idGetter = config('andale-inbox.eloquent.participant.id_attibute', 'inbox_participant_id');
    if ($idGetter) {
      return is_callable($idGetter) ? $idGetter($model) : $model->{$idGetter};
    }

    return $returnDefault ? 'unknown_0' : null;
  }

  public static function getUnkonwnParticipantData()
  {
    return [
      '_id' => 'unknown_0',
      '_name' => Str::title(__('Unknown entity')),
      'id' => 0,
      'recipient_display_name' => Str::title(__('Unknown entity')),
      'recipient_id' => 'unknown_0',
    ];
  }

  public static function isMultiTenant()
  {
    return config('andale-inbox.tenancy.multi_tenant');
  }

  public static function getTenantColumnName()
  {
    return config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }
  #region participants

  /**
   *
   * @return \Andaletech\Inbox\Contracts\Models\IGenericParticipant
   */
  public static function getGenericParticipant(string $email, ?string $name = null, ?string $phoneNumber = null, $tenantId = null)
  {
    if (empty(filter_var($email, FILTER_VALIDATE_EMAIL)) && empty($phoneNumber)) {
      return null;
    }
    $name = $name ?? '';
    $data = [
      'name' => $name,
      'email' => $email,
    ];
    if ($phoneNumber) {
      $data['phone_number'] = $phoneNumber;
    }
    if ($tenantId && static::isMultiTenant() && $tenantColumnName = static::getTenantColumnName()) {
      $data[$tenantColumnName] = $tenantId;
    }
    $genericParticipantClassName = config('andale-inbox.eloquent.models.generic_participant');
    $genericParticipantClassName::unguard();
    $participant = $genericParticipantClassName::firstOrCreate($data);
    $genericParticipantClassName::reguard();

    return $participant;
  }

  /**
   * Return the namespaced generic participant class name
   * @return mixed
   */
  public static function getGenericParticipantClassName()
  {
    return config('andale-inbox.eloquent.models.generic_participant');
  }

  // public static function getGenericParticipant(string $email, ?string $name = null, ?string $phoneNumber = null, $tenantId = null)
  // {
  //   if ($phoneNumber) {
  //     $data['phone_number'] = $phoneNumber;
  //   }
  //   if ($tenantId && static::isMultiTenant() && $tenantColumnName = static::getTenantColumnName()) {
  //     $data[$tenantColumnName] = $tenantId;
  //   }
  // }

  public static function parseStringAsEmail($string)
  {
    $parsed = ['name' => null, 'email' => null];
    if (is_string($string)) {
      $trimmed = trim(trim($string), '>');
      if ($trimmed) {
        $parts = explode('<', $trimmed);
        $count = count($parts);
        if ($count == 1 || $count == 2) {
          $name = $parts[0];
          $email = $parts[$count == 2 ? 1 : 0];
          if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parsed['name'] = $name;
            $parsed['email'] = $email;
          }
        }
      }
    }

    return null;
  }

  #endregion participants
}
