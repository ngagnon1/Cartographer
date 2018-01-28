<?php

namespace Ncg\CartographerBundle\Core;

class TableClass extends StdClass {

  public $Target;

  public function getTarget(){
    if( $target = $this->getOption( "Target" ) ){
      return $target;
    }
    return parent::getTarget();
  }

  public function __construct( $p_obj=NULL ){
    $this->setTarget($p_obj);
  }

  public static function setup(){
    $class = get_called_class();
    $class = preg_replace( "/.*\\\\/", "", $class );
    $class_dir = Util::$TableClasses[$class]['Directory'];
    $class_base = Util::$TableClasses[$class]['ClassName'];
    foreach(Table::$TableNames as $db => $tables ){

      if( !is_dir($class_dir) ){
        mkdir( $class_dir, 0777 );
      }

      if( !is_dir("$class_dir/$class") ){
        mkdir( "$class_dir/$class", 0777 );
      }
      if( !is_dir("$class_dir/$class/$db") ){
        mkdir( "$class_dir/$class/$db", 0777 );
      }

      $class_name_str = get_called_class();

      foreach( $tables as $tbl_name ){
        if( !file_exists( "$class_dir/$class/$db/$tbl_name.php" ) ){
          $class_str = <<<END
<?php
namespace $class_base\\$db;
class $tbl_name extends \\$class_name_str {
}
END;
          file_put_contents( 
            "$class_dir/$class/$db/{$tbl_name}.php", 
            $class_str
          );
        }
      }
    }
  }

  public static function getTable(){

    $class_name = get_called_class();
    $parent = get_parent_class($class_name);
    $target_class = NULL;
    while( $parent <> "Ncg\\CartographerBundle\\Core\\TableClass" ){
      $target_class = $class_name;
      $class_name = $parent;
      $parent = get_parent_class($parent);
    }
    $db = preg_replace( "/.*\\\\(\w+)\\\\(\w+)$/", "$1", $target_class );
    $tbl = preg_replace( "/.*\\\\(\w+)\\\\(\w+)$/", "$2", $target_class );
    return Table::get($db,$tbl);

  }

  public function setTarget( $p_target ){
    if( $p_target !== NULL ){
      $class_name = static::getTableClassName("Obj");
      $this->Target = $class_name::get($p_target);
    }
    else
      $this->Target = NULL;
  }

  public function getTableClass($p_class_name){
    $class_name = static::getTableClassName($p_class_name);
    $ret = new $class_name( $this->getTarget() );
    if( $ops = $this->getOption( "TableClassOps" ) ){
      $ret->registerOptions( $ops );
    }
    if( $ops = $this->getOption( "TableClassOps[Class=$p_class_name].Ops" ) ){
      $ret->registerOptions( $ops );
    }
    if( $ops = $this->getOption( "TableClassOps[Class=All].Ops" ) ){
      $ret->registerOptions( $ops );
    }
    return $ret;
  }

  public static function getTableClassName($p_class_name){
    $tbl = static::getTable();
    $class_base = Util::$TableClasses[$p_class_name]['ClassName'];
    $class_name = "$class_base\\{$tbl->getConnection()}\\{$tbl->getTableName()}";
    return $class_name;
  }

}

