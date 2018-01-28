<?php

namespace Ncg\CartographerBundle;

use Ncg\CartographerBundle\Core;

class Obj extends Core\TableClass {

  public static $CONTINUE_ON_RETURN = 74747474;

  public static $Entities = array();
  public static $ClassRelationships = array();
  public static $ClassRelationshipsInitArr = array();

  public static $ObjectIdSet = array();
  public static $ObjectStringSet = array();
  public static $ObjectMembers = array();

  public static $InitializedOptions = array();

  public static function staticOptions($p_name=NULL,$p_class=NULL){
    $class = $p_class? $p_class: get_called_class();
    if( $class <> "Ncg\\CartographerBundle\\Table\\Obj" && !in_array( $class, static::$InitializedOptions ) ){

      $arr = array();

      $local_tbl = static::getTable();
      $local_tbl_name = $local_tbl->getTableName();
      $local_tbl_con = $local_tbl->getConnection();

      $local_id_field = $local_tbl->getIdField();
      $local_id_field_name = $local_id_field["Field"];

      $db_sql = Core\getSQLValueString( $local_tbl->getConnection(), "Text" );

      $query = new Core\Query( "
        SELECT * FROM TableForeignKey
        WHERE ( 
          ( ReferencedTableName = '$local_tbl_name' AND ReferencedDbName = $db_sql )
          OR ( TableName = '$local_tbl_name' AND DbName = $db_sql  ) 
        )
      " );

      $res = $query->getRows();

      if( gettype($res) == "array" ) foreach( $res as $r ){

        if( $local_tbl_name == $r["TableName"] && $local_tbl->getConnection() == $r["DbName"] ){
          $remote_tbl_name = $r["ReferencedTableName"];
          $remote_db = $r["ReferencedDbName"];
          $remote_field = $r["ReferencedFieldName"];
          $local_field = $r["FieldName"];
          $member_name = $local_field;
        }
        else{
          $remote_tbl_name = $r["TableName"];
          $remote_db = $r["DbName"];
          $remote_field = $r["FieldName"];
          $local_field = $r["ReferencedFieldName"];
          $member_name = $remote_field == $local_id_field_name? $remote_tbl_name: $remote_field;
        }

        $class_name = Core\Table::get($remote_db,$remote_tbl_name)->getClassName();

        if( $class_name && !isset($GLOBALS['IsSetup']) ){

          $remote_tbl = $class_name::getTable();
          $remote_id_field = $remote_tbl->getIdField();

          if( $member_name == $remote_id_field["Field"] && $local_field != $local_id_field_name ){
            $member_name = $local_field;
          }

          $is_array = $remote_field != $remote_id_field["Field"];

          $member_name = preg_replace( "/(Id)$/", "", $member_name );
          if( $is_array ){
            $arr[] = array(
              "Name" => $member_name,
              "Class" => $class_name,
              "LocalField" => $local_field,
              "RemoteField" => $remote_field,
              "IsArray" => false,
            );
            $member_name .= "Set";
          }

          $arr[] = array(
            "Name" => $member_name,
            "Class" => $class_name,
            "LocalField" => $local_field,
            "RemoteField" => $remote_field,
            "IsArray" => $is_array,
          );
        }

      }
      $arr = array( "Members" => $arr );

      $table = static::getTable();
      $fields = $table->getFieldDefinitions();
      $field_ops = array();
      foreach( $fields as $f ){
        $field_ops[] = $f;
      }
      $arr["Fields"] = $field_ops;

      static::$InitializedOptions[] = $class;
      if( $renamed = static::staticOptions( "RenamedMembers" ) ){
        foreach( $renamed as $r ){
          foreach( $arr["Members"] as &$m ){
            if( $m["Name"] == $r["OldName"] ){
              $m["Name"] = $r["NewName"];
            }
          }
        }
      }
      if( $additional = static::staticOptions( "AdditionalMembers" ) ){
        foreach( $additional as $a ){
          $arr["Members"][] = $a;
        }
      }
      static::registerStaticOptions( $arr );

    }

    return parent::staticOptions($p_name,$class);
  }

  public $MemberCounts = array();

  public $Id = NULL;

  public static function memberOptions( $p_member_name=NULL, $p_option=NULL ){
    $ops_arr = array( $p_member_name? "Members[Name=$p_member_name]": "Members" );
    if( $p_option ) $ops_arr[] = $p_option;
    $ret = static::staticOption( implode( ".", $ops_arr ) );
    if( !$ret ){
      $ops_arr = array( $p_member_name? "Members[Class=$p_member_name]": "Members" );
      if( $p_option ) $ops_arr[] = $p_option;
      $ret = static::staticOption( implode( ".", $ops_arr ) );
    }
    return $ret;
  }

  public static function memberOption( $p_member_name, $p_option ){
    return static::memberOptions($p_member_name,$p_option);
  }

  public function getMemberOptions( $p_member_name=NULL, $p_option=NULL ){
    $ops_arr = array( $p_member_name? "Members[Name=$p_member_name]": "Members" );
    if( $p_option ) $ops_arr[] = $p_option;
    $op_name = implode(".",$ops_arr);

    $ret = $this->getOption($op_name);

    return $ret;
  }

  public function getMemberOption( $p_member_name, $p_option_val ){
    return $this->getMemberOptions($p_member_name,$p_option_val);
  }

  public function hasMemberOptions( $p_member_name, $p_option_val=NULL ){
    $ops_arr = array("Members[Name=$p_member_name]");
    if( $p_option )
      $ops_arr[] = $p_option;
    $op_name = implode(".",$ops_arr);
    $ret = $this->hasOption($op_name);
    return $ret;
  }

  public function hasMemberOption( $p_member_name, $p_option_val ){
    return $this->hasMemberOptions($p_member_name,$p_option_val);
  }

  public static function fieldOptions( $p_field_name=NULL, $p_option=NULL ){
    $ops_arr = array( $p_field_name? "Fields[Field=$p_field_name]": "Fields" );
    if( $p_option ) $ops_arr[] = $p_option;
    $ret = static::staticOption( implode( ".", $ops_arr ) );
    return $ret;
  }

  public static function fieldOption( $p_field_name, $p_option ){
    return static::fieldOptions($p_field_name,$p_option);
  }

  public function getFieldOptions( $p_field_name=NULL, $p_option=NULL ){
    $ops_arr = array( $p_field_name? "Fields[Field=$p_field_name]": "Fields" );
    if( $p_option ) $ops_arr[] = $p_option;
    $op_name = implode(".",$ops_arr);

    $ret = $this->getOption($op_name);

    return $ret;
  }

  public function getFieldOption( $p_field_name, $p_option_val ){
    return $this->getFieldOptions($p_field_name,$p_option_val);
  }

  public function hasFieldOptions( $p_field_name, $p_option_val=NULL ){
    $ops_arr = array("fields[field=$p_field_name]");
    if( $p_option_val )
      $ops_arr[] = $p_option_val;
    $op_name = implode(".",$ops_arr);
    $ret = $this->hasOption($op_name);
    return $ret;
  }

  public function hasFieldOption( $p_field_name, $p_option_val=array() ){
    return $this->hasFieldOptions($p_field_name,$p_option_val);
  }

  public static function initialize(){
  }

  public static function setup(){
    set_time_limit( 300 );
    foreach(Core\Table::$TableNames as $db => $tables ){

      if( !is_dir(CTG_BASE_PATH."/Table/BaseObj") ){
        mkdir( CTG_BASE_PATH."/Table/BaseObj", 0777 );
      }
      if( !is_dir(CTG_BASE_PATH."/Table/Obj") ){
        mkdir( CTG_BASE_PATH."/Table/Obj", 0777 );
      }
      if( !is_dir(CTG_BASE_PATH."/Table/BaseObj/$db") ){
        mkdir( CTG_BASE_PATH."/Table/BaseObj/$db", 0777 );
      }
      if( !is_dir(CTG_BASE_PATH."/Table/Obj/$db") ){
        mkdir( CTG_BASE_PATH."/Table/Obj/$db", 0777 );
      }

      foreach( $tables as $tbl_name ){
        if( !file_exists( CTG_BASE_PATH."/Table/BaseObj/$db/$tbl_name.php" ) ){
          $class_str = <<<END
<?php
namespace Ncg\\CartographerBundle\\Table\\BaseObj\\$db;
use Ncg\\CartographerBundle\\Table;
use Ncg\\CartographerBundle\\Core;
class $tbl_name extends Table\\Obj {
}
END;
          file_put_contents( 
            CTG_BASE_PATH."/Table/BaseObj/$db/$tbl_name.php", 
            $class_str
          );
        }
      }
    }

    static::initialize();

    foreach(Core\Table::$TableNames as $db => $tables ){
      foreach( $tables as $tbl_name ){
        $class_name = CTG_BASE_OBJ_CLASS_BASENAME."\\$db\\$tbl_name";
        $obj = new $class_name;
        $member_str_arr = array();
        $members = $obj->getOption("Members");
        $fields = $obj->getOption("Fields");
        foreach( $members as $member ){
          $mem_name = $member["Name"];
          $field = $member["LocalField"];
          $id_field = $obj->getTable()->getIdField()->getField();
          $member_str_arr[] = "public function get$mem_name(){return \$this->$mem_name;}";
          if( $id_field == $field ){
            $member_str_arr[] = "public function set$mem_name(\$p_val){\$this->$mem_name = \$p_val; return \$this;}";
          }
          else{
            $member_str_arr[] = "public function set$mem_name(\$p_val){\$this->$mem_name = \$p_val; \$this->$field = \$p_val? \$p_val->getId(): NULL; return \$this;}";
          }
        }
        foreach( $fields as $field ){
          $mem_name = $field["Field"];
          $member_str_arr[] = "public function get$mem_name(){return \$this->$mem_name;}";
          $member_str_arr[] = "public function set$mem_name(\$p_val){\$this->$mem_name = \$p_val; return \$this;}";
        }
        $member_str = implode( "\n\n  ", $member_str_arr );
        $class_str = <<<END
<?php
namespace Ncg\\CartographerBundle\\Table\\BaseObj\\$db;
use Ncg\\CartographerBundle\\Core;
use Ncg\\CartographerBundle\\Table;
class $tbl_name extends Table\\Obj {
  $member_str
}
END;
        file_put_contents( 
          CTG_BASE_PATH."/Table/BaseObj/$db/$tbl_name.php", 
          $class_str
        );

        if( !file_exists( CTG_BASE_PATH."/Table/Obj/$db/$tbl_name.php" ) ){
          $class_str = <<<END
<?php
namespace Ncg\\CartographerBundle\\Table\\Obj\\$db;
use Ncg\\CartographerBundle\\Table\\BaseObj;
class $tbl_name extends BaseObj\\$db\\$tbl_name {
}
END;
          file_put_contents( 
            CTG_BASE_PATH."/Table/Obj/$db/$tbl_name.php", 
            $class_str
          );
        }
      }
    }

  }

  public function getId(){
    return $this->Id;
  }

  public function __construct( $p_obj=NULL ){

    $class_name = get_class($this);

    $table = static::getTable();

    $fields = $table->getFieldDefinitions();
    foreach( $fields as $fk => $fv ){
      $this->$fk = NULL;
    }
    if( is_numeric($p_obj) && $p_obj ){

      if( isset(Obj::$ObjectIdSet[$class_name][$p_obj]) ){
        static::__construct(Obj::$ObjectIdSet[$class_name][$p_obj]);
        return;
      }
      $select = new Core\SelectRow( $table, $p_obj );
      $row = $select->getRow();
      static::__construct( $row? $row:NULL );
      return;
    } 
    else if( gettype($p_obj) == "string" && strlen($p_obj) && static::getStringConstructField() ){

      if( isset(Obj::$ObjectStringSet[$class_name][$p_obj]) ){
        static::__construct(Obj::$ObjectStringSet[$class_name][$p_obj]);
        return;
      }

      $field = static::getStringConstructField();
      $obj_set = static::getSet( array( 
        "FieldConditions" => array(
          $field => $p_obj,
        ),
        "Limit" => 1,
      ) );
      $obj = reset($obj_set);
      if( $obj )
        static::__construct( $obj->getId() );
      else
        static::__construct(NULL);
      return;
    }
    else if( gettype($p_obj) == "object" ){
      if( 
        $class_name == get_class($p_obj) 
     || is_subclass_of(get_class($p_obj),$class_name) 
     || is_subclass_of($class_name, get_class($p_obj))
      ){
        foreach( get_object_vars($p_obj) as $name => $member ){
          $this->$name = $member; 
        }
        return;
      }
      else{
        throw new \Exception( "Attempt to initialize Obj with invalid class" );
      }
    }
    else if( gettype($p_obj) == "array" ){
      foreach( $p_obj as $k => $v ){
        if( isset($fields[$k]) ){
          $this->$k = $p_obj[$k];
        }
      } 

      $id_field = $table->getIdField();
      $field = $p_obj[$id_field["Field"]];
      $this->Id = $field;
    }
    else if( $p_obj === NULL ){

    }
    else {
      d($p_obj);
      exit;
      throw new \Exception( "Attempt to initialize Object with unrecognized argument" );
    }

    if( $this->getId() && !isset(Obj::$ObjectIdSet[$class_name][$this->getId()]) ){
      Obj::$ObjectIdSet[$class_name][$this->getId()] = $this;
    }

    $string_field = static::getStringConstructField();
    $str_field_arr = array();
    if( $string_field ){
      foreach( explode( "::", $string_field ) as $str ){
        $v = $this[$str];
        if( $v ) $str_field_arr[] = $v;
      }
      if( count( $str_field_arr ) )
        Obj::$ObjectStringSet[$class_name][implode("::",$str_field_arr)] = $this;
    }
  }

  public function init( $p_member_name, $p_options=NULL ){

    /* Retreive if member for this object has already been initialized */
    $called_class_name = get_called_class();
    $this_id = $this->getId();
    if( !isset( static::$ObjectMembers[$called_class_name] ) ) Obj::$ObjectMembers[$called_class_name] = array();
    if( !isset( static::$ObjectMembers[$called_class_name][$p_member_name] ) ) Obj::$ObjectMembers[$called_class_name][$p_member_name] = array();
    $obj_arr = &static::$ObjectMembers[$called_class_name][$p_member_name];
    if( $this_id && array_key_exists( $this_id, $obj_arr ) ){
      $this->$p_member_name = $obj_arr[$this_id];
      return;
    }

    $ret = parent::init($p_member_name);

    if( isset($this->$p_member_name) && $ret !== static::$CONTINUE_ON_RETURN ) return;

    /** Check if this is a defined member of this object **/
    $member_defs = $this->getMemberOptions();
    $m = $this->getMemberOptions($p_member_name);
    if( $m ){

      $class_name = $this->getMemberOptions( $p_member_name, "Class" );
      $member_name = $this->getMemberOptions( $p_member_name, "Name" );
      $local_field = $this->getMemberOptions( $p_member_name, "LocalField" );
      $remote_field = $this->getMemberOptions( $p_member_name, "RemoteField" );
      $is_array = $this->getMemberOptions( $p_member_name, "IsArray" );

      $table = $class_name::getTable();
      $member_ops = array( "FieldConditions" => array(
        $remote_field => $this->getMember( $local_field ),
      ) );


      if( $gs_ops = $this->getMemberOption( $p_member_name, "GetSetOps" ) ){
        $member_ops = Core\Util::arrayMerge( $member_ops, $gs_ops );
      }

      $member_set = $class_name::getSet( $member_ops );

      if( $is_array ){
        $this->$p_member_name = $member_set;
      }
      else{
        $this->$p_member_name = reset($member_set);
      }

    }

    return $this;
  }

  public function getFieldArray(){
    $fields = $this->getTable__FieldDefinitions();
    $arr = array();
    foreach($fields as $fk => $f ){
      $arr[$fk] = $this->$fk;
    }
    return $arr;
  }

  public static function orderBy(){
    $fields = static::getTable()->getFieldDefinitions();

    $default_fields = static::staticOption("DefaultOrderByFields");

    foreach( $default_fields as $f ){
      if( isset( $fields[$f["Field"]] ) ){
        return "`{$f["Field"]}` {$f["Direction"]}";
      }
    }

    $id_field = static::getTable()->getIdField();
    return $id_field["Field"];
  }

  public static function getStringConstructField(){

    if( $str = static::staticOption("StringConstructField") ) return $str;

    $default_fields = static::staticOption("DefaultStringConstructorFields");

    foreach( $default_fields as $f ){
      $field = static::fieldOptions($f["Field"]);
      if( isset($field) ){
        return $f["Field"];
      }
    }

    return NULL;

  }

  public static function getSetJoins( $arr, $field, $join_type, $class, $parent_class=NULL, $parent_member_name=NULL ){
    $joins = array();
    $found_member_name = false;
    foreach( $class::memberOptions() as $r ){
      if( $r["Name"] == $field ){
        $found_member_name = true;
        break;
      }
    }
    foreach( $class::memberOptions() as $r ){
      $remote_class = $r["Class"];
      $remote_tbl_name = $remote_class::getTable()->getTableName();
      if( $r["Name"] == $field || ( !$found_member_name && $remote_tbl_name == $field ) ){
        $local_tbl_name = $class::getTable()->getTableName();
        $local_field_defs = $class::getTable()->getFieldDefinitions();
        $local_field_name = $r["LocalField"];
        $remote_field_name = $r["RemoteField"];
        $local_alias = $parent_class? $parent_class."_".$parent_member_name: $local_tbl_name;
        $remote_alias = preg_replace("/Ncg\\\\CartographerBundle\\\\Table\\\\Obj\\\\(\w*)\\\\/", "$1_", $class)."_".$r["Name"];
        $remote_field_defs = $remote_class::getTable()->getFieldDefinitions();
        $remote_join_val = "$remote_alias.$remote_field_name";
        if( $remote_field_defs[$r["RemoteField"]]["EncryptKey"] ){
          $remote_join_val = "AES_DECRYPT( $remote_join_val, ".Core\getSQLValueString($remote_field_defs[$r["RemoteField"]]["EncryptKey"],"Text")." )";
        }
        $local_join_val = "$local_alias.$local_field_name";
        if( $local_field_defs[$r["RemoteField"]]["EncryptKey"] ){
          $local_join_val = "AES_DECRYPT( $local_join_val, ".Core\getSQLValueString($local_field_defs[$r["RemoteField"]]["EncryptKey"],"Text")." )";
        }
        $joins[$field] = array(
          "Type" => $join_type,
          "Statement"  => "$remote_tbl_name AS $remote_alias ON $local_join_val = $remote_join_val",
         );

        foreach( $arr as $arr_k => $arr_v ){
          if( gettype($arr_v) == "Array" ){
            $joins = array_merge( $joins, static::getSetJoins( $arr_v, $arr_k, $join_type, $remote_class, $class, $r["Name"]) );
          }
        }
      }

    }


    return $joins;
  }

  public static function getSetConditions( $arr, $field, $class, $parent_class=NULL, $member_name=NULL ){
    $conditions = array();
    if( isset($arr['__field'] ) ){

      $alias = preg_replace("/Ncg\\\\CartographerBundle\\\\Table\\\\Obj\\\\(\w*)\\\\/", "$1_", $parent_class)."_$member_name";
      $field = $alias.".".$arr['__field'];
      $operator = $arr['__operator']? $arr['__operator']: "=";
      $tbl = $class::getTable();
      if( gettype($arr['__value'])=="Array" ){
        $values = array();
        foreach( $arr['__value'] as $v ){
          $value = $tbl->getSqlString( $arr['__field'], $v );
          $key = Core\Table::$FieldKeys[$tbl->getConnection()][$tbl->getTableName()][$arr['__field']];
          if( $key ){
            $key_sql = Core\getSQLValueString($key,"Text");
            $value = "AES_ENCRYPT( $value, $key_sql )";
          }
          if( $arr['__condition'] ){
            $str = preg_replace( "/<<field>>/", $field, $arr['__condition'] );
            $str = preg_replace( "/<<value>>/", $value, $str );
            $values[] = $str;
          }
          else{
            if( $value == "NULL" && $operator == "=" ){
              $operator = "IS";
            }
            $values[] = "$field $operator $value";
          }
        }
        $cond_type = $arr['__condition_type'];
        return "( ".implode( " $cond_type ", $values )." )";
      }
      else{
        $value = $tbl->getSqlString( $arr['__field'], $arr['__value'] );
        $key = NULL;
        if( isset(Core\Table::$FieldKeys[$tbl->getConnection()][$tbl->getTableName()][$arr['__field']]) ) 
          $key = Core\Table::$FieldKeys[$tbl->getConnection()][$tbl->getTableName()][$arr['__field']];
        if( $key ){
          $key_sql = Core\getSQLValueString($key,"Text");
          $value = "AES_ENCRYPT( $value, $key_sql )";
        }
        if( isset($arr['__condition']) && $arr['__condition'] ){
          $str = preg_replace( "/<<field>>/", $field, $arr['__condition'] );
          $str = preg_replace( "/<<value>>/", $value, $str );
          return array( $str );
        }
        else {
          if( $value == "NULL" && $operator == "=" ){
            $operator = "IS";
          }
          return array( "$field $operator $value" );
        }
      }

    }

    $found_member_name = false;
    foreach( $class::memberOptions() as $r ){
      if( $r["Name"] == $field ){
        $found_member_name = true;
        break;
      }
    }
    foreach( $class::memberOptions() as $r ){
      $remote_class = $r["Class"];
      $remote_tbl_name = $remote_class::getTable()->getTableName();
      if( $r["Name"] == $field || ( !$found_member_name && $remote_tbl_name == $field ) ){
        $local_tbl_name = $class::getTable()->getTableName();
        $local_field_name = $r["LocalField"];
        $remote_field_name = $r["RemoteField"];
        $local_alias = $parent_class? $parent_class."_".$local_tbl_name: $local_tbl_name;
        $remote_alias = $class."_".$r["Name"];

        foreach( $arr as $arr_k => $arr_v ){
          if( gettype($arr_v) == "array" ){
            $conditions = array_merge( $conditions, static::getSetConditions( $arr_v, $arr_k, $remote_class, $class, $r["Name"]) );
          }
        }
      }
    }
    return $conditions;
  }

  public static function getSet($ops=array()){
    $opk = count($ops)? static::registerStaticOptions($ops): NULL;

    $tbl = static::getTable();
    $tbl_name = $tbl->getTableName();
    $id_field = $tbl->getIdField();
    $id_field_name = $id_field["Field"]; 
    if( !isset( $ops["OrderBy"]) ) 
      $ops["OrderBy"] = static::staticOption("OrderBy");

    if( !isset($ops["FieldConditions"]) ) 
      $ops["FieldConditions"] = static::staticOption("FieldConditions");

    if( isset($ops["FieldConditions"]) && gettype($ops["FieldConditions"]) == "string" )
      $ops["FieldConditions"] = array( $ops["FieldConditions"] );

    if( isset($ops["FieldConditions"]) && gettype($ops["FieldConditions"]) == "array" ){
      $conditions = array();
      $conds = array();
      $joins = array();
      $join_type = isset($ops["FieldConditionType"]) && $ops["FieldConditionType"] == 'OR'? "LEFT": "INNER";
      foreach( $ops["FieldConditions"] as $fck => $fcv ){
        if( is_numeric($fck) && gettype($fcv) == "string" ){
          $condition = $fcv;
        }
        else if( !is_numeric($fck) ){
          $field = $fck;
          $value = $fcv;
          $operator = '=';
          $cond_type = "OR";
          $condition = false;
        }
        else{
          $field = isset($fcv["Field"])?$fcv["Field"]:NULL;
          $value = isset($fcv["Value"])?$fcv["Value"]:NULL;
          $operator = isset($fcv["Operator"])? $fcv["Operator"]: "=" ;
          $cond_type = isset($fcv["ConditionType"])? $fcv["ConditionType"]: "OR" ;
          $condition = isset($fcv["Condition"])? $fcv["Condition"]: false;
        }
        $fck_arr = explode( ".", $field );
        if( count($fck_arr)>1 ){
          $arr = &$conditions;
          foreach( $fck_arr as $fck_k => $fck_v ){
            if( $fck_k == count($fck_arr)-1 ){
              $arr[$fck_v] = array(
                "__field" => $fck_v,
                "__value" => $value,
                "__operator" => $operator,
                "__condition_type" => $cond_type,
                "__condition" => $condition,
              );
            }
            else{
              if( !isset($arr[$fck_v]) ){
                $arr[$fck_v] = array();
              }
              $arr = &$arr[$fck_v];
            } 
          }
        }
        else{
          if( gettype($value)=="array" ){
            $values = array();
            foreach( $value as $v ){
              $val = static::getTable()->getSqlString( $field, $v );
              $key = Core\Table::$FieldKeys[static::getTable()->getConnection()][static::getTable()->getTableName()][$field];
              if( $key ){
                $key_sql = Core\getSQLValueString($key,"text");
                $val = "AES_ENCRYPT( $val, $key_sql )";
              }
              if( $condition ){
                $str = preg_replace( "/<<field>>/", "`$tbl_name`.`$field`", $condition );
                $str = preg_replace( "/<<value>>/", $val, $str );
                $values[] = $str;
              }
              else{
                if( $val == "NULL" && $operator == "=" ){
                  $operator = "IS";
                }
                $values[] = "`$tbl_name`.`$field` $operator ".$val;
              }
            }
            $cond_type = $cond_type;
            $conds[] = "( ".implode( " $cond_type ", $values )." )";
          }
          else{
            if( $field ){
              $val = static::getTable()->getSqlString( $field, $value );

              if( isset(Core\Table::$FieldKeys[static::getTable()->getConnection()][static::getTable()->getTableName()][$field]) ){
                $key = Core\Table::$FieldKeys[static::getTable()->getConnection()][static::getTable()->getTableName()][$field];
                $key_sql = Core\getSQLValueString($key,"text");
                $val = "AES_ENCRYPT( $val, $key_sql )";
              }
            }
            if( $condition ){
              $str = preg_replace( "/<<field>>/", "`$tbl_name`.`$field`", $condition );
              $str = preg_replace( "/<<value>>/", $val, $str );
              $conds[] = $str;
            }
            else{
              if( $val == "NULL" && $operator == "=" ){
                $operator = "IS";
              }
              $conds[] = "`$tbl_name`.`$field` $operator $val";
            }
          }
        }
      }
      foreach( $conditions as $ck => $cv ){
        $joins = array_merge( $joins, static::getSetJoins( $cv, $ck, $join_type, get_called_class() ) );
      }

      $ops["Joins"] = $joins;

      foreach( $conditions as $ck => $cv ){
        $conds = array_merge( $conds, static::getSetConditions( $cv, $ck, get_called_class() ) );
      }

      $cond_type = isset($ops["FieldConditionType"])? $ops["FieldConditionType"]: "AND";
      $ops["Where"] = implode( " $cond_type ", $conds );

      if( !isset($ops["GroupBy"]) ) $ops["GroupBy"] = "`$tbl_name`.`$id_field_name`";
    }
    $q = new Core\Select( $tbl, $ops );

    $set = array();

    if( isset($ops["ReturnQuery"]) ){
      return $q;
    }
    if( isset($ops["SelectCount"]) || isset($ops["ReturnCount"])){
      return $q->getCount();
    }
    foreach( $q->getRows() as $r ){
      if( !isset($set[$r[$id_field_name]]) )
        $set[$r[$id_field_name]] = static::get( $r );
      else
        $set[] = static::get( $r );
    }
    return $set;

  }

  public static function getSetCount( $p_ops=array() ){
    return static::getSet( Core\Util::arrayMerge( $p_ops, array( "SelectCount" => true ) ) );
  }

  public static function getSetQuery( $p_ops=array() ){
    return static::getSet( Core\Util::arrayMerge( $p_ops, array( "ReturnQuery" => true ) ) );
  }

  public static function get( $p_val, $ops=array() ){
    $class_name = get_called_class();
    $id_field = static::getTable()->getIdField();
    $ret = NULL;
    if( is_numeric($p_val) && $p_val ){
      if( isset(Obj::$ObjectIdSet[$class_name][$p_val]) ){
        $ret = Obj::$ObjectIdSet[$class_name][$p_val];
      }
    }
    else if( gettype($p_val) == "string" && strlen($p_val) && $string_field = static::getStringConstructField() ){
      if( isset(Obj::$ObjectStringSet[$class_name][$p_val]) ){
        $ret = Obj::$ObjectStringSet[$class_name][$p_val];
      }
    }
    else if( gettype($p_val)=="array" && $p_val[$id_field["Field"]] ){
      if( isset(Obj::$ObjectIdSet[$class_name][$p_val[$id_field["Field"]]]) ){
        $ret = Obj::$ObjectIdSet[$class_name][$p_val[$id_field["Field"]]];
      }
    }
    else if( gettype($p_val)=="object" &&  $class_name == get_class($p_val) ){
      $ret = $p_val;
    }
    if( !$ret )
      $ret = new $class_name( $p_val );

    if( $ret->getOption("ObjectInitFunction") ){
      $func = $ret->getOption("ObjectInitFunction");
      $ret->$func($p_val, $ops );
    }

    return $ret;
  }

  public function updateQuery(){
    if( $this->getId() == NULL ) throw new \Exception( "Attempt to Update an Object without an Id " );

    $fields = array();
    foreach( $this->getTable__FieldDefinitions() as $fk => $f ){
      $set = "set$fk";
      $get = "get$fk";
      $this->$set( $this->getPreparedValue( $fk, $this->$get() ) );
      if( $f->isWriteable() || $f["Key"] == "PRI" ){
        $fields[$fk] = $this->$get();
      }
    }

    return new Core\Update( $this->getTable(), $fields );
  }

  public function update(){
    $q = $this->updateQuery();
    $q->execute();
    return $this;
  }

  public function insertQuery(){

    $fields = array();
    foreach( $this->getTable()->getFieldDefinitions() as $fk => $f ){
      $set = "set$fk";
      $get = "get$fk";
      $this->$set( $this->getPreparedValue( $fk, $this->$get() ) );
      if( $f->isInsertable() && ( $f["Default"] === NULL || $this->$get() !== NULL ) ){
        $fields[$fk] = $this->$get();
      }
    }

    $ret = new Core\Insert( $this->getTable(), $fields );
    return $ret;
  }

  public function insert(){
    $query = $this->insertQuery();
    $query->execute();
    $insert_field = $this->getTable()->getIdField();
    $this->Id = $query->getInsertId();
    $field = $insert_field["Field"];
    $this->$field = $this->getId();
    return $this;
  }

  public function deleteQuery(){
    if( $this->getId() == NULL ) throw new \Exception( "Attempt to Delete an Object without an Id " );
    return new Core\Delete( $this->getTable(), $this->getId() );
  }

  public function delete(){

    $query = $this->deleteQuery();
    $query->execute();
    return $this;
  }

  public function getMemberCount( $p_member ){
    if( !$this->MemberCounts[$p_member] ){
      $rel = $this->getClassRelationship( $p_member );
      if( $rel && $rel["IsArray"]  ){
        $cnt_method = "get{$p_member}Count";
        if( $rel["LocalField"] && $rel["RemoteField"] ){
          $remote_class= $rel["Class"];
          $remote_tbl = $remote_class::getTable();
          $tbl = $this->getTable();

          $remote_tbl_name = $remote_tbl->getTableName();
          $remote_field_name = $rel["RemoteField"];
          $member_name = $rel["LocalField"];
          $my_val = $tbl->getSqlString( $rel["LocalField"], $this->$member_name );
          $q = new Core\Query( $remote_tbl->getConnection(), "
            SELECT COUNT(*) AS cnt FROM `$remote_tbl_name` WHERE `$remote_tbl_name`.`$remote_field_name` = $my_val
          " );

          $res = $q->getRow();
          $this->MemberCounts[$p_member] = (int)$res['cnt'];
        }
        else if( method_exists( $this, $cnt_method ) ){
          return $this->$cnt_method();
        }
        else{
          $get_method = "get{$p_member}";
          return count($this->$get_method());
        }
      }
      else{
        return count( $this->getMember( $p_member ) );
      }
    }
    return $this->MemberCounts[$p_member];
  }

  public static function defaultValue( $p_input ){
    if(  $p_input == "DateTime" 
      || $p_input == "DateTimeRecorded" 
      || $p_input == "DateTimeSubmitted" 
      || $p_input == "DateTimeAdded" 
    )

      return date( 'Y-m-d H:i:s' );


    if( $p_input == "IpAddr" || $p_input == "IpAddress" )
      return $_SERVER['REMOTE_ADDR'];

    return NULL;
  }

  public function getCodeDefaultValue(){
    if( $this->getName() ){
      return Core\Util::label2Code( $this->getName() );
    }
    else if( $this->getName() ){
      return Core\Util::label2Code( $this->getLabel() );
    }
    return "BLAH";
  }

  public function getDefaultValue($p_field ){

    if( $this->hasFieldOption($p_field,"DefaultValue") ){
      return $this->getFieldOption($p_field,"DefaultValue");
    }
    else if( $this->hasOption("DefaultFieldValues.$p_field") ){
      return $this->getOption("DefaultFieldValues.$p_field");
    }

    return static::defaultValue($p_field);
  }

  public function getPreparedValue($p_field, $p_val ){

    if( $this->hasFieldOption($p_field,"PreparedValue") ){
      return $this->getFieldOption($p_field,"PreparedValue");
    }
    
    if( $p_val === NULL ){
      return $this->getDefaultValue( $p_field );
    }
    return $p_val;
  }

  public function __call( $method, $args ){

    if( preg_match( "/^has\w\w*Set$/", $method ) ){
      $field = preg_replace( "/^has(\w*)/", "$1", $method );
      return $this->getMemberCount( $field ) > 0;
    }

    /** setXxxx Methods **/
    if( preg_match( "/^set\w\w*$/", $method ) ){
      $field = preg_replace( "/^set(\w*)/", "$1", $method );

      foreach( static::memberOptions() as $r ){
        if( $r["Name"] == $field ){
          $set_method = 'set'.$r["LocalField"];
          if( is_a($args[0],"Ncg\\CartographerBundle\\Table\\Obj") && $args[0]->getId() ){
            $this->$set_method( $args[0]->getId() );
            $this->$field = $args[0];
            return $this;
          }
          else if( $this[$field] ){
            $this->$set_method(NULL);
            return $this;
          }
        }
      }

    }
    if( preg_match( "/^get\w\w*Label$/", $method ) ){
      $field = preg_replace( "/^get(\w*)Label$/", "$1", $method );

      foreach( static::memberOptions() as $r ){
        if( $r["Name"] == $field ){
          $has_method = "has".$field;
          $get_method = "get".$field;
          if( $this->$has_method() ){
            return $this->$get_method()->getLabel();
          }
        }
      }
    }
    if( preg_match( "/^get\w\w*Count$/", $method ) ){
      $member = preg_replace( "/^get(\w*)Count$/", "$1", $method );

      foreach( static::memberOptions() as $r ){
        if( $r["Name"] == $member ){
          $has_method = "Has".$member;
          if( $this->$has_method() ){
            return $this->getMemberCount($member);
          }
        }
      }

    }
    return parent::__call($method,$args);
  }

  public function __invoke(){
    return $this->getId() > 0;
  }

  public function getTableClass($p_class_name){
    $this->registerOption( "Target", $this );
    return parent::getTableClass($p_class_name);
  }

}

