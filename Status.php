<?php

namespace NoLibForIt\NNTP;

class Status {

  /**
   * <https://www.w3.org/Protocols/rfc977/rfc977>
   *
   * 2.4.2. Status Responses
   *
   *   These are status reports from the server and indicate the response to
   *   the last command received from the client.
   *
   *   Status response lines begin with a 3 digit numeric code which is
   *   sufficient to distinguish all responses.  Some of these may herald
   *   the subsequent transmission of text.
   *
   *   The first digit of the response broadly indicates the success,
   *   failure, or progress of the previous command.
   *
   *      1xx - Informative message
   *      2xx - Command ok
   *      3xx - Command ok so far, send the rest of it.
   *      4xx - Command was correct, but couldn't be performed for
   *            some reason.
   *      5xx - Command unimplemented, or incorrect, or a serious
   *            program error occurred.
   *
   *   The next digit in the code indicates the function response category.
   *
   *      x0x - Connection, setup, and miscellaneous messages
   *      x1x - Newsgroup selection
   *      x2x - Article selection
   *      x3x - Distribution functions
   *      x4x - Posting
   *      x8x - Nonstandard (private implementation) extensions
   *      x9x - Debugging output
   *
   *   The exact response codes that should be expected from each command
   *   are detailed in the description of that command.  In addition, below
   *   is listed a general set of response codes that may be received at any
   *   time.
   *
   **/

  public int    $code;
  public string $message;

  public function __construct( $line ) {
    $this->code    = substr($line,0,3);
    $this->message = substr($line,4,-2);
  }

}
