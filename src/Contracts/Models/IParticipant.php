<?php

namespace Andaletech\Inbox\Contracts\Models;

/**
 * Interface that defines a message.
 *
 * @author Kolado Sidibe <ksidibe@yahoo.com>
 * @copyright 2022 Andale Technologies, SARL.
 * @license MIT
 *
 * @property string $nano_id
 * @property \Illuminate\Database\Eloquent\Model $thread
 * @property \Illuminate\Database\Eloquent\Model $message
 * @property \Illuminate\Database\Eloquent\Model $participant
 */
interface IParticipant
{
  /**
   * The thread to which this message belongs.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function thread();

  /**
   * The message to which this participant belongs.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function message();

  /**
   * The eloquent model to which this refers (for example, the user to whom the message was sent).
   *
   * @return \Illuminate\Database\Eloquent\Relations\MorphTo
   */
  public function participant();
}
