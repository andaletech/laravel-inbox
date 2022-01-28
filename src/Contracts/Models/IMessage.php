<?php

namespace Andaletech\Inbox\Contracts\Models;

/**
 * Interface that defines a message.
 *
 * @author Kolado Sidibe <ksidibe@yahoo.com>
 * @copyright 2022 Andale Technologies, SARL.
 * @license MIT
 *
 * @property string|null $user_id
 * @property string|null $thread_id
 * @property string|null $nano_id
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $body_plain_text
 * @property string|null $from_type
 * @property string|null $from_id
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
interface IMessage
{
  /**
   * The thread to which this message belongs.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function thread();

  /**
   * The User who sent the message.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function from();

  /**
   * The recipients of this message.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function participants();
}
