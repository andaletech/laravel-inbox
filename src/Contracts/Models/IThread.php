<?php

namespace Andaletech\Inbox\Contracts\Models;

/**
 * The interface defining a message thread.
 *
 * @author Kolado Sidibe <ksidibe@yahoo.com>
 * @copyright 2022 Andale Technologies, SARL.
 * @license MIT
 */
interface IThread
{
  /**
   * The messages of this thread
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function messages();

  /**
   * The messages of this thread
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function latestMessage();

  /**
   * The participants (recipients) of the messages belonging to this thread.
   *
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function participants();
}
