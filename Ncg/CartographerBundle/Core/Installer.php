<?php

namespace Ncg\CartographerBundle\Core;

class Installer extends StdClass{
  public static function install(){
  }

  public static function createTables(){
    $queries = json_decode( file_get_contents(CTG_CONFIG_PATH."/install_queries.json"), true );
    foreach( $queries['Queries'] as $q ){
      $q = new Query( $q );
      $q->execute();
    }
  }


}
