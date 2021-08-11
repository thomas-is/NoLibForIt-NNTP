<?php

namespace NoLibForIt\NNTP;

use NoLibForIt\TOR\TOR as TOR;

define( "NNTP_EOL", "\r\n" );
define( "NNTP_EOF", ".".NNTP_EOL );

class Client {

  public  string $host;
  public  int    $port;
  public  bool   $useTOR;
  private        $errorCode;
  private        $errorMessage;

  public         $status;
  public  array  $lines;
  private        $socket;

  public function __construct( $host, $port = 119, $useTOR = false ) {

    $this->host   = $host   ;
    $this->port   = $port   ;
    $this->useTOR = $useTOR ;

    if ( $this->useTOR ) {
      $this->socket = TOR::open( $this->host, $this->port, $this->errorCode, $this->errorMessage, 10);
    } else {
      $this->socket = fsockopen( $this->host, $this->port, $this->errorCode, $this->errorMessage, 10);
    }

    $this->readStatus();

  }

  public function disconnect() {
    if ( $this->socket ) { fclose($this->socket); }
    $this->socket = null;
  }

  private function handleEmptySocket() {
    if ( empty($this->socket) ) {
      $this->status = new Status( "500 Not connected".NNTP_EOL );
      die();
    }
  }

  private function send( $line ) {
    $this->handleEmptySocket();
    fwrite( $this->socket, $line );
  }

  private function readStatus() {
    $this->handleEmptySocket();
    $this->status = new Status( fgets($this->socket) );
  }

  private function readLines() {
    $this->lines = array();
    $this->handleEmptySocket();
    while ( ! feof($this->socket) ) {
      $line = fgets($this->socket);
      if( $line == NNTP_EOF ) {
        break;
      }
      $this->lines[] = substr($line,0,-2);
    }
  }

  private function readArticle() {
    /**
     *  ARTICLE, BODY, HEAD, and STAT status codes
     *
     *  220 n <a> article retrieved - head and body follow
     *         (n = article number, <a> = message-id)
     *  221 n <a> article retrieved - head follows
     *  222 n <a> article retrieved - body follows
     *  223 n <a> article retrieved - request text separately
     *  412 no newsgroup has been selected
     *  420 no current article has been selected
     *  423 no such article number in this group
     *  430 no such article found
     **/
    $this->readStatus();
    switch( $this->status->code ) {
      case 220:
      case 221:
      case 222:
        $this->readLines();
        break;
      case 223:
      default:
    }
  }

  public function auth( $user, $pass ) {
    /**
      * <https://datatracker.ietf.org/doc/html/rfc4643#section-2.3.1>
      *
      * These commands MUST NOT be pipelined.
      *
      * Syntax
      *   AUTHINFO USER username
      *   AUTHINFO PASS password
      *
      * Responses
      *   281 Authentication accepted
      *   381 Password required [1]
      *   481 Authentication failed/rejected
      *   482 Authentication commands issued out of sequence
      *   502 Command unavailable [2]
      *
      * [1] Only valid for AUTHINFO USER.  Note that unlike traditional 3xx
      * codes, which indicate that the client may continue the current
      * command, the legacy 381 code means that the AUTHINFO PASS
      * command must be used to complete the authentication exchange.
      *
      * [2] If authentication has already occurred, AUTHINFO USER/PASS are
      * not valid commands (see Section 2.2).
      *
      * NOTE: Notwithstanding Section 3.2.1 of [NNTP], the server MUST
      * NOT return 480 in response to AUTHINFO USER/PASS.
      *
      * Parameters
      * username = string identifying the user/client
      * password = string representing the user's password
      *
      **/

    $this->send("AUTHINFO USER $user".NNTP_EOL);
    $this->readStatus();
    if( $this->status->code == 281 ) {
      /* 281 Ok */
      return true;
    }
    if( $this->status->code != 381 ) {
      return false;
    }
    /* 381 PASS required */
    $this->send("AUTHINFO PASS $pass".NNTP_EOL);
    $this->readStatus();
    /* 281 Ok */
    return $this->status->code == 281;
  }

  public function article($id) {
    $this->send("ARTICLE $id".NNTP_EOL);
    $this->readArticle();
  }
  public function head($id) {
    $this->send("HEAD $id".NNTP_EOL);
    $this->readArticle();
  }
  public function body($id) {
    $this->send("BODY $id".NNTP_EOL);
    $this->readArticle();
  }
  public function stat($id) {
    $this->send("STAT $id".NNTP_EOL);
    $this->readArticle();
  }

  public function help() {
    $this->send("HELP".NNTP_EOL);
    $this->readArticle();
  }

  public function quit() {
    $this->send("QUIT".NNTP_EOL);
    $this->disconnect();
  }

  public function group( $group ) {
    $this->send("GROUP $group".NNTP_EOL);
    $this->readStatus();
  }

  public function xover( $range = null ) {
    /**
     * <https://datatracker.ietf.org/doc/html/rfc2980#section-2.8>
     * The optional
     *  range argument may be any of the following:
     *     an article number
     *     an article number followed by a dash to indicate
     *        all following
     *     an article number followed by a dash followed by
     *        another article number
     **/
    $cmd="XOVER";
    if ( $range ) {
      $cmd .= " $range";
    }
    $this->send("$cmd".NNTP_EOL);
    $this->readStatus();
    /**
     * 224 Overview information follows
     * 412 No news group current selected
     * 420 No article(s) selected
     * 502 no permission
     **/
    if ( $this->status->code == 224 ) {
      $this->readLines();
    }

  }

  public function post( $lines ) {

    $this->send("POST".NNTP_EOL);
    $this->readStatus();

    if( $this->status->code != 340 ) {
      return false;
    }

    foreach( $lines as $line ) {
      $this->send($line.NNTP_EOL);
    }
    $this->send(NNTP_EOL);
    $this->send(NNTP_EOF);
    $this->readStatus();

    return $this->status->code == 240;

  }


}

?>
