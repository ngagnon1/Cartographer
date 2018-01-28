<?php

namespace Ncg\CartographerBundle\Core;

use FluentPDO\FluentPDO;


class Db extends StdClass {

  /** Set of mysql connection settings. Used to set up connections */

  public static $ConnectionSettingSet = array();

  /** Holds the set of initialized database connections (initializes connections when requested) */
  public static $ConnectionSet = array(); 

  public static $DefaultConnection = null; 

  public static $PdoSet = array();

  public static $ExecutionCount=0;

  public static $LOG_QUERIES=false;

  public static $Queries=array();

  public static function initialize(){
    static::$ConnectionSettingSet = Util::$Settings['DbConnections'];
    $first = reset(static::$ConnectionSettingSet);
    static::$DefaultConnection = $first["DbName"];

  }

  public static function connection( $p_con_name ){

    $con_name = $p_con_name;

    if( !strlen($con_name) || !isset( self::$ConnectionSettingSet[$con_name] ) ){
      throw new \Exception( "Attempted to access connection has no configuration: '$con_name'" );
    }

    if( !isset( self::$ConnectionSet[$con_name] ) ){
      self::$ConnectionSet[$con_name] = mysql_pconnect( 
        self::$ConnectionSettingSet[$con_name]["Server"], 
        self::$ConnectionSettingSet[$con_name]["UName"], 
        self::$ConnectionSettingSet[$con_name]["Pw"] 
      );

      if( !self::$ConnectionSet[$con_name] ) throw new \Exception( "Unable to initialize connection '$p_con_name'" );

    }

    $res = mysql_select_db(self::$ConnectionSettingSet[$con_name]["DbName"], self::$ConnectionSet[$con_name] );

    return self::$ConnectionSet[$con_name];
  }

  /**
   * Returns the connection settigns for a specified connection
   *
   * @param string $p_con_name name of the connection to retreive.
   *
   * @return array Set of connection settings (server name, database name, user name and password)
   */
  public static function connectionInfo( $p_con_name ){
    $con_name = $p_con_name;
    return self::$ConnectionSettingSet[$con_name];
  }

  public static function pdo( $p_con_name ){

    if( !isset(static::$PdoSet[$p_con_name]) ){

      $con = static::connectionInfo( $p_con_name );

      $db_name = $con["DbName"];
      $user = $con["UName"];
      $pw = $con["Pw"];
      $pdo = new \pdo( "mysql:dbname=$db_name", $user, $pw );
      static::$PdoSet[$p_con_name] = new FluentPDO( $pdo );
    }

    return static::$PdoSet[$p_con_name];
  }

  /**
   * Runs a query on a specified mysql connection.
   *
   * @param string $p_con_name name of the target mysql connection (from $ConnectionSettingSet)
   *
   * @param string $p_query query string to execute
   *
   * @return object returns the mysql result (from mysql_query)
   *
   */
  public static function query( $p_con_name, $p_query ){

    $before = microtime(true);

    Db::$ExecutionCount++;

    $query = strval( $p_query );

    $con = self::connection( $p_con_name ); 
    if( !strlen($query) ) throw new \Exception( "Attempted to run query on with invalid query argument p_query" );

    $res = mysql_query($query, $con);

    if( !$res ) {

      $error = strval(mysql_error());

      throw new \Exception( "The following error occurred: $error" );
    }

    if( Db::$LOG_QUERIES ){
      global $start_time;
      $now = microtime(true);
      $index = $now-$start_time;
      $exec_time =$now-$before;
      $db_str_arr = array();
      foreach( array_reverse( debug_backtrace() ) as $t ){
        $db_str_arr[] = preg_replace( "/.*tfc.io\//", "", $t["File"] )." #".$t["Line"].": ".$t["Class"]."-".$t["Function"];
      }
      Db::$Queries[(string)$index] = array( 
        "ExecTime" => $exec_time,
        "Trace" => implode( "->\n", $db_str_arr ),
        "Query" => $query,
      );

    }

    return $res;
  }

  /**
   * Gets the next row from a mysql result (from Util::Query)
   *
   * @param object $p_query_res mysql result object (passed back from Util::Query
   *
   * @return array row from mysql query result
   */
  public static function getRow( $p_query_res ){
    return mysql_fetch_assoc($p_query_res);
  }

  /**
   * Gets a set of rows from a query result (from Util::Query)
   *
   * Can either get entire set of results, or by specifying "Start" and "Count"
   *
   * @param object $p_query_res mysql result object (passed back from Util::Query
   *
   * @param int $p_start Optional. Starting index of the set to return. Will return all if not specified.
   * 
   * @param int $p_count Optional. Specifies the # of rows to return.  WIll return all if not specified.  
   * If there are less rows then count, it will just return all.
   *
   * @return array a two dimensional array containing the set of rows 
   */
  public static function getRows( $p_query_res, $p_start=-1, $p_count=-1  ){

    $count = 0;
    $index = 0;
    $return = array();
    while( ($row = mysql_fetch_assoc($p_query_res)) && ($p_count==-1 || $count<$p_count)  ){
      if( $p_start == -1 || $index >= $p_start ){
        $return[] = $row;
        $count++;
      }
      $index++;
    }
    return $return;

  }

  /**
   * Returns the number of rows in a query result 
   *
   * @param object $p_query_res query result (from Util::Query)
   *
   * @return int number of rows in result
   */
  public static function getCount( $p_query_res ){
    return mysql_num_rows($p_query_res);
  }

  /**
   * Retreives the last insert Id for a DB connection
   *
   * @param string $p_con_name name of the connection to get last insert Id of
   *
   * @result int the id of the last insert
   */
  public static function getInsertId( $p_con_name ){
    $con = self::connection( $p_con_name ); 
    return mysql_insert_id($con);
  }

  public static function getNumRows( $p_result ){
    return mysql_num_rows($p_result);
  } 

  public static function convertMysqlType( $p_mysql_type ){

    $match  = preg_replace( "/\(.*/", "", $p_mysql_type );

    if( preg_match( "/int/", $match ) ){
      return "Int";

    } else if( preg_match( "/^decimal/", $match ) ){
      return "Double";
    } else if( preg_match( "/^float/", $match ) ){
      return "Double";
    } else if( preg_match( "/^double/", $match ) ){
      return "Double";
    } else if( preg_match( "/^double/", $match ) ){
      return "Double";

    } else if( preg_match( "/char/", $match ) ){
      return "Text";
    } else if( preg_match( "/binary/", $match ) ){
      return "Text";
    } else if( preg_match( "/enum/", $match ) ){
      return "Text";
    } else if( preg_match( "/text/", $match ) ){
      return "Text";
    } else if( preg_match( "/blob/", $match ) ){
      return "Text";
    } else if( preg_match( "/set/", $match ) ){
      return "Text";

    } else if( preg_match( "/date/", $match ) ){
      return "Date";
    } else if( preg_match( "/time/", $match ) ){
      return "Date";
    } else if( preg_match( "/year/", $match ) ){
      return "Date";

    } else {
      throw new Exception( "could not identify type" );
    }
  }

}

/**
 * This class handles performing standard Table Insert operations.
 *
 * The class takes the target table and an array of field names and values to execute a standard table insert operation.
 *
 * When building the insert query string, this class makes sure that only valid field names are used (it won't include any fields that aren't defined in table), and that all values are properly escaped when creating the query.  It will also handle encrypting any protected fields in the table.
 *
 * <b>Example:</b>
 * Here is a typical example of how this class should be used.  
 * In this example a record is inserted into the AccessUser table:
 *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
 *      $user_insert = new Insert( $user_tbl, array(
 *        "UserName" => "Testuser",
 *        "FullName" => "Test User",
 *        "UserPass" => "Testpassword",
 *      ) );
 *      $user_insert->execute();
 */
class Insert extends Query {

  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the insert operation 
   * @param array $p_insert_data An array containing the field_name/value pairs to be inserted. Any pairs with a field_name that does not belong to the table will just be ignored.  Array should have field_names as indices and values as the values. ex:
   *      array(
   *        "field_name1" => "val1",
   *        "field_name2" => "val2"
   *      )
   */
  public function __construct($p_table=NULL, $p_insert_data=array() ){
    $this->setTable( $p_table );
    $this->setArray( $p_insert_data );
  }

  /**
   * Overrides Query::GetConnection to retreive connection from the Table
   *
   * @return the DB connection for the table
   */
  public function getConnection(){
    return $this->getTable()->getConnection();
  }

  /**
   * Constructs an 'INSERT INTO' statement using the target table and the specified field_name/value pairs.
   *
   * Overrides the Query::GetQuery method.  This prepares the statement for use by method Query::Execute.  This <b>should not be used publicly</b> but is left
   * public for debugging purposes.
   *
   * This will apply the MySQL AES_ENCRYPTED function protected fields.
   *
   * @return string insert query string
   *
   */
  public function getQuery(){

    $inputs = array();
    $fields = $this->getTable()->getFieldDefinitions();

    $names = array();
    $values = array();
    /* Iterate through field_name/value pairs, add appropriate pairs to query */
    foreach( $this as $k => $v ){
      if( isset($fields[$k] ) //test if field_name is a field in the table
          && strpos($fields[$k]["Extra"],"AutoIncrement")  === false //make sure it's not an auto-increment field 
          && ( !( //make sure it's not a timestamp
            $fields[$k]["Extra"] == 'CURRENT_TIMESTAMP' 
            && $this->hasOption("SetTimeStamp") == false ) ) 
      ){
        /* Prepare field_name for query string */
        $names[] ="`$k`";

        /* Prepare value for query string */
        $value = getSqlValueString($v,Db::convertMysqlType($fields[$k]["Type"]));
        if( $fields[$k]["EncryptKey"] !== NULL ){
          $key = getSqlValueString($fields[$k]["EncryptKey"],"Text");
          $value = "AES_ENCRYPT( $value, $key )";
        }
        $values[] = $value;
      }
    }

    /* If no fields to insert, throw exception */
    if( !count($values) ){
      throw new \Exception( "Attempt to run \"Insert\" transaction without any fields" ); 
    }

    $q = "INSERT INTO {$this->Table->getTableName()} (".implode( ", ", $names ).") VALUES (".implode(", ", $values).")";

    return $q;

  }
}

/**
 * This class implements a single-row update DB transaction.
 *
 * The class takes a series of field_name/value pairs (similar to Insert) and constructs a standard 'UPDATE' query. he update will update the row that matches the primary key in the field_name/value pair set.
 *
 * In order for this transaction to be executed, two conditions must be met:
 * 1. The target table must have a Primary Key. 
 * 2. The field_name/value pairs must contain a value for the primary key field.
 *
 * If either of these conditions are not met, the update will not work.
 *
 * Also note, the update will ONLY effect fields specified in the field_name/value pair set.
 *
 * <b>Example:</b>
 * Here is a typical example of how this class should be used.  
 * In this example the record with AccessUserId=22 in the AccessUser table is updated:
 *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
 *      $user_update = new Update( $user_tbl, array(
 *        "AccessUserId" => 22,
 *        "UserName" => "Updatedtestuser",
 *        "FullName" => "Updated Test User",
 *        "UserPass" => "Updatedpassword",
 *      ) );
 *      $user_update->execute();
 */
class Update extends Query {
  
  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the update operation 
   * @param array $p_update_data An array containing the field_name/value pairs to be updated. This array <b>Must include a value for the Primary Key of the table</b>. Any pairs with a field_name that does not belong to the table will just be ignored.  Array should have field_names as indices and values as the values. ex:
   *      array(
   *        "field_name1" => "val1",
   *        "field_name2" => "val2"
   *      )
   * @param array $p_includes Optional.  An array of field_names to include.  If specified, will only update field names in this array.
   * @param array $p_excludes Optional.  An array of field_names to exclude.  If specified, will NOT update field names.
   */
  public function __construct($p_table=NULL, $p_update_data=array() ){
    $this->setTable( $p_table );
    $this->setArray( $p_update_data );
  }

  /**
   * Overrides Query::GetConnection to retreive connection from the Table
   *
   * @return the DB connection for the table
   */
  public function getConnection(){
    return $this->getTable()->getConnection();
  }

  /**
   * Constructs an 'UPDATE' statement using the target table and the specified field_name/value pairs.
   *
   * Overrides the Query::GetQuery method. Prepares the statement for use by method Query::Execute.  This <b>should not be used publicly</b> but is left
   * public for debugging purposes.
   *
   * This will apply the MySQL AES_ENCRYPTED function protected fields.
   *
   * @return string update query string
   *
   */
  public function getQuery(){

    $fields = $this->getTable__FieldDefinitions();
    $primary_field = NULL;
    $primary_value = NULL;
    $updates = array();

    /* Iterate through field_name/value pairs, add appropriate pairs to query */
    foreach( $this as $k => $v ){
      if( isset($fields[$k] ) //test if field_name is a field in the table
          && $fields[$k]->isAutoIncrement() == false
          && $fields[$k]->isWriteable()
      ){
        /* Prepare value for query string */
        $value = getSqlValueString($v,Db::convertMysqlType($fields[$k]["Type"]) );
        if( $fields[$k]["EncryptKey"] !== NULL ){
          $key = getSqlValueString($fields[$k]["EncryptKey"],"Text");
          $value = "AES_ENCRYPT( $value, $key )";

         /* Prepare assignment for query string */   }
        $updates[] = "`$k`=$value";
      }

      /* If matches the primary key, set as target */
      else if( $this->getTable__IdField__FieldName() == $k ){
        $primary_field = $k;
        $primary_value = getSqlValueString( $v,Db::convertMysqlType($fields[$k]["Type"]) );
      }
    }

    /* If no primary key was specified, or no fields to update, throw exception */
    if( !$primary_field || !$primary_value || !count($updates) ){
      throw new \Exception( "Invalid parameters for running an 'Update' transaction" ); 
    }

    /* Prepare query */
    $q = "UPDATE ".$this->Table->getTableName()." SET ".implode( ", ", $updates)." WHERE $primary_field=$primary_value";

    return $q;

  }
}

/**
 * This class implements an insert/update DB transaction. 
 *
 * The class takes a series of field_name/value pairs (similar to Insert) and constructs a query that will update a row matching the provided primary key value if it exists, and insert a new row if the primary key value does not exist. If no primary key value is provided, it will just create an insert query instead.
 *
 * This class should be used in situations where you do not know if a row with the primary key already exists. 
 *
 * <b>Example:</b>
 * Here is a typical example of how this class should be used.  
 * In this example the record with AccessUserId=22 in the AccessUser table is updated if it exists, and is inserted if not:
 *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
 *      $user_ui = new UpdateInsert( $user_tbl, array(
 *        "AccessUserId" => 22,
 *        "UserName" => "Updatedtestuser",
 *        "FullName" => "Updated Test User",
 *        "UserPass" => "Updatedpassword",
 *      ) );
 *      $user_ui->execute();
 */
class UpdateInsert extends Query {

  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the update-insert operation 
   * @param array $p_update_data An array containing the field_name/value pairs to be insert-updated. Any pairs with a field_name that does not belong to the table will just be ignored.  Array should have field_names as indices and values as the values. ex:
   *      array(
   *        "field_name1" => "val1",
   *        "field_name2" => "val2"
   *      )
   * @param array $p_includes Optional.  An array of field_names to include.  If specified, will only update field names in this array.
   * @param array $p_excludes Optional.  An array of field_names to exclude.  If specified, will NOT update field names.
   */
  public function __construct($p_table=NULL, $p_update_data=array(), $p_includes=array(), $p_excludes=array() ){
    $this->setTable( $p_table );
  }

  /**
   * Overrides Query::GetConnection to retreive connection from the Table
   *
   * @return the DB connection for the table
   */
  public function getConnection(){
    return $this->Table->getConnection();
  }

  /**
   * Constructs an update-insert statement using the target table and the specified field_name/value pairs.
   *
   * Overrides the Query::GetQuery method. Prepares the statement for use by method Query::Execute.  This <b>should not be used publicly</b> but is left
   * public for debugging purposes.
   *
   * This will apply the MySQL AES_ENCRYPTED function protected fields.
   *
   * @return string update query string
   *
   */
  public function getQuery(){

    $inputs = array();
    $fields = $this->Table->getFieldDefinitions();

    $updates = array();
    $field_names = array();
    $values = array();
    $id_field = $this->Table->getIdField();

    if( !$id_field ) throw new \Exception( "Cannot run 'UpdateInsert' transaction on table without id field" ); 

    /* Iterate through field_name/value pairs, add appropriate pairs to query */
    foreach( $this as $k => $v ){
      if( isset($fields[$k] ) ){
        $value = getSqlValueString($v,Db::convertMysqlType($fields[$k]["Type"]) );
        if( $fields[$k]["EncryptKey"] !== NULL ){
          $key = getSqlValueString($fields[$k]["EncryptKey"],"Text");
          $value = "AES_ENCRYPT( $value, $key )";
        }
        if( $id_field["Field"] != $k ) $updates[] = "`$k`=$value";
        $field_names[] = "`$k`";
        $values[] = $value;
      }

      if( $id_field["Field"] == $k ){
        $primary_field = $k;
        $primary_value = getSqlValueString( $v,Db::convertMysqlType($fields[$k]["Type"]) );
      }
    }

    /* If no fields to update, throw exception */
    if( !count($updates) ){
      throw new \Exception( "Invalid parameters for running an 'Update' transaction" ); 
    }

    /* If no primary field specified, just create a standard insert instead */
    if( !$primary_field || !$primary_value ){
      $insert_q = new Insert( $this->Table, $this->getArray() );
      return $insert_q->getQuery();
    } 

    /* Construct Query */
    $q = "INSERT INTO `".$this->Table->getTableName()."` (".implode(", ", $field_names).") VALUES (".implode(", ", $values).") ON DUPLICATE KEY UPDATE ".implode(", ",$updates);

    return $q;

  }
}


/**
 * This class implements a multi-row SELECT DB transaction.
 *
 */
class Select extends Query {

  public $Pdo = NULL;

  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the update-insert operation 
   * @param int $p_value The primary key Value for the row to select.
   * will select all fields in the table.  The array should be in the form:
   *      array( "fieldname1", "fieldname2", "fieldnname3" )
   */
  public function __construct($p_table=NULL, $p_ops = NULL ){
    $this->setTable( $p_table );
    $this->setPdo( Db::pdo( $p_table->getConnection() ) );

    $id_field = $this->Table->getIdField();
    $id_field_name = $id_field["Field"];
    $ops = array(
      "Where" => "TRUE",
      "OrderBy" => $id_field_name? "`$id_field_name`": NULL,
    );
    $this->registerOptions( $ops );
    if( $p_ops ) $this->registerOptions( $p_ops );

  }

  /**
   * Overrides Query::GetConnection to retreive connection from the Table
   *
   * @return the DB connection for the table
   */
  public function getConnection(){
    return $this->Table->getConnection();
  }

  /**
   * Constructs a select statement using the target table and the specified primary key value and optionally 
   * the specified fields to be selected.
   *
   * Overrides the Query::GetQuery method. Prepares the statement for use by method Query::Execute.  This <b>should not be used 
   * publicly</b> but is left public for debugging purposes.
   *
   * This will apply the MySQL AES_ENCRYPTED function protected fields.
   *
   * @return string update query string
   *
   */
  public function getQuery(){

    $q = $this->Pdo->from( $this->Table->getTableName() );
    $q->disableSmartJoin();
    $fields = $this->Table->getFieldDefinitions();
    $tbl_name = $this->Table->getTableName();

    /* Determine set of fields to select */
    $sel_fields = array();
    foreach( $fields as $k => $v ){ $sel_fields[] = $k; }

    /* Prepare select fields for query */
    $sel_arr = array();
    foreach( $sel_fields as $f ){
      if( isset($fields[$f]["EncryptKey"]) && strlen($fields[$f]["EncryptKey"]) ){
        $key = getSqlValueString($fields[$f]["EncryptKey"],"Text");
        $q = $q->select("AES_DECRYPT( `$tbl_name`.`$f`, $key ) AS $f");
      }
      else{
        $q = $q->select("`$tbl_name`.`".$f."`");
      }
    }

    /* Prepare Query */
    if( $this->hasOption("OrderBy") ) $q = $q->orderBy( "`$tbl_name`.".$this->getOption("OrderBy") );
    if( $this->hasOption("Where") ) $q = $q->where( $this->getOption("Where") );
    if( $this->hasOption("Limit") ) $q = $q->limit( $this->getOption("Limit") );
    if( $this->hasOption("Offset") ) $q = $q->offset( $this->getOption("Offset") );
    if( $this->hasOption("GroupBy") ) $q = $q->groupBy( $this->getOption("GroupBy") );

    if( $this->hasOption("Joins") && gettype($this->getOption("Joins")) == "array" ){
      foreach( $this->getOption("Joins") as $j ){
        if( strtoupper($j["Type"]) == "INNER" ){
          $q = $q->innerJoin( $j["Statement"] );
        }
        else{
          $q = $q->leftJoin( $j["Statement"] );
        }
      }
    }

    $q_str = preg_replace( "/\w*\.\*,/", "", $q->getQuery() );
    return $q_str;

  }

}

/**
 * This class implements a single-row SELECT DB transaction.
 *
 * The class takes a primary key value and constructs a standard 'SELECT' query that targets a row based on the primary key field.
 *
 * <b>Example:</b>
 * Here is a typical example of how this class should be used.  
 * In this example the record with AccessUserId=22 in the AccessUser table is selected:
 *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
 *      $user_select = new SelectRow( $user_tbl, 22 );
 *      $user_row = $user_select->getRow();
 */
class SelectRow extends Select {

  public static $Selects = array();

  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the update-insert operation 
   * @param int $p_value The primary key Value for the row to select.
   * will select all fields in the table.  The array should be in the form:
   *      array( "fieldname1", "fieldname2", "fieldnname3" )
   */
  public function __construct($p_table=NULL, $p_value=NULL ){

    $id_field = $p_table->getIdField();
    $id_field_name = $id_field["Field"];
    $options = array(
      "SelectField" => $id_field_name,
      "OrderBy" => "`$id_field_name`"
    );

    $fields = $p_table->getFieldDefinitions();

    $select_field = $fields[$options["SelectField"]];

    $is_compound = count(explode("::",$options["SelectField"]))>1;
    if( !$select_field && !$is_compound ) throw new \Exception( "Select field is undefined" ); 

    $field = $select_field["Field"];
    if( isset($fields[$field]["EncryptKey"]) && strlen($fields[$field]["EncryptKey"]) ){
      $key = getSqlValueString($fields[$field]["EncryptKey"],"Text");
      $field = "AES_DECRYPT( `$field`, $key )"; 
    } else if( $is_compound ) {
      $field_arr = explode("::",$options["SelectField"]);
      $field = "CONCAT( `".implode("`, '::', `", $field_arr)."` )";
    } else {
      $field = "`$field`";
    }
    $type = !$is_compound? Db::convertMysqlType($select_field["Type"]): "Text";
    $value = getSqlValueString( $p_value, $type );

    $options["Where"] = "$field=$value";

    parent::__construct( $p_table, $options );
  }

}

/**
 * This class implements a single-row DELETE DB transaction.
 *
 * The class takes a primary key value and constructs a 'DELETE' query that targets a row based on the primary key field.
 *
 * <b>Example:</b>
 * Here is a typical example of how this class should be used.  
 * In this example the record with AccessUserId=22 in the AccessUser table is deleted:
 *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
 *      $user_delete = new Delete( $user_tbl, 22 );
 *      $result = $user_delete->execute();
 */
class Delete extends Query {

  /**
   * Initializes member variables from parameters
   *
   * @param object $p_table The target table (Table Object) for the update-insert operation 
   * @param int $p_id The primary key Id for the row to delete.
   */
  public function __construct($p_table=NULL, $p_id=NULL ){
    $this->setTable( $p_table );
    $this->setId( $p_id );
  }

  /**
   * Overrides Query::GetConnection to retreive connection from the Table
   *
   * @return the DB connection for the table
   */
  public function getConnection(){
    return $this->Table->getConnection();
  }

  /**
   * Constructs a delete statement using the target table and the specified primary key value 
   *
   * Overrides the Query::GetQuery method. Prepares the statement for use by method Query::Execute.  This <b>should not be used 
   * publicly</b> but is left public for debugging purposes.
   *
   * This will apply the MySQL AES_ENCRYPTED function protected fields.
   *
   * @return string update query string
   *
   */
  public function getQuery(){

    $primary_field = false;
    $primary_value = false;
    $fields = $this->Table->getFieldDefinitions();

    $id_field = $this->Table->getIdField();
    if( !$id_field ) throw new \Exception( "Cannot run 'Delete' transaction on table without id field" ); 
    if( !$this->getId() ) throw new \Exception( "Cannot run 'Delete' transaction without id value" ); 

    /* Prepare key name/value for query */
    foreach( $fields as $k => $v ){
      if( $id_field["Field"] == $k ){
        $primary_field = "`$k`";
        $primary_value = getSqlValueString( $this->Id,Db::convertMysqlType($id_field["Type"]) );
      }
    }

    if( !$primary_field || !$primary_value )
      throw new \Exception( "Invalid parameters for running a 'DELETE' transaction" ); 

    $tbl_name = $this->Table->getTableName();

    /* Construct Query */
    $q = "DELETE FROM `$tbl_name` WHERE $primary_field=$primary_value";

    return $q;

  }
}

