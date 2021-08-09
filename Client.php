<?php

namespace NoLibForIt\NNTP;

define( "NNTP_EOL", "\r\n" );
define( "NNTP_EOF", ".".NNTP_EOL );

class Client {

  public  string $host;
  public  int    $port;
  private bool   $useTOR;
  private        $errorCode;
  private        $errorMessage;

  public         $status;
  public  array  $lines;
  private        $socket;

  public function __construct( $host, $port = 119, $useTOR = false) {

    $this->host   = $host   ;
    $this->port   = $port   ;
    $this->useTOR = $useTOR ;

    if ( $this->useTOR ) {
      $this->socket = \TOR::open( $this->host, $this->port, $this->errorCode, $this->errorMessage, 10);
    } else {
      $this->socket =  fsockopen( $this->host, $this->port, $this->errorCode, $this->errorMessage, 10);
    }

    $this->readStatus();

  }

  public function disconnect() {
    if ( $this->socket ) { fclose($this->socket); }
    $this->socket = null;
  }

  private function handleEmptySocket() {
    if ( ! $this->socket ) {
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
      $this->lines[] = $this->decodeLine(substr($line,0,-2));
    }
  }

  public function decodeLine( $line ) {
    $decoded = iconv_mime_decode($line,0,"UTF-8");
    return $decoded;
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
    $this->readStatus;

    if( $this->status->code != 340 ) {
      return false;
    }

    foreach( $lines as $line ) {
      $this->send($line.NNTP_EOL);
    }
    $this->send(NNTP_EOL);
    $this->send(NNTP_EOF);
    $this->readStatus;

    return $this->status->code == 240;

  }


}

?>
