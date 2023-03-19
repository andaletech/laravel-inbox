<?php

namespace Andaletech\Inbox\Libs;

use DOMNode;
use DOMDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use Andaletech\Inbox\Models\Media\Attachment;
use Andaletech\Inbox\Contracts\Models\IMessage;
use Andaletech\Inbox\Models\Media\ContentMedia;
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
   * Process attachments and inline image from the incoming message.
   *
   * @param IMessage|HasMedia $message
   * @param [type] $payload
   * @return void
   */
  public static function processMessageBodyMedia(IMessage &$message, ?array $contentIdMap = [])
  {
    $processedContentIds = [];
    if (is_array($contentIdMap) && $message->body) {
      $dom = new DOMDocument();

      /**
       * mb_convert_encoding is needed in order to handle characters with accents.
       * Alternative ways could be used. The poin is simply to tell the dom parser
       * that the charset is utf-8.
       */
      $dom->loadHTML(mb_convert_encoding($message->body, 'HTML-ENTITIES', 'UTF-8'));
      $images = $dom->getElementsByTagName('img');
      foreach ($images as $k => $img) {
        $imageSrc = $img->getAttribute('src');
        $cidPattern = '/cid:(?<contentId>\S+)/';
        $matches = [];
        preg_match($cidPattern, $imageSrc, $matches);
        if ($matches && $matchedContentId = Arr::get($matches, 'contentId')) {
          /**
           * @var \Illuminate\Http\UploadedFile
           */
          if ($contentMedia = Arr::get($contentIdMap, $matchedContentId)) {
            $fileName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $contentMedia->getClientOriginalName());
            $customProperties = [];
            if (self::isMultiTenant()) {
              $customProperties[self::getTenantColumnName()] = $message->{self::getTenantColumnName()};
            }
            $media = $message->addMedia($contentMedia)->usingFileName($fileName)->withCustomProperties($customProperties)->toMediaCollection(self::getContentMediaCollectionName());
            $url = self::getContentMediaUrl($media->id);
            $processedContentIds[] = $matchedContentId;
          }
          $img->removeAttribute('src');
          $img->setAttribute('src', $url);
        }
      }
      $body = $dom->getElementsByTagName('body');
      if ($body->length > 0) {
        $message->body = self::getNodeElementContent($body[0]);
      }
      $message->save();
    }

    return $processedContentIds;
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

  public static function addAttachmentsToMessage(HasMedia|Model &$message, $attachments = null, $keysToIgnore = null)
  {
    $mappedKeysToIgnore = [];
    foreach ($keysToIgnore as $aKey) {
      $mappedKeysToIgnore[$aKey] = true;
    }
    $mediaAdded = new Collection();
    $attachments = Arr::where(
      is_array($attachments) ? $attachments : [$attachments],
      function ($anAttachment, $key) use ($mappedKeysToIgnore) {
        return $anAttachment && Arr::get($mappedKeysToIgnore, $key) == false ? true : false;
      }
    );
    foreach ($attachments as $anAttachment) {
      if ($anAttachment && (($anAttachment instanceof Media) || is_subclass_of($anAttachment, Media::class))) {
        $mediaAdded->add($anAttachment->move($message, 'attachments'));
      } elseif (
        is_string($anAttachment) ||
        ($anAttachment instanceof UploadedFile) || is_subclass_of($anAttachment, UploadedFile::class)
      ) {
        $mediaAdded->add($message->addMedia($anAttachment)->toMediaCollection(self::getAttachmentCollectionName()));
      }
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

  public static function isMultiTenant() : bool
  {
    return config('andale-inbox.tenancy.multi_tenant') ? true : false;
  }

  public static function getTenantColumnName()
  {
    return config('andale-inbox.tenancy.tenant_id_column', 'tenant_id');
  }

  #region string

  public static function trimAdditional($subject, $whatToTrim = null)
  {
    return trim(
      trim($subject, (string) $whatToTrim)
    );
  }

  #endregion string

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

  public static function getParticipantsTableName() : string
  {
    return config('andale-inbox.tables.participants', 'inbox_participants');
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

  #region Media

  public static function getContentMediaModel() : string
  {
    return config('andale-inbox.media.content_media.model', ContentMedia::class);
  }

  public static function getContentMediaCollectionName() : string
  {
    return config('andale-inbox.media.content_media.collection_name', 'inboxContentMedia');
  }

  public static function getAttachmentModel() : string
  {
    return config('andale-inbox.media.attachments.model', Attachment::class);
  }

  public static function getAttachmentCollectionName() : string
  {
    return config('andale-inbox.media.attachment.collection_name', 'inboxAttachments');
  }

  #endregion Media

  #region HTML DOM manipulations

  public static function addTargetBlankToATags(string $html)
  {
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    foreach ($doc->getElementsByTagName('a') as $aLink) {
      $aLink->setAttribute('target', '_blank');
    }
    $body = $doc->getElementsByTagName('body');
    if (count($body)) {
      return self::getNodeElementContent($body[0]);
    }

    return $html;
  }

  public static function getNodeElementContent(DOMNode $element)
  {
    $innerHTML = '';
    $children = $element->childNodes;

    foreach ($children as $child) {
      $innerHTML .= $element->ownerDocument->saveHTML($child);
    }

    return $innerHTML;
  }

  public static function getPlainText($html)
  {
    if ($html) {
      $dom = new DOMDocument();
      /**
       * mb_convert_encoding is needed in order to handle characters with accents.
       * Alternative ways could be used. The poin is simply to tell the dom parser
       * that the charset is utf-8.
       */
      libxml_use_internal_errors(true);
      $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
      libxml_clear_errors();

      return strip_tags($dom->textContent);
    }

    return $html;
  }

  public static function getHtmlBody($fullHtml)
  {
    if ($fullHtml) {
      $dom = new DOMDocument();
      /**
      * mb_convert_encoding is needed in order to handle characters with accents.
      * Alternative ways could be used. The poin is simply to tell the dom parser
      * that the charset is utf-8.
      */
      libxml_use_internal_errors(true);
      $dom->loadHTML(mb_convert_encoding($fullHtml, 'HTML-ENTITIES', 'UTF-8'));
      libxml_clear_errors();
      $body = $dom->getElementsByTagName('body');
      if ($body->length > 0) {
        return static::getNodeElementContent($body[0]);
      }
    }

    return $fullHtml;
  }

  #endregion HTML DOM manipulations

  #region authorizations

  public static function getContentMediaPolicy()
  {
    return Gate::getPolicyFor(self::getContentMediaModel());
  }

  public static function getAttachmentPolicy()
  {
    return Gate::getPolicyFor(self::getAttachmentModel());
  }

  #endregion authorizations

  #region routing

  public static function getTopRouteName()
  {
    return self::trimAdditional(config('andale-inbox.routing.name', 'andale-inbox'), '.');
  }

  #region contentMedia

  public static function getContentMediaRouteName()
  {
    return self::trimAdditional(config('andale-inbox.routing.content_media.name', 'contentMedia'), '.');
  }

  public static function getContentMediaRouteParamName()
  {
    return config('andale-inbox.routing.content_media.routeParam', 'andaleInboxContentMedia');
  }

  public static function getContentMediaRouteParamPattern()
  {
    return config('andale-inbox.routing.content_media.idPattern', '[0-9]+');
  }

  public static function getContentMediaRouteSlug()
  {
    return join('', ['/', '{', self::getContentMediaRouteParamName(), '}']);
  }

  public static function getContentMediaUrl($mediaId)
  {
    $fullRouteName = join(
      '.',
      [self::getTopRouteName(), 'media', self::getContentMediaRouteName(), 'getSingleContentMedia']
    );
    try {
      $routeParam = [self::getContentMediaRouteParamName() => $mediaId];

      return route($fullRouteName, [self::getContentMediaRouteParamName() => $mediaId]);
    } catch (\Exception $ex) {
    }

    return null;
  }

  #endregion contentMedia

  #region attachments

  public static function getAttachmentsRouteName()
  {
    return self::trimAdditional(config('andale-inbox.routing.attachments.name', 'attachments'), '.');
  }

  public static function getAttachmentsRouteParamName()
  {
    return self::trimAdditional(config('andale-inbox.routing.attachments.routeParam', 'andaleInboxAttachment'));
  }

  public static function getAttachmentRouteSlug()
  {
    return join('', ['/', '{', self::getAttachmentsRouteParamName(), '}']);
  }

  public static function getAttachmentRouteParamPattern()
  {
    return config('andale-inbox.routing.attachments.idPattern', '[0-9]+');
  }

  public static function getAttachmentUrl($mediaId)
  {
    $fullRouteName = join(
      '.',
      [self::getTopRouteName(), 'media', self::getAttachmentsRouteName(), 'getSingleAttachment']
    );
    try {
      $routeParam = [self::getAttachmentsRouteParamName() => $mediaId];

      return route($fullRouteName, [self::getAttachmentsRouteParamName() => $mediaId]);
    } catch (\Exception $ex) {
    }

    return null;
  }

  #endregion attachments

  #endregion routing

#region Spatie MediaLibrary

public static function getMediaRelativePath(Media $media, $includeName = false)
{
  /**
   * @var \Spatie\MediaLibrary\Support\PathGenerator\PathGenerator
   */
  $pathGenerator = app(config('media-library.path_generator'));
  $path = $pathGenerator->getPath($media);

  return $includeName ? "$path{$media->file_name}" : $path;
}

#endregion Spatie MediaLibrary

#region mime

  /**
   * convert a mime type into a possible extention.
   *
   * @see https://stackoverflow.com/a/53662733/853130
   * @param string $mime
   * @return string|null
   */
  public static function mimeToExt($mime)
  {
    $mimeMap = [
      'video/3gpp2' => '3g2', 'video/3gp' => '3gp', 'video/3gpp' => '3gp',
      'application/x-compressed' => '7zip', 'audio/x-acc' => 'aac',
      'audio/ac3' => 'ac3', 'application/postscript' => 'ai',
      'audio/x-aiff' => 'aif', 'audio/aiff' => 'aif',
      'audio/x-au' => 'au', 'video/x-msvideo' => 'avi',
      'video/msvideo' => 'avi', 'video/avi' => 'avi',
      'application/x-troff-msvideo' => 'avi', 'application/macbinary' => 'bin',
      'application/mac-binary' => 'bin', 'application/x-binary' => 'bin',
      'application/x-macbinary' => 'bin', 'image/bmp' => 'bmp',
      'image/x-bmp' => 'bmp', 'image/x-bitmap' => 'bmp',
      'image/x-xbitmap' => 'bmp', 'image/x-win-bitmap' => 'bmp',
      'image/x-windows-bmp' => 'bmp', 'image/ms-bmp' => 'bmp',
      'image/x-ms-bmp' => 'bmp', 'application/bmp' => 'bmp',
      'application/x-bmp' => 'bmp', 'application/x-win-bitmap' => 'bmp',
      'application/cdr' => 'cdr', 'application/coreldraw' => 'cdr',
      'application/x-cdr' => 'cdr',
      'application/x-coreldraw' => 'cdr',
      'image/cdr' => 'cdr',
      'image/x-cdr' => 'cdr',
      'zz-application/zz-winassoc-cdr' => 'cdr',
      'application/mac-compactpro' => 'cpt',
      'application/pkix-crl' => 'crl',
      'application/pkcs-crl' => 'crl',
      'application/x-x509-ca-cert' => 'crt',
      'application/pkix-cert' => 'crt',
      'text/css' => 'css',
      'text/x-comma-separated-values' => 'csv',
      'text/comma-separated-values' => 'csv',
      'application/vnd.msexcel' => 'csv',
      'application/x-director' => 'dcr',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
      'application/x-dvi' => 'dvi',
      'message/rfc822' => 'eml',
      'application/x-msdownload' => 'exe',
      'video/x-f4v' => 'f4v',
      'audio/x-flac' => 'flac',
      'video/x-flv' => 'flv',
      'image/gif' => 'gif',
      'application/gpg-keys' => 'gpg',
      'application/x-gtar' => 'gtar',
      'application/x-gzip' => 'gzip',
      'application/mac-binhex40' => 'hqx',
      'application/mac-binhex' => 'hqx',
      'application/x-binhex40' => 'hqx',
      'application/x-mac-binhex40' => 'hqx',
      'text/html' => 'html',
      'image/x-icon' => 'ico',
      'image/x-ico' => 'ico',
      'image/vnd.microsoft.icon' => 'ico',
      'text/calendar' => 'ics',
      'application/java-archive' => 'jar',
      'application/x-java-application' => 'jar',
      'application/x-jar' => 'jar',
      'image/jp2' => 'jp2',
      'video/mj2' => 'jp2',
      'image/jpx' => 'jp2',
      'image/jpm' => 'jp2',
      'image/jpeg' => 'jpeg',
      'image/pjpeg' => 'jpeg',
      'application/x-javascript' => 'js',
      'application/json' => 'json',
      'text/json' => 'json',
      'application/vnd.google-earth.kml+xml' => 'kml',
      'application/vnd.google-earth.kmz' => 'kmz',
      'text/x-log' => 'log',
      'audio/x-m4a' => 'm4a',
      'audio/mp4' => 'm4a',
      'application/vnd.mpegurl' => 'm4u',
      'audio/midi' => 'mid',
      'application/vnd.mif' => 'mif',
      'video/quicktime' => 'mov',
      'video/x-sgi-movie' => 'movie',
      'audio/mpeg' => 'mp3',
      'audio/mpg' => 'mp3',
      'audio/mpeg3' => 'mp3',
      'audio/mp3' => 'mp3',
      'video/mp4' => 'mp4',
      'video/mpeg' => 'mpeg',
      'application/oda' => 'oda',
      'audio/ogg' => 'ogg',
      'video/ogg' => 'ogg',
      'application/ogg' => 'ogg',
      'application/x-pkcs10' => 'p10',
      'application/pkcs10' => 'p10',
      'application/x-pkcs12' => 'p12',
      'application/x-pkcs7-signature' => 'p7a',
      'application/pkcs7-mime' => 'p7c',
      'application/x-pkcs7-mime' => 'p7c',
      'application/x-pkcs7-certreqresp' => 'p7r',
      'application/pkcs7-signature' => 'p7s',
      'application/pdf' => 'pdf',
      'application/octet-stream' => 'pdf',
      'application/x-x509-user-cert' => 'pem',
      'application/x-pem-file' => 'pem',
      'application/pgp' => 'pgp',
      'application/x-httpd-php' => 'php',
      'application/php' => 'php',
      'application/x-php' => 'php',
      'text/php' => 'php',
      'text/x-php' => 'php',
      'application/x-httpd-php-source' => 'php',
      'image/png' => 'png',
      'image/x-png' => 'png',
      'application/powerpoint' => 'ppt',
      'application/vnd.ms-powerpoint' => 'ppt',
      'application/vnd.ms-office' => 'ppt',
      'application/msword' => 'ppt',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
      'application/x-photoshop' => 'psd',
      'image/vnd.adobe.photoshop' => 'psd',
      'audio/x-realaudio' => 'ra',
      'audio/x-pn-realaudio' => 'ram',
      'application/x-rar' => 'rar',
      'application/rar' => 'rar',
      'application/x-rar-compressed' => 'rar',
      'audio/x-pn-realaudio-plugin' => 'rpm',
      'application/x-pkcs7' => 'rsa',
      'text/rtf' => 'rtf',
      'text/richtext' => 'rtx',
      'video/vnd.rn-realvideo' => 'rv',
      'application/x-stuffit' => 'sit',
      'application/smil' => 'smil',
      'text/srt' => 'srt',
      'image/svg+xml' => 'svg',
      'application/x-shockwave-flash' => 'swf',
      'application/x-tar' => 'tar',
      'application/x-gzip-compressed' => 'tgz',
      'image/tiff' => 'tiff',
      'text/plain' => 'txt',
      'text/x-vcard' => 'vcf',
      'application/videolan' => 'vlc',
      'text/vtt' => 'vtt',
      'audio/x-wav' => 'wav',
      'audio/wave' => 'wav',
      'audio/wav' => 'wav',
      'application/wbxml' => 'wbxml',
      'video/webm' => 'webm',
      'image/webp' => 'webp',
      'audio/x-ms-wma' => 'wma',
      'application/wmlc' => 'wmlc',
      'video/x-ms-wmv' => 'wmv',
      'video/x-ms-asf' => 'wmv',
      'application/xhtml+xml' => 'xhtml',
      'application/excel' => 'xl',
      'application/msexcel' => 'xls',
      'application/x-msexcel' => 'xls',
      'application/x-ms-excel' => 'xls',
      'application/x-excel' => 'xls',
      'application/x-dos_ms_excel' => 'xls',
      'application/xls' => 'xls',
      'application/x-xls' => 'xls',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
      'application/vnd.ms-excel' => 'xlsx',
      'application/xml' => 'xml',
      'text/xml' => 'xml',
      'text/xsl' => 'xsl',
      'application/xspf+xml' => 'xspf',
      'application/x-compress' => 'z',
      'application/x-zip' => 'zip',
      'application/zip' => 'zip',
      'application/x-zip-compressed' => 'zip',
      'application/s-compressed' => 'zip',
      'multipart/x-zip' => 'zip',
      'text/x-scriptzsh' => 'zsh',
    ];

    return isset($mimeMap[$mime]) ? $mimeMap[$mime] : false;
  }

  #endregion mime

  #region dirs and files

  /**
  * Returns the size of the backup in Megabytes.
  *
  * @param int|string $value
  * @return number
  */
  public static function sizeInKB($value)
  {
    return $value / pow(1024, 1);
  }

  /**
  * Returns the size of the backup in Megabytes.
  *
  * @param innt|string $value
  * @return number
  */
  public static function sizeInMB($value)
  {
    return $value / pow(1024, 2);
  }

  /**
  * Returns the size of the backup in Gigabytes.
  *
  * @param int|string $value
  * @return number
  */
  public static function sizeInGB($value)
  {
    return $value / pow(1024, 3);
  }

  public static function humanFileSize($bytes, $decimals = 2)
  {
    $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
  }

  #endregion dirs and files
}
