<?php

namespace Andaletech\Inbox\Http\Controllers;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Illuminate\Contracts\Container\BindingResolutionException;

class InboxController extends Controller
{
  /**
   * The responder class
   *
   * @var \Andaletech\Inbox\Http\Contracts\Response\IResponseBuilder
   */
  protected $responder;

  public function __construct()
  {
    /**
     * @var \Andaletech\Inbox\Http\Contracts\Response\IResponseBuilder
     */
    $this->responder = resolve('Andaletech\Responder');
  }

  public function index()
  {
    return response()->json(['message' => 'Hello from Andale Inbox']);
  }

  #region thread

  public function slugIndex($slug)
  {
    return response()->json(['message' => 'Hello from Andale Inbox', 'slug' => $slug]);
  }

  public function getSluggedThreads($slug, $id)
  {
    /**
     * @var \Andaletech\Inbox\Contracts\Models\IHasInbox
     */
    $owner = $this->getMappedModel($slug, $id);
    if (empty($owner)) {
      return response()->json(['message' => 'Model not found'], 404);
    }
    $query = $owner->threads()->summaryFor($owner);
    $total = $query->count();
    $threads = $this->applySkipAndTake($query)->get();
    foreach ($threads as $aThread) {
      $aThread->latestMessage()->forParticipant(get_class($owner), $owner->id)->first();
      $aThread->latestMessage->setPerspective($owner);
    }

    return $this->responder->toResponse([
      'threads' => $threads,
      'threads_sorted' => $threads/* ->sortBy(function ($aThread) {
        return $aThread->latestMessage ? $aThread->latestMessage->created_at->format('Y-m-d H:i:s') : null;
      }) */,
      'total' => $total,
    ]);
  }

  /**
   *
   *
   * @param string $slug
   * @param int|string $id
   * @return
   */
  public function createThread(Request $request, $slug, $id)
  {
    /**
     * @var \Andaletech\Inbox\Contracts\Models\IHasInbox
     */
    $owner = $this->getMappedModel($slug, $id);
    if (empty($owner)) {
      return response()->json(['message' => 'Model not found'], 404);
    }

    $owner->subject($request->get('subject'))->write($request->get('messages'));
  }

  #region messages

  public function getSluggedThreadMessages(Request $request, $slug, $id, $threadId)
  {
    /**
     * @var \Andaletech\Inbox\Contracts\Models\IHasInbox
     */
    $owner = $this->getMappedModel($slug, $id);
    if (empty($owner)) {
      return response()->json(['message' => 'Model not found'], 404);
    }
    $messageClassName = config('andale-inbox.eloquent.models.message');
    $query = $messageClassName::forThread($threadId)->for($owner)->withParticipants()->latest();
    $total = $query->count();
    $query = $this->applySkipAndTake($query);
    $messages = $query->get();
    $messages->each(function ($aMessage) use ($owner) {
      $aMessage->setPerspective($owner);
    });
    try {
      return $this->responder->toResponse([
        'messages' => $messages,
        'total' => $total,
      ]);
    } catch (Exception $ex) {
      report($ex);

      return response()->json(['errors' => $ex->getMessage()], 500);
    }
  }

  #endregion messages

  #region status

  public function markSluggedThreadAsRead(Request $request, $slug, $id, $threadId)
  {
  }

  #endregion status

  #endregion thread

  /**
   * Apply the take and skip parms to the query as provided by the request.
   * @param \Illuminate\Database\Eloquent\Builder $query
   * @return \Illuminate\Database\Eloquent\Builder
   * @throws BindingResolutionException
   * @throws NotFoundExceptionInterface
   * @throws ContainerExceptionInterface
   */
  protected function applySkipAndTake($query)
  {
    list('skip' => $skip, 'take' => $take) = $this->getSkipAndTake();
    if ($skip) {
      $query = $query->skip($skip);
    }
    if ($take) {
      $query = $query->take($take);
    }

    return $query;
  }

  protected function getSkipAndTake()
  {
    $request = request();
    $take = $request->get('take');
    if (
      empty($take) &&
      config('andale-inbox.page_size') > 0
    ) {
      $take = config('andale-inbox.page_size');
    }

    return [
      'take' => $take,
      'skip' => $request->get('skip'),
    ];
  }

  /**
   *
   *
   * @param sting $slug
   * @param string|int $id
   * @return \Andaletech\Inbox\Contracts\Models\IHasInbox|null
   */
  protected function getMappedModel($slug, $id)
  {
    $mapping = Arr::get(
      (array) config('andale-inbox.routing.slug_to_model_map'),
      $slug
    );
    if ($mapping && $id) {
      return $mapping::find($id);
    }

    return null;
  }
}
