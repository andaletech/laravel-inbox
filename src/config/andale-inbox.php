<?php

use Andaletech\Inbox\Models\Media\Attachment;
use Andaletech\Inbox\Contracts\Models\IMessage;
use Andaletech\Inbox\Models\Media\ContentMedia;

return [
  /*
  |--------------------------------------------------------------------------
  | Media related configs
  |--------------------------------------------------------------------------
  |
  |
  |
  */
  'media' => [
    /**
     * Drive where to store media
     */
    'storage_disk' => 'local',
    'attachments' => [
      'model' => Attachment::class,
      'collection_name' => 'inboxAttachments',
      'accepts_file' => function ($file) {
        return true;
        /**
         * Below is an example to only accept files of image mime types
         */
        // return Str::startsWith($file->mimeType, 'image/');
      },
    ],

    'content_media' => [
      'model' => ContentMedia::class,
      'collection_name' => 'inboxContentMedia',
      // Not yet "wired" (i.e not operational)
      'accepts_file' => function ($file) {
        return true;
        /**
         * Below is an example to only accept files of image mime types
         */
        // return Str::startsWith($file->mimeType, 'image/');
      },
      // img tags are always parsed
      'dom_parsers' => [
        function (IMessage $message, DOMDocument $dom) {},
      ],
      // img tags are always parsed
      // 'additional_html_tags_to_parse' => [],
    ],
    'routes' => [
      'content_media' => [
        'name' => 'contentMedia',
      ],
    ],
  ],
  /*
  |--------------------------------------------------------------------------
  | Inbox Tables Name
  |--------------------------------------------------------------------------
  |
  |
  |
  */
  'tables' => [
    'threads' => 'inbox_threads',
    'messages' => 'inbox_messages',
    'participants' => 'inbox_participants',
    'generic_participants' => 'inbox_generic_participants',
  ],
  /*
  |--------------------------------------------------------------------------
  | Models
  |--------------------------------------------------------------------------
  |
  | If you want to overwrite any model you should change it here as well.
  |
  */

  'eloquent' => [
    'models' => [
      'thread' => Andaletech\Inbox\Models\Thread::class,
      'message' => Andaletech\Inbox\Models\Message::class,
      'participant' => Andaletech\Inbox\Models\Participant::class,
      'generic_participant' => Andaletech\Inbox\Models\GenericParticipant::class,
    ],
    'participant' => [
      'name_attibute' => 'inbox_participant_name',
      'id_attibute' => 'inbox_participant_id',
    ],
    'serialization' => [
      'datetime_format' => null, //null = default laravel format which depends on your version of Laravel,
      'serializers' => [
        'thread' => null,
        'message' => null,
        'participant' => null,
      ],
    ],
  ],

  'tenancy' => [
    'multi_tenant' => false,
    'tenant_id_column' => 'tenant_id',
    'currentTenantId' => null,
    /*
     * // Here's an example of how to set this config item
     * 'currentTenantId' => fn () => resolve('CurrentTenantId'),
     */
  ],

  'query_builder_chunk_size' => 100,
  /*
  |--------------------------------------------------------------------------
  | Inbox Notification
  |--------------------------------------------------------------------------
  |
  | Via Supported: "mail", "database", "array"
  |
  */

  'notifications' => [
    'via' => [
      'mail',
    ],
  ],
  /*
  |--------------------------------------------------------------------------
  | Routing
  |--------------------------------------------------------------------------
  |
  | route slug to model map
  |
  */
  'routing' => [
    'prefix' => 'andale-inbox',
    'middleware' => ['web', 'auth'],
    'name' => 'andale-inbox',
    'id_pattern' => '[0-9]+',
    'content_media' => [
      'name' => 'contentMedia',
      // 'prefix' => '',
      'routeParam' => 'andaleInboxContentMedia',
      'idPattern' => '[0-9]+',
    ],
    'attachments' => [
      'name' => 'attachments',
      // 'prefix' => '',
      'routeParam' => 'andaleInboxAttachment',
      'idPattern' => '[0-9]+',
    ],

    /**
     * For example in messaging route user_101 will  be mapped to user model with id 101.
     * student_89 will be mapped to student model with id 89
     */
    'slug_to_model_map' => [
      // 'users' => 'App\Models\User\User',
      // 'student' => 'App\Models\Student\Student,
    ],
    'responder' => 'Andaletech\Inbox\Http\Response\ResponseBuilder',
  ],

  'page_size' => 10,
];
