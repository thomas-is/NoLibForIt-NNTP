<?php


namespace NoLibForIt\NNTP;

class Group {

  private Client $nntp         ;
  public  int    $messageCount ;
  public  int    $idFirst      ;
  public  int    $idLast       ;
  public  string $name         ;
  public  array  $overview = [];

  public function __construct( $name, $nntp ) {

    $this->nntp = $nntp;
    $this->nntp->group($name);

    if( $this->nntp->status->code != 211 ) {
      error_log( "[".__CLASS__."] {$this->nntp->status->code} {$this->nntp->status->message}");
      return;
    }

    $props = explode(" ",$this->nntp->status->message);
    $this->messageCount = @$props[0];
    $this->idFirst      = @$props[1];
    $this->idLast       = @$props[2];
    $this->name         = @$props[3];

  }

  public function xover( $first, $last = null ) {

    /**
     * $last is optional
     *
     *  $this->xover(1234)
     *    XOVER 1234-
     *
     *  $this->xover(1234-1256)
     *    XOVER 1234-1256
     *
     */

    $this->overview = array();

    $this->nntp->group($this->name);

    $first = (int) $first;
    if ( empty($last) ) {
      $last = "";
    } else {
      $last = (int) $last;
    }

    $this->nntp->xover("$first-$last");
    $lines = $this->nntp->lines;
    foreach( $lines as $line ) {

      $element                 = array();
      $props                   = explode("\t",$line);

      $element['Id']           = (string) @$props[0];
      $element['Subject']      = (string) @$props[1];
      $element['From']         = (string) @$props[2];
      $element['Date']         = (string) @$props[3];
      $element['Message-ID']   = (string) @$props[4];
      $element['References']   = (string) @$props[5];
      $element['Size']         = (string) @$props[6];
      $element['Lines']        = (string) @$props[7];
      $element['Xref']         = (string) @$props[8];

      /* mime decode Subject and From */
      $element['Subject'] = iconv_mime_decode($element['Subject'],2,"UTF-8");
      $element['From']    = iconv_mime_decode($element['From']   ,2,"UTF-8");

      /* remove 'Xref: ' from Xref  */
      if( strlen($element['Xref']) > 6 ) {
        $element['Xref'] = substr($element['Xref'],6);
      }
      $this->overview[] = $element;

    }

  }

}

?>
