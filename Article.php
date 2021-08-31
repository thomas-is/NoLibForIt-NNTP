<?php

namespace NoLibForIt\NNTP;

class Article {

  public  array   $header;
  public  array   $body;
  public  string  $charset;

  public function __construct( $lines = array() ) {
    $this->body = array();
    $this->parseHeader($lines);
    $index = array_search("",$lines);
    if( $index ) {
      $index++;
      $this->body = array_slice($lines,$index);
    }
    $this->charset = $this->encoding();
    foreach( $this->body as &$line ) {
      $decoded = iconv($this->charset,"UTF-8",$line);
      $line = $decoded ? $decoded : $line;
    }
  }

  public function encoding() {
    $charset  = @$this->header['Content-Type'];
    if( empty($charset) ) {
      return "ASCII";
    }
    $charset=str_replace("\"","",$charset);
    $charset = strtoupper($charset);
    $field='CHARSET=';
    $start=strpos($charset,$field);
    if( empty($start) ) {
      return "ASCII";
    }
    $start += strlen($field);
    $charset = substr($charset,$start);
    $end = strpos($charset,";");
    if( empty($end) ) {
      return $charset;
    }
    $charset = substr($charset,0,$end);

    return $charset;
  }

  private function parseHeader( $lines ) {

    if( ! is_array($lines) ) {
      error_log("[".__CLASS__."] array expected.");
      return;
    }

    $delimiter = ": ";

    $this->header = array();
    $key = null;

    foreach( $lines as $line ) {
      if( $line == "" ) {
        // assuming end of HEADER
        break;
      }
      // handle multi lines field
      if( $line[0] == " " || $line[0] == "\t") {
        if( $key !== null ) {
          $this->header[$key] .= " ".trim($line);
          continue;
        } else {
          // no previous key, assuming end of HEAD
          break;
        }
      }

      $k   = strpos($line,$delimiter);
      $key = substr($line,0,$k);
      if( ! $key ) {
        // no key, assuming end of HEAD
        break;
      }

      $k  += strlen($delimiter);
      $value = substr($line,$k);

      $this->header[$key] = $value;

    }

  }
}

?>
