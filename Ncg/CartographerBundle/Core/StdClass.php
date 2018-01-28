<?php

namespace Ncg\CartographerBundle\Core;

class StdClass implements \arrayaccess, \Iterator {

  public $Array = array();
  public $RegisteredOptions = array();
  public $OptionsCache = NULL;

  public static $DefaultStaticOptions = array();
  public static $StaticRegisteredOptions = array();
  public static $StaticOptionsCache = NULL;

  public static $Initialized = array();

  public static function initialize(){}
  public static function setup(){}

  public function __construct( $p_obj=NULL, $p_options=null ){

    if( $p_options ) 
      $this->registerOptions( $p_options );

    if( gettype($p_obj) == "array" ){
      foreach( $p_obj as $k => $v ){
        $this->Array[$k] = $v;
      }
    }
    else if( is_a( $p_obj, get_class($this) ) ){
      foreach( get_object_vars($p_obj) as $name => $member ){
        $this->$name = $p_obj->$name;
      }
      return;
    }
    else if( $p_obj !== NULL ){
      throw new \Exception( "Attempt to initialize StdClass with unrecognized argument" );
    }
  }

  public function getMember( $p_member=NULL ){
    if( !$p_member ) return $this->__call( "getMember", NULL );
    $func = "get$p_member";
    return $this->$func();
  }

  public static function get( $p_member ){
    $called_class = get_called_class();
    if( property_exists( $called_class, $p_member ) ){
      return $called_class;
    }
    else
      return static::staticOption( $p_member );
  }


  public function setMember( $p_member, $p_val=UNDEFINED_VAL, $p_val2=NULL ){
    if( $p_val === UNDEFINED_VAL ) $ret = $this->__call( "setMember", array($p_member) );
    else{
      $func = "set$p_member";
      $ret =$this->$func($p_val, $p_val2 );
    }
  }

  public function init( $p_member_name ){

    if( property_exists( $this, $p_member_name) && !$this->getOption( "$p_member_name.ForceInit" ) ) return;

    $method_name = "init$p_member_name";
    if( method_exists( $this, $method_name ) ){
      $this->$p_member_name = MEMBER_INIT_VAL;
      return $this->$method_name();
    }

    return $this;
  }

  public static function staticOptions($p_name = NULL,$p_class=NULL){

    $class = $p_class? $p_class: get_called_class();
    if( !isset(static::$Initialized[$class]) ){
      static::$Initialized[$class] = 1;
      $parent_class = get_parent_class($class);
      $additional_methods = array();
      if( $parent_class && $ops = $parent_class::staticOptions() ){
        static::registerStaticOptions( $ops, $class );
        if( isset($ops["ChildStaticOptionMethods"]) ){
          foreach( $ops["ChildStaticOptionMethods"] as $method ){
            if( isset($method["Parent"]) && isset($method["Method"]) && $method["Parent"] == $parent_class  ){
              $additional_methods[] = $method["Method"];
            }
          }
        }
      }
      if( static::$DefaultStaticOptions )
        static::registerStaticOptions( static::$DefaultStaticOptions, $class );
      $class_fname = preg_replace( "/\\\\/", "/", $class  );
      $full_fname = SYMFONY_ROOT."/src/$class_fname.json";
      if( file_exists($full_fname) ){ 
        $ops = json_decode( file_get_contents($full_fname), true );
        if( !$ops ){
          throw new \Exception( "Invalid JSON format: $full_fname" );
        }
        static::registerStaticOptions( $ops, $class );
        if( isset($ops["StaticOptionMethods"]) ){
          $additional_methods = array_merge( $additional_methods, $ops["StaticOptionMethods"] );
        }
      }
      foreach( $additional_methods as $method ){
        static::registerStaticOptions( static::$method(), $class );
      }
    }

    $orig_class = $class;
    if( !isset(static::$StaticOptionsCache[$class]) ){
      static::$StaticOptionsCache[$class] = array();
      $class_arr = array();
      while( $class ){
        array_unshift( $class_arr, $class );
        if( $class == CTG_STD_CLASS ) break;
        $class = get_parent_class($class);
      }
      static::$StaticOptionsCache[$orig_class] = array();
      foreach( $class_arr as $c ){
        if( isset(static::$StaticRegisteredOptions[$c]) ) 
          foreach( static::$StaticRegisteredOptions[$c] as $reg_ops ){
            static::$StaticOptionsCache[$orig_class] = Util::arrayMerge( static::$StaticOptionsCache[$orig_class], $reg_ops );
          }
      }
    }

    $ret = static::$StaticOptionsCache[$orig_class];
    if( $p_name ) {
      if( isset($ret['__MethodOps'][$p_name]) ){
        $func_name = $ret['__MethodOps'][$p_name];
        $ret = static::$func_name();
      }
      else
        $ret = Util::extractArrayValue( $ret, $p_name, array( "CallerClass" => get_called_class() ) );
    }
    return $ret;
  }

  public static function staticOption( $p_name, $p_class=NULL ){
    $class = $p_class? $p_class: get_called_class();
    return static::staticOptions($p_name,$class);
  }

  /**
   * get complete set of options.
   *
   * @return array the current options
   */
  public function getOptions($p_name = NULL, $p_class=NULL ){

    $class = $p_class? $p_class: get_class($this);

    if( $this->OptionsCache === NULL ){

      $this->OptionsCache = static::staticOptions(NULL,$class);

      foreach( $this->RegisteredOptions as $ops ){
        $this->OptionsCache = Util::arrayMerge( $this->OptionsCache, $ops );
      }

    }

    if( $p_name == null ){
      return $this->OptionsCache;
    }
    return Util::extractArrayValue( $this->OptionsCache, $p_name, array( "Caller" => $this ) );
  }

  public function getOption( $p_name ){
    return $this->getOptions($p_name);
  }

  public function hasOption( $p_name ){
    $ops = $this->getOptions();
    return Util::extractArrayValue( $ops, $p_name, array( "Caller" => $this, "CheckExists" => true ) ) != NULL;
  }

  public function registerOptions( $p_options ){

    if( gettype($p_options) == "array" ){
      $id = uniqid();
      $this->RegisteredOptions[$id] = $p_options;
    }
    else{
      throw new \Exception( "Invalid parameter for registerOptions (must be array)" );
    }

    $this->setOptionsCache( NULL );

    return $id;
  }

  public function registerOption( $p_option, $p_value ){

    if( !strlen(trim((string)$p_option)) ){
      throw new \Exception( "Invalid key for registerOption (must be non-empty string)" );
    }

    return $this->registerOptions( array( $p_option => $p_value ) );

  }
    
  public function unregisterOptions( $p_id ){

    if( $p_id && array_key_exists($p_id, $this->RegisteredOptions) ){
      unset($this->RegisteredOptions[$p_id]);
    }
    else throw new \Exception( "Attempting to unregister an undefined option key" );

    $this->setOptionsCache( NULL );

  }
    
  public function unregisterOption( $p_id ){
    return $this->unregisterOptions($p_id);
  }

  public static function registerStaticOptions( $p_options, $p_class=NULL ){

    $class = $p_class? $p_class: get_called_class();

    if( gettype($p_options) == "array" ){
      $id = uniqid();
      static::$StaticRegisteredOptions[$class][$id] = $p_options;
    }
    else{
      throw new \Exception( "Invalid parameter for registerOptions (must be array)" );
    }

    static::$StaticOptionsCache[$class] = NULL;

    return $id;
  }

  public static function registerStaticOption( $p_option, $p_value ){

    if( !strlen(trim((string)$p_option)) ){
      throw new \Exception( "Invalid key for registerStaticOption (must be non-empty string)" );
    }

    return static::registerStaticOptions( Util::insertArrayValue( array(), $p_option, $p_value ), get_called_class() );

  }
    
  public static function unregisterStaticOptions( $p_id, $p_class=NULL ){

    $class = $p_class? $p_class: get_called_class();

    if( $p_id && isset(static::$StaticRegisteredOptions[$class][$p_id]) ){
      unset(static::$StaticRegisteredOptions[$class][$p_id]);
    }
    else throw new \Exception( "Attempting to unregister an undefined option key" );

    static::$StaticOptionsCache[$class] = NULL;

  }
    
  public static function unregisterStaticOption( $p_id ){
    return static::unregisterStaticOptions($p_id,get_called_class());
  }

  public function offsetSet($offset, $value) {
    if (is_null($offset)) {
        $this->Array[] = $value;
    } else {
        $this->Array[$offset] = $value;
    }
  }

  public function offsetExists($offset) {
    return isset($this->Array[$offset]);
  }

  public function offsetUnset($offset) {
    unset($this->Array[$offset]);
  }

  public function offsetGet($offset) {
    return isset($this->Array[$offset]) ? $this->Array[$offset] : null;
  }

  public function __get( $p_str ){

    $ret = NULL;

    if( property_exists($this, $p_str) ){
      $reflector = new \ReflectionClass($this);
      $prop = $reflector->getProperty($p_str);
      if( ( $prop->isPrivate() || $prop->isProtected() ) ) {
        throw new \Exception( "Attempting to access private/protected member from outside the class" );
      }
    }
    $this->init( $p_str );
    $underscore = Util::underscore($p_str);
    if( property_exists($this, $p_str) ){
      $ret = $this->$p_str;
    }
    else if( array_key_exists( $p_str, $this->Array ) ){
      $ret =$this[$p_str];
    }
    else if( array_key_exists( $underscore, $this->Array ) ){
      $ret =$this[$underscore];
    }
    else if( preg_match( "/.+Set$/", $p_str ) ){
      $ret = array();
    }
    return $ret;
  }

  public function __set( $p_str, $p_val ){

    if( $p_val === MEMBER_INIT_VAL ){
      $this->$p_str = NULL;
      return;
    }

    if( property_exists($this, $p_str) ){
      $reflector = new \ReflectionClass($this);
      $prop = $reflector->getProperty($p_str);
      if( ( $prop->isPrivate() || $prop->isProtected() ) ) {
        throw new \Exception( "Attempting to access private/protected member from outside the class" );
      }
    }

    if( property_exists($this, $p_str) ){
      $this->$p_str = $p_val;
      return $this->$p_str;
    }

    /** get Field Variable **/
    $underscore = Util::underscore($p_str);
    if( array_key_exists( $p_str, $this->Array ) ){
      $this[$p_str] = $p_val;
      return $p_val;
    }
    else if( array_key_exists( $underscore, $this->Array ) ){
      $this[$underscore] = $p_val;
      return $p_val;
    }
    else{
      $this->$p_str = $p_val;
      return $p_val;
    }

  }

  public function __call( $method, $args ){

    if( preg_match( "/^(get)\w\w*(Count)$/", $method ) ){
      $member = preg_replace( "/^(get)(\w*)(Count)$/", "$2", $method );

      $get_mem = "get$member";
      $ret = $this->$get_mem();
      if( gettype($ret) == "array" ){
        return count($ret);
      }
    }

    /** setXxxx Methods **/
    if( preg_match( "/^(set)\w\w*$/", $method ) ){
      $field = preg_replace( "/^(set)(\w\w*)/", "$2", $method );

      if( count($args) > 1 ){
        $this->init($field);
        $arr = &$this->$field;
        $arr[$args[0]] = $args[1];
      }
      else{
        $this->$field = $args[0];
      }

      return $this;
    }

    /*** getXxx Methods ***/
    else if( preg_match( "/^(get)\w\w*$/", $method ) ){
      $field = preg_replace( "/^(get)(\w*)/", "$2", $method );
      if( isset($args[0]) && gettype($args[0]) != "array" ){
        return $this->$field[$args[0]];
      }
      else if( $alias = static::staticOptions( "MemberAliases[Alias=$field]") ){
        $get = "get{$alias["Target"]}";
        $ret = $this->$get();
        return $ret;
      }
      else if( $this->hasOption( $field ) ){
        return $this->getOption( $field );
      }
      else{
        $pattern = "/(\w+)__(\w+)/";
        $matches = array();
        if( preg_match( $pattern, $field, $matches ) ){
          if( count($matches) > 2 ){
            $get = "get{$matches[1]}";
            $obj = $this->$get();
            $get2 = "get{$matches[2]}";
            if( gettype($obj) == "object" ){
              $obj2 = $obj->$get2();
              return $obj2;
            }
            else if( gettype($obj) == "array" ){
              $obj2 = array();
              foreach( $obj as $o ){
                if( gettype($o) == "object" ){
                  $ret = $o->$get2();
                  if( gettype($ret) == "array" ){
                    foreach( $ret as $r ) $obj2[] = $r;
                  }
                  else if( gettype($ret) != "null" )
                    $obj2[] = $ret;
                }
              }
              return $obj2;
            }
          }
        }
        return $this->$field;
      }
    }

    /*** IsXxx HasXxx Methods ***/
    else if( preg_match( "/^(has|is|was)\w\w*$/", $method ) ){
      $field = preg_replace( "/^(has|is|was)(\w*)/", "$2", $method );
      $type = gettype( $this->$field );
      if( $type == "NULL" ){ 
        $field = "Is$field";
        $type = $this->$field;
      }
      $get_method = "get".$field;
      if( $type == "array" ){
        return count($this->$get_method()) > 0;
      }
      else if( $type == "object" ){
        $class_name = get_class($this->$get_method());
        if( is_subclass_of($this->$get_method(), "Ncg\\CartographerBundle\\Core\\Obj") ){
          return $this->$get_method()->getId() > 0;
        }
        else{
          return true;
        }
      }
      else{
        $get_a = $this->$get_method();
        $get_method_b = "get$method";
        $get_b = $this->$get_method_b();
        if( $get_a !== NULL ) return $get_a;
        else return $get_b;
      }
    }

    /*** OutputXxx Methods ***/
    else if( preg_match( "/^(output)\w\w*$/", $method ) ){
      $field = preg_replace( "/^(output)(\w*)/", "$2", $method );
      $get_method = "get".$field;
      if( isset($args[0]) ){
        echo (string)$this->$get_method($args[0]);
      }
      else{
        $str_func = $field."Str"; 
        echo $this->$str_func();
      }
    }

    /*** AddXxx Methods ***/
    else if( preg_match( "/^(add)\w\w*$/", $method ) ){
      $field = preg_replace( "/^(add)(\w*)/", "$2", $method ).'Set';
      if( isset($args[1]) ){
        $new_method = preg_replace( "/^add/", "set", $method );
        return $this->$new_method( $args[0], $args[1] );
      }
      else if( isset($args[0]) ){
        $this->init($field);
        array_push( $this->$field, $args[0] );
      }
      return $this;
    }

    /*** XxxStr Methods ***/
    else if( preg_match( "/^\w\w*(Str)$/", $method ) ){
      $field = preg_replace( "/^(\w*)(Str)$/", "$1", $method );
      $get_method = "get$field";
      if( isset($args[0]) ){
        return (string)$this->$get_method( $args[0] );
      }
      else{
        return (string)$this->$get_method();
      }
    }
  }

  public function __toString(){
    return $this->getStr();
  }

  public function rewind() {
    reset($this->Array);
  }

  public function current() {
    return $this->Array[key($this->Array)];
  }

  public function key() {
    return key($this->Array);
  }

  public function next() {
    return next($this->Array);
  }

  public function valid() {
    return array_key_exists(key($this->Array), $this->Array );
  }
}
