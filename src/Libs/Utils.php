<?php

namespace Andaletech\Inbox\Libs;

use Illuminate\Database\Eloquent\Model;

class Utils
{
  const VERSION = '1.0';

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

  public static function getParticipantName(Model $model)
  {
    $nameGetter = config('andale-inbox.eloquent.participant.name_attibute', 'inbox_participant_name');
    if ($nameGetter) {
      return is_callable($nameGetter) ? $nameGetter($model) : $model->{$nameGetter};
    }

    return null;
  }

  public static function getParticipantId(Model $model)
  {
    $idGetter = config('andale-inbox.eloquent.participant.id_attibute', 'inbox_participant_id');
    if ($idGetter) {
      return is_callable($idGetter) ? $idGetter($model) : $model->{$idGetter};
    }
  }

  #region participants

  /**
   *
   * @return \Andaletech\Inbox\Models\GenericParticipant
   */
  public static function createGenericRecipient(string $email, ?string $name = null, ?string $phoneNumber = null)
  {
    $name = $name ?? $email;
    $genericParticipantClassName = config('andale-inbox.eloquent.models.generic_participant');
    $data = [
      'name' => $name,
      'email' => $email,
    ];
    if ($phoneNumber) {
      $data['phone_number'] = $phoneNumber;
    }

    return $genericParticipantClassName::firstOrCreate($data);
  }

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
