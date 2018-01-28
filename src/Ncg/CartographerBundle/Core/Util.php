<?php

namespace Ncg\CartographerBundle\Core;

use Ncg\CartographerBundle;

use Symfony\Component\ClassLoader;

define( "MEMBER_INIT_VAL", 997111 ); //just random big value
define( "UNDEFINED_VAL", 997112 );//just random big value

define( "CTG_BASE_PATH", __DIR__."/..");
define( "SYMFONY_ROOT", __DIR__."/../../../.." );
define( "CTG_CONFIG_PATH", CTG_BASE_PATH."/Resources/config" );
define( "CTG_CACHE_PATH", CTG_BASE_PATH."/Resources/cache" );

define( "CTG_STD_CLASS", "Ncg\\CartographerBundle\\Core\\StdClass" );
define( "CTG_BASE_OBJ_CLASS_BASENAME", "Ncg\\CartographerBundle\\Table\\BaseObj" );

class Util extends StdClass {

  public static $Settings = array();
  public static $TableClasses = array();
  public static $IsInitialized = false;

  public static function initialize(){

    if( static::$IsInitialized ) return;
    static::$IsInitialized = true;

    static::$Settings = json_decode( file_get_contents(CTG_CONFIG_PATH."/settings.json"), true );
    if( !static::$Settings ) throw new \Exception( "Unable to read in settings.json" );

    Table::initialize();
    Db::initialize();

    if( is_file( CTG_CACHE_PATH."/table_classes.json" ) ){
      static::$TableClasses = json_decode( file_get_contents(CTG_CACHE_PATH."/table_classes.json"), true );
      foreach( static::$TableClasses as $class ){
        $class_name = $class['ClassName'];
        $class_name::initialize();
      }
    }

  }

  public static function setup(){

    static::$TableClasses = array();
    foreach( static::$Settings["TableClassDirectories"] as $dir ){
      $full_dir = SYMFONY_ROOT.'/src'.$dir;
      $namespace = preg_replace( "/\//", "\\\\", preg_replace("/^\//", "", $dir) );
      if( is_dir($full_dir) ){
        $files = scandir( $full_dir );
        foreach( $files as $f ){
          if( preg_match( "/^\w+\.php$/", $f ) ){
            $class = preg_replace( "/\.php$/", "", $f );
            static::$TableClasses[$class] = array(
              "ClassName" => $namespace."\\".$class,
              "Directory" => $full_dir,
            );
          }
        }
      }
    }

    static::$TableClasses['Obj'] = array(
      "ClassName" => "Ncg\\CartographerBundle\\Table\\Obj",
      "Directory" => CTG_BASE_PATH.'/Table',
    );
    d(static::$TableClasses);
    file_put_contents( CTG_CACHE_PATH."/table_classes.json", json_encode(static::$TableClasses) );


    $GLOBALS['IsSetup'] = true;
    Table::setup();
    foreach( static::$TableClasses as $class ){
      $class_name = $class['ClassName'];
      $class_name::setup();
    }
  }

  public static function index2dArray( $array, $index ){

    /* Assert that inputs are correct type */
    if( gettype($array) != "array" || gettype($index) != "string" )
      return false;

    $new_arr = array();
    foreach( $array as $row ){

      /* Assert that index is set */
      if( gettype($row) == 'array' && !array_key_exists($index,$row) ){
        return false;
      } else if( !is_a( $row, CTG_STD_CLASS ) ){
        return false;
      } else {
        $new_arr[$row[$index]] = $row;
      }
    }

    return $new_arr;

  }

  public static function arrayMerge( $p_arrayA, $p_arrayB ){

    if( $p_arrayA === UNDEFINED_VAL && $p_arrayB === UNDEFINED_VAL ){
      return null;
    }

    if( gettype($p_arrayA) != "array" ){
      return $p_arrayB === UNDEFINED_VAL? $p_arrayA: $p_arrayB;
    }

    else if( gettype($p_arrayB) != "array" ){
      return $p_arrayA === UNDEFINED_VAL? $p_arrayB: $p_arrayA;
    }

    else{ 
      $arr = array();
      if( !array_key_exists('__CLEAR_ARRAY', $p_arrayB) ){
        foreach( $p_arrayA AS $ak => $av ){
          $b = array_key_exists($ak, $p_arrayB)? $p_arrayB[$ak]: UNDEFINED_VAL;
          $arr[$ak] = static::arrayMerge( $p_arrayA[$ak], $b );
        }
      }
      else{
        unset( $p_arrayB['__CLEAR_ARRAY'] );
      }
      foreach( $p_arrayB AS $bk => $bv ){
        $a = array_key_exists($bk, $p_arrayA)? $p_arrayA[$bk]: UNDEFINED_VAL;
        if( !array_key_exists($bk, $arr) ) $arr[$bk] = static::arrayMerge( $a, $p_arrayB[$bk] );
      }


      return $arr;
    }

  }

  public static function extractColumn( $p_rows, $p_column_name ){

    $vals = array();
    foreach( $p_rows as $r ){
      if( is_a( $r, CTG_STD_CLASS ) && !array_key_exists($p_column_name,$r->Array) ){
        $vals[] = $r->getMember( $p_column_name );
      }
      else{
        $vals[] = $r[$p_column_name];
      }
    }
    return $vals;

  }

  public static function runMethod( $p_method, $p_ops=array() ){
    $ret = NULL;
    if( gettype($p_method) == "string" && ( strpos($p_method, "->") === 0 || strpos($p_method, "::") === 0 ) ){ 
      $func = strtr( $p_method, array( "::" => "", "->" => "" ) );
      if( isset($p_ops['Caller']) && ($caller = $p_ops["Caller"]) )
        $ret = $caller->$func();
      else if( isset($p_ops['CallerClass']) && ($class = $p_ops["CallerClass"]) ){
        $ret = $class::$func();
      }
      else{
        $class = get_called_class();
        $ret = $class::$func();
      }
    }
    return $ret;
  }

  public static function insertArrayValue( $p_array, $p_value_str, $p_val ){
    $parts = explode( ".", $p_value_str );
    $ret = $p_array;
    $target = &$ret;
    foreach( $parts as $pk => $p ){
      if( gettype($target) <> "array" ){
        throw new \Exception( "Attempt to insert and invalid value" );
      }
      if( count($parts)-1 == $pk ){
        $target[$p] = $p_val;
      }
      else if( preg_match( "/^\w+\[(\w+)=.*\]$/", $p ) ){
        $sel_index = preg_replace( "/^(\w+).*/", "$1", $p );
        $sel_val_index = preg_replace( "/^\w+\[(\w+)=.*\]$/", "$1", $p );
        $sel_val = preg_replace( "/^\w+\[\w+=(.*)\]$/", "$1", $p );
        if( !array_key_exists( $sel_index, $target ) ){
          $target[$sel_index] = array();
        }
        $target = &$target[$sel_index];
        $found = false;
        foreach( $target as &$t ){
          if( isset($t[$sel_val_index]) && $t[$sel_val_index]==$sel_val ){
            $target = $t;
            $found = true;
            break;
          }
        }
        if( !$found ){
          $target[] = array(
            $sel_val_index => $sel_val,
          );
          end($target);
          $target = &$target[key($target)];
        }
      }
      else{
        if( !array_key_exists($p,$target) ) $target[$p] = array();
        $target = &$target[$p];
      }
    }

    return $ret;
  }

  public static function isMethodOption($str){
    $ret = false;
    if( gettype($str) == "string" ){ 
      if( strpos($str, "->") === 0 || strpos($str, "::") === 0 ){
        $ret = true;
      }
    }
    return $ret;
  }

  public function getMethodOption($p_k){
    $ret = NULL;
    if( gettype($p_k) == "string" && ( strpos($p_k, "->") === 0 || strpos($p_k, "::") === 0 ) ){ 
      $func = strtr( $p_k, array( "::" => "", "->" => "" ) );
      $ret = static::$func();
    }
    return $ret;
  }

  public static function extractArrayValue( $p_array, $p_value_str, $p_ops=array() ){
    $parts = explode( ".", $p_value_str );
    $target = &$p_array;
    $found = true;
    foreach( $parts as $pk => $p ){

      $filter = false;
      if( isset($target[$p."Filter"]) )
        $filter = static::isMethodOption($target[$p."Filter"])?
          static::runMethod($target[$p."Filter"],$p_ops): 
          $target[$p."Filter"];

      if( isset($target[$p]) ){
        $target = static::isMethodOption($target[$p])? 
          static::runMethod($target[$p],$p_ops): 
          $target[$p];
      }
      else if( preg_match( "/^\w+\[(\w+)=.*\]$/", $p ) ){
        $sel_index = preg_replace( "/^(\w+).*/", "$1", $p );
        $sel_val_index = preg_replace( "/^\w+\[(\w+)=.*\]$/", "$1", $p );
        $sel_val = preg_replace( "/^\w+\[\w+=(.*)\]$/", "$1", $p );
        $val_found = false;
        if( isset($target[$sel_index]) ){
          $target = static::isMethodOption($target[$sel_index])? 
            static::runMethod($target[$sel_index],$p_ops): 
            $target[$sel_index];
        }
        else{
          $found = false;
          break;
        }
        if( gettype($target) <> "array" ){
          $found = false;
          break;
        }
        $val_found = false;
        foreach( $target as $tk ){
          if( isset($tk[$sel_val_index]) && $tk[$sel_val_index] == $sel_val ){
            $target = $tk;
            $val_found = true;
            break;
          }
        }
        if( !$val_found ){
          $found = false;
          break;
        }
      }
      else{
        $found = false;
        break;
      }

      if( gettype($target) == "array" ){
        if( $filter ){
          $target = $filter($target);
        }
      }

    }

    if( isset($p_ops["CheckExists"]) ) return $found;
    else if( !$found ) return NULL;
    else return $target;
  }
 
  public static function isUnderscore($p_str){
    return preg_match( "/^[a-z0-9]+(_[a-z0-9]+)*$/", $p_str );
  }

  public static function isCamelCase($p_str){
    return preg_match( "/^([A-Z][a-z][0-9]*)+$/", $p_str );
  }

  public static function underscore($p_str){
    $str = preg_replace( "/([A-Z])([A-Z]+)([A-Z][a-z])/e", "'$1'.strtolower('$2').'$3'", $p_str );
    $str = preg_replace( "/([a-z])([A-Z])/", "$1_$2", $str );
    return strtolower($str);
  }

  public static function camelCase($p_str){
    $str = preg_replace( "/(_|^)([a-z])([a-z]*)/e", "strtoupper('$2').'$3'", $p_str );
    return $str;
  }

  public static function indexCamelCaseArray( $p_arr, $p_recursive=true ){
    if( gettype($p_arr) != "array" || is_a( $p_arr, CTG_STD_CLASS ) ) throw new \Exception( "Invalid argument \$p_arr" );

    $arr = gettype($p_arr)=="array"? $p_arr: $p_arr->Array;

    foreach( $arr as $ak => $av ){

      $v = $p_recursive && (gettype($av) == "array" || is_a($p_arr,CTG_STD_CLASS))? 
        static::indexCamelCaseArray($av): 
        $av;
      unset( $arr[$ak] );
      $arr[static::camelCase($ak)] = $v;
    }

    return $arr;
  }

  public static function indexUnderscoreArray( $p_arr, $p_recursive=true ){

    if( gettype($p_arr) != "array" || is_a( $p_arr, CTG_STD_CLASS ) ) throw new \Exception( "Invalid argument \$p_arr" );

    $arr = gettype($p_arr)=="array"? $p_arr: $p_arr->Array;
    foreach( $arr as $ak => $av ){

      $v = $p_recursive && (gettype($av) == "array" || is_a($p_arr,CTG_STD_CLASS))? 
        static::indexUnderscoreArray($av): 
        $av;
      unset( $arr[$ak] );
      $arr[static::underscore($ak)] = $v;
    }

    return $arr;
  }

  public static function array2StdClass( $p_Arr ){
    $arr = static::indexCamelCaseArray($p_Arr, false);
    $obj = new StdClass;
    foreach( $arr as $k => $v ){
      if( gettype($v) == "array" ){
        $v = static::array2StdClass( $v );
      }
      $obj->Array[$k] = $v;
    }

    return $obj;
  }

  public static function stdClass2Array( $p_Obj ){
    $arr = static::indexUnderscoreArray( $p_Obj->Array, false );
    foreach( $arr as &$v ){
      if( is_a( $v, CTG_STD_CLASS ) ){
        $v = static::stdClass2Array( $v );
      }
    }

    return $arr;
  }

  public static function def2Array( $p_arr_def, $p_target ){
    $arr = array();
    foreach( $p_arr_def as $k => $d ){
      if( gettype( $d ) == "string" ){
        if( gettype($p_target) == "array" ){
          $arr[$d] = isset($p_target[$d])? $p_target[$d]: NULL;
        }
        else if( is_a( $p_target, CTG_STD_CLASS ) ){
          $arr[$d] = $p_target->getMember( Util::camelCase($d) );
        }
      }
      else if( gettype( $d ) == "array" ){
        if( gettype($p_target) == "array" ){
          if( is_numeric($k) ){
            foreach( $p_target as $t ){
              $arr[] = static::def2Array($d, $t);
            }
          }
          else
            $arr[$k] = static::def2Array( $d, isset($p_target[$k])?$p_target[$k]:array() );
        }
        else if( is_a( $p_target, CTG_STD_CLASS ) ){
          $new_target = $p_target->getMember( Util::camelCase($k) );
          $arr[$k] = static::def2Array( $d, $new_target );
        }
      }
    }
    return $arr;
  }

  public static function encode( $p_content, $p_key=NULL, $p_key2=NULL ){
    $key = isset( static::$Settings['Secrets']['EncodeKey1'] )? static::$Settings['Secrets']['EncodeKey1']: "";
    $key2 = isset( static::$Settings['Secrets']['EncodeKey2'] )? static::$Settings['Secrets']['EncodeKey2']: "";
    while(strlen($key)<16) $key .= "X";
    while(strlen($key2)<16) $key2 .= "X";
    if( strlen($p_key) ) $key = $p_key.substr($key, strlen($p_key) );
    if( strlen($p_key2) ) $key2 = $p_key2.substr($key2, strlen($p_key2) );

    return static::Base642AlphaNumeric( base64_encode(
      mcrypt_encrypt(
        MCRYPT_RIJNDAEL_256, $key.$key2, $p_content, 
        MCRYPT_MODE_CBC, 
        md5(md5($key)
      ))) ); 
  }

  public static function decode( $p_content, $p_key=NULL, $p_key2=NULL  ){

    $key = isset( static::$Settings['Secrets']['EncodeKey1'] )? static::$Settings['Secrets']['EncodeKey1']: "";
    $key2 = isset( static::$Settings['Secrets']['EncodeKey2'] )? static::$Settings['Secrets']['EncodeKey2']: "";
    while(strlen($key)<16) $key .= "X";
    while(strlen($key2)<16) $key2 .= "X";
    if( strlen($p_key) ) $key = $p_key.substr($key, strlen($p_key) );
    if( strlen($p_key2) ) $key2 = $p_key2.substr($key2, strlen($p_key2) );

    $str = static::alphaNumeric2Base64($p_content);;
    return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key.$key2, 
      base64_decode($str), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
  }

  public static function hex2AlphaNumeric($p_str){
    $b64 = base64_encode(pack('H*',$p_str));
    $an = static::base642AlphaNumeric( $b64);
    return $an;
  }

  public static function alphaNumeric2Hex($p_str){
    $b64 = static::alphaNumeric2Base64( $p_str );
    $bin = base64_decode( $b64 );
    $hex = bin2hex( ($bin) );
    return $hex;
  }

  public static function base642AlphaNumeric($p_str){
    return strtr( rtrim( $p_str, "=" ), array(
      "a" => "aa",
      "+" => "ab",
      "/" => "ac",
    ) );
  }

  public static function alphaNumeric2Base64($p_str){
    return strtr( $p_str, array(
      "ab" => "+",
      "ac" => "/",
      "aa" => "a",
    ) );
  }

  public static function randomString($p_length=10, $p_is_alphanumeric=false ){
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if( !$p_is_alphanumeric ) $characters .= "`~!@#$%^&*()-_=+[{]}\|'\";:,<.>/?";
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $p_length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  public static function label2Code( $p_label ){
    $code = trim($p_label);
    $code = preg_replace("/[^A-Za-z0-9 \t]/", "", $code );
    $code = preg_replace( "/[ \t]*\b(\w)/e", "strtoupper('$1')", $code );

    return $code;
  }
  
}

if(!function_exists("getSqlValueString")) {
function getSqlValueString($theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "") {
  if (PHP_VERSION < 6) {
    $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;
  }

  $theValue = mysql_escape_string($theValue);

  switch ($theType) {
    case "Text":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;    
    case "Long":
    case "Int":
      $theValue = ($theValue != "") ? intval($theValue) : "NULL";
      break;
    case "Double":
      $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
      break;
    case "Date":
      $theValue = ($theValue != "") ? "'" . date( 'Y-m-d H:i:s', strtotime($theValue) ) . "'" : "NULL";
      break;
    case "Defined":
      $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
      break;
  }
  return $theValue;
}
}
