<?php

namespace Andaletech\Inbox\Http\Controllers;

use Illuminate\Http\Request;
use Andaletech\Inbox\Libs\Utils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Andaletech\Inbox\Libs\Security\PolicyActionsConstants;

class MediaController
{
  protected $contentMediaPolicy;

  public function __construct()
  {
  }
  #region content media

  /**
   * Handle request fo single content media
   *
   * @param Request $request
   * @param \Spatie\MediaLibrary\MediaCollections\Models\Media $contentMedia
   * @return Response
   */
  public function getContentMedia(Request $request, $contentMedia = null)
  {
    if (empty($contentMedia)) {
      return response(null, 404);
    }
    if (!$this->authorizeContentMediaOperation(PolicyActionsConstants::VIEW, $contentMedia)) {
      return response(null, 403);
    }

    if (0 == strcasecmp($request->get('disposition'), 'download')) {
      return $contentMedia;
    }

    return response()->stream(
      function () use ($contentMedia) {
        echo Storage::disk($contentMedia->disk)->get(Utils::getMediaRelativePath($contentMedia, true));
        // echo Storage::disk($contentMedia->disk)->get($contentMedia->getPath());
      },
      200,
      [
        'Content-Type' => $contentMedia->mime_type,
        'Content-Length' => $contentMedia->size,
        'Last-Modified' => $contentMedia->updated_at->format('D, d M Y H:i:s T'),
        'Content-Disposition' => HeaderUtils::DISPOSITION_INLINE,
      ]
    );
  }

  public function authorizeContentMediaOperation($operation = PolicyActionsConstants::VIEW, $contentMedia = null)
  {
    $contentMediaPolicy = Utils::getContentMediaPolicy();
    if ($contentMediaPolicy) {
      try {
        return $contentMediaPolicy->{$operation}(Auth::user(), $contentMedia);
      } catch (\Exception $ex) {
        return false;
      }
    }

    return true;
  }

  #endregion content media

  #region attachment

  /**
   * Handle request fo single content media
   *
   * @param Request $request
   * @param \Spatie\MediaLibrary\MediaCollections\Models\Media $contentMedia
   * @return Response
   */
  public function getAttachment(Request $request, $attachment = null)
  {
    if (empty($attachment)) {
      return response(null, 404);
    }
    if (!$this->authorizeAttachmentOperation(PolicyActionsConstants::VIEW, $attachment)) {
      return response(null, 403);
    }

    if (0 == strcasecmp($request->get('disposition'), 'inline')) {
      return response()->stream(
        function () use ($attachment) {
          echo Storage::disk($attachment->disk)->get(Utils::getMediaRelativePath($attachment, true));
        },
        200,
        [
          'Content-Type' => $attachment->mime_type,
          'Content-Length' => $attachment->size,
          'Last-Modified' => $attachment->updated_at->format('D, d M Y H:i:s T'),
          'Content-Disposition' => HeaderUtils::DISPOSITION_INLINE,
        ]
      );
    }

    return $attachment;
  }

  public function authorizeAttachmentOperation($operation = PolicyActionsConstants::VIEW, $attachment = null)
  {
    $attachmentPolicy = Utils::getAttachmentPolicy();
    if ($attachmentPolicy) {
      try {
        return $attachmentPolicy->{$operation}(Auth::user(), $attachment);
      } catch (\Exception $ex) {
        return false;
      }
    }

    return true;
  }

  #endregion attachment
}
