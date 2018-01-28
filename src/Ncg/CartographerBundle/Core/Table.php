<?php

namespace Ncg\CartographerBundle\Core;

use Ncg\CartographerBundle\Obj;

/**
 * Class that handles operations related to database tables.  
 *
 * Includes methods for retreiving meta-info about a DB table and performing operations on the table. This class 
 * has had functions added 'as-needed'.  Not all possible information and operations available, but more may be
 * added in the future.
 *
 */
class Table extends StdClass {

  public static $FieldKeys;

  /** Holds an array of encrypted fields and their corresponding keys. Indexes=field name, Values=key. */
  public $EncryptedFields = NULL;

  public static $TableColumnLabels = array();

  public static $TableInfo = array();

  public static $Tables = array();

  public static $TableNames = array();

  public $TblExists = UNDEFINED_VAL;

  public static function initialize(){
    if( !file_exists( CTG_CACHE_PATH."/tables.json" ) ) static::setup();
    static::$TableNames = json_decode( file_get_contents(CTG_CACHE_PATH."/tables.json"), true );
    if( gettype(static::$TableNames) <> "array" ) throw new \Exception( "Invalid contents in tables.json file" );

    Table::$FieldKeys = Util::$Settings["FieldKeys"];
  }

  public static function setup(){
    static::updateForeignKeyTable();
    static::rebuildTableColumn();

    $tables = array();
    foreach( Db::$ConnectionSettingSet as $set ){
      $q = new Query( $set["DbName"], "SHOW TABLES" );
      foreach($q->getRows() as $row ){
        $tables[$set["DbName"]][$row["Tables_in_{$set["DbName"]}"]] = $row["Tables_in_{$set["DbName"]}"];
      }
    }

    $contents = json_encode($tables);
    file_put_contents( CTG_CACHE_PATH."/tables.json", $contents );
  }

  public static function updateForeignKeyTable(){
    $cons = Db::$ConnectionSettingSet;

    $db_names = array();
    foreach( $cons as $c ){
      $db_names[] = "'".$c["DbName"]."'";
    }
    $cond = "TABLE_SCHEMA = ".implode( $db_names, " OR TABLE_SCHEMA = " );

    $query = new Query( "
      SELECT 
        COUNT(*) as cnt
      FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
      WHERE
        ( $cond )
        AND REFERENCED_TABLE_NAME IS NOT NULL
    " );
    $row = $query->getRow();
    $key_cnt = $row['cnt'];

    $query = new Query( "SELECT COUNT(*) as cnt FROM TableForeignKey" );
    $row = $query->getRow();
    if( $row['cnt'] != $key_cnt ){

      $query = new Query( "DELETE FROM TableForeignKey" );
      $query->execute();
      $query = new Query( "ALTER TABLE TableForeignKey AUTO_INCREMENT=1" );
      $query->execute();

      $query = new Query( "
        SELECT 
          TABLE_NAME,
          TABLE_SCHEMA,
          COLUMN_NAME,
          CONSTRAINT_NAME,
          REFERENCED_TABLE_NAME,
          REFERENCED_TABLE_SCHEMA,
          REFERENCED_COLUMN_NAME 
        FROM 
          INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE
          ( $cond )
          AND REFERENCED_TABLE_NAME IS NOT NULL
      " );


      foreach( $query->getRows() as $r ){
        $DbName_sql = getSqlValueString($r['TABLE_SCHEMA'],"Text");
        $TableName_sql = getSqlValueString($r['TABLE_NAME'],"Text");
        $FieldName_sql = getSqlValueString($r['COLUMN_NAME'],"Text");
        $ReferencedDbName_sql = getSqlValueString($r['REFERENCED_TABLE_SCHEMA'],"Text");
        $ReferencedTableName_sql = getSqlValueString($r['REFERENCED_TABLE_NAME'],"Text");
        $ReferencedFieldName_sql = getSqlValueString($r['REFERENCED_COLUMN_NAME'],"Text");
        $q = new Query( "
          INSERT INTO TableForeignKey ( 
            DbName, TableName, FieldName, ReferencedDbName, ReferencedTableName, ReferencedFieldName
          )
          VALUES (
            $DbName_sql, $TableName_sql, $FieldName_sql, $ReferencedDbName_sql, $ReferencedTableName_sql, $ReferencedFieldName_sql
          )
        " );
        $q->execute();
      }
    }
  }

  public static function rebuildTableColumn(){
    $query = new Query( "DELETE FROM TableColumn" );
    $query->execute();
    foreach( Db::$ConnectionSettingSet as $conn ){
      $tbl_query = new Query( $conn["DbName"], "SHOW TABLES" );
      foreach( $tbl_query->getRows() as $table ){ 
        $tbl_name = $table["Tables_in_{$conn["DbName"]}"];
        $field_query = new Query( $conn["DbName"], "SHOW COLUMNS IN `$tbl_name`" );
        $field_res = $field_query->getRows();
        $ret = Util::index2dArray( $field_res, "Field" );
        $db_sql = getSqlValueString($conn["DbName"],"Text");
        $table_sql = getSqlValueString($tbl_name,"Text");
        foreach( $field_res AS $k => $r ){
          $f_sql = getSqlValueString($r['Field'],"Text");
          $t_sql = getSqlValueString($r['Type'],"Text");
          $n_sql = getSqlValueString($r['Null'],"Text");
          $k_sql = getSqlValueString($r['Key'],"Text");
          $d_sql = getSqlValueString($r['Default'],"Text");
          $e_sql = getSqlValueString($r['Extra'],"Text");
          $o_sql = getSqlValueString($k,"Int");
          $insert_query = new Query( "
            INSERT INTO TableColumn (
              `DbName`,`TableName`,`Field`,`Type`,`Null`,`Key`,`Default`,`Extra`,`DisplayOrder`
            ) VALUES (
              $db_sql,$table_sql,$f_sql,$t_sql,$n_sql,$k_sql,$d_sql,$e_sql,$o_sql
            )
          " ); 
          $insert_query->execute();
        } 
      }
    }
  }

  /**
   * Constructor - takes a connection name and a query string and optionally an array defining the encrypted fields in the table.
   *
   * <b>Example:</b>
   * Creates a Table object for the AccessUser table
   *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" );
   *
   * @param string $p_Connection the name of the database connection for the table  
   *
   * @param string $p_Table the name of the table
   *
   * @param array $p_Query Optional.  Query to construct temporary table with.
   */
  public function __construct( $p_Connection, $p_Table=NULL, $p_Options=array() ){

    if( !strlen($p_Connection) ) throw new \Exception( "Cannot create table without parameter p_Connection" );

    if( !strlen($p_Table) ) return self::__construct( Db::$DefaultConnection, $p_Connection );

    $this->setConnection( $p_Connection );
    $this->setTableName( strval($p_Table)? strval($p_Table): NULL );

    if( gettype($p_Options) == "string" && strlen($p_Options) ){
      $q = Query( 
        $p_Connection, 
        "CREATE TEMPORARY TABLE IF NOT EXISTS $p_Table AS $p_Options" 
      );
      $q->execute();
    }
    else if( gettype($p_Options) == "array" && count($p_Options) ){
      $op_id = $this->registerOptions( $p_Options );
    }

    // Else, if there are Keys defined for this table, use those
    if( isset(self::$FieldKeys[$this->getConnection()][$this->getTableName()]) ){
      $this->setEncryptedFields( self::$FieldKeys[$this->getConnection()][$this->getTableName()] );
    }
    // Else, no encrypted fields
    else{
      $this->setEncryptedFields( array() );
    }

  }

  public static function get( $p_Connection, $p_Table=NULL, $p_Options=array() ){
    $connection = $p_Table? $p_Connection: Db::$DefaultConnection;
    $table = $p_Table? $p_Table: $p_Connection;
    $options = $p_Table? $p_Options: $p_Table;
    if( !$options ) $options = array();

    if( !strlen($table) ) throw new \Exception( "Must invoke with a table name" );

    if( !array_key_exists( $connection, static::$Tables ) ) static::$Tables[$connection] = array();

    if( !array_key_exists( $table, static::$Tables[$connection] ) ) static::$Tables[$connection][$table] = new Table( $connection, $table, $options );

    return static::$Tables[$connection][$table];

  }

  public static function name2Table( $p_name ){

    $class_name = preg_replace( "/.*\\\\/", "", $p_name );
    if( !preg_match( "/^\w+$/", $class_name ) ) throw new \Exception( "Invalid class name" );

    $res_con = Db::$DefaultConnection;
    $res_tbl_name = $class_name;
    foreach( Db::$ConnectionSettingSet as $conn ){
      if( array_key_exists( "Prefix", $conn ) ){
        $prefix = $conn["Prefix"];
        if( preg_match( "/^$prefix/", $class_name ) ){
          $res_con = $conn["DbName"];
          $res_tbl_name = preg_replace( "/^$prefix/", "", $class_name );
        }
      }
    }
    return Table::get( $res_con, $res_tbl_name );
  }

  /**
   * Returns a multi-dimensional array containing the field definitions of the table.
   *
   * Retreives meta-info about the fields in the table by running a 'SHOW COLUMNS' query.
   *
   * <b>Example:</b>
   * gets field definitions for the table Access User
   *      $user_tbl = Table::get( "Tfcnetx", "AccessUser", array( "UserPass" => "C0rner5tone" ) );
   *      $user_defs = $user_tbl->getFieldDefinitions();
   *
   * @return array[] each field has an array with the following indexes:
   *      array(
   *        "Field" => {Field Name}, 
   *        "Type" => {MySQL Data Type}, 
   *        "Null" => {if field can be null}, 
   *        "Key" => {if field is a table key}, 
   *        "Encrypted" => {encryption key if applicable}, 
   *        "Extra" => {misc. info}, 
   *      )
   */
  public function initFieldDefinitions(){

    $tbl_name_sql = getSqlValueString($this->getTableName(),"Text");
    $db_name_sql = getSqlValueString($this->getConnection(),"Text");
    $q = new Query( "SELECT * FROM TableColumn WHERE DbName=$db_name_sql AND TableName=$tbl_name_sql ORDER BY DisplayOrder" );
    $this->FieldDefinitions = array();
    foreach( $q->getRows() as $row ){
      $this->FieldDefinitions[$row["Field"]] = new FieldDefinition( $row );
    }

    $encrypt_fields = $this->getEncryptedFields();
    foreach( $this->getFieldDefinitions() as $k => $d ){
      if( array_key_exists( $k, $encrypt_fields ) ){
        $this->FieldDefinitions[$k]["EncryptKey"] = $encrypt_fields[$k];
      }
      else
        $this->FieldDefinitions[$k]["EncryptKey"] = NULL;
    }
  }

  public function hasField($f){

    $fields = $this->getFieldDefinitions();

    return isset($fields[$f]);
  }

  /**
   * Retreives a connections config information array
   *
   * @return array returns an array with this structure
   *      array(
   *        "Server" => {server's name},
   *        "DbName" => {databases' name},
   *        "UName" => {mysql user name},
   *        "Pw" => {password},
   *      )
   *        
   */
  public function getConnectionInfo(){
    return Db::connectionInfo( $this->getConnection() );
  }

  /**
   * Checks if table actually exists.
   *
   * Uses mysql 'SHOW TABLES' query to check if the table exists in DB.
   *
   * @return bool Returns true if table exists, false if not.
   */
  public function exists(){
    if( $this->getTblExists() === UNDEFINED_VAL ){
      if( !array_key_exists($this->getConnection(), static::$TableInfo) ){
        $q = new Query( "SHOW TABLES IN ".$this->getConnection() );
        static::$TableInfo[$this->getConnection()] = Util::extractColumn( $q->getRows(), "Tables_in_".$this->getConnection() );
      }
      $this->setTblExists( in_array( $this->getTableName(), static::$TableInfo[$this->getConnection()] ) );
    }
    return $this->TblExists;
  }

  /**
   * get the Primary Id field definition for the table.
   *
   * Returns the field definition array for the primary key in the table.
   *
   * <b>Example:</b>
   * get the Id field for table AccessUser
   *      $table = Table::get( "Tfcnetx", "AccessUser" );
   *      $table_id = $table->getIdField();
   *
   * @return array An array with the following structure corresponding for the primary Id field of table:
   *      array(
   *        "Field" => {Field Name}, 
   *        "Type" => {MySQL Data Type}, 
   *        "Null" => {if field can be null}, 
   *        "Key" => {if field is a table key}, 
   *        "Encrypted" => {encryption key if applicable}, 
   *        "Extra" => {misc. info}, 
   *      )
   */
  public function getIdField(){
    $fields = $this->getFieldDefinitions();
    $primary_keys = array();
    foreach( $fields as $f ){
      if( $f["Key"] == "PRI" ){
        $primary_keys[] = $f;
      }
    }

    if( count($primary_keys) == 1 ){
      return $primary_keys[0];
    }
    else if( count($primary_keys) > 1 ){
      $auto_incr = false;
      foreach( $primary_keys as $pk ){
        if( strpos($pk["Extra"],"AutoIncrement") !== false ){
          return $pk;
        }
      }
      return $primary_keys[0];
    }
    else{
      return false;
    }
  }

  /**
   * Prepares a value to be inserted into query string for a particular field.
   *
   * This function uses the defined MySQL data type of the field to determine how to prepare the value 
   * to be inserted into a query string.  This is done by looking at the at 'Type' in the Field Definition.
   *
   * <b>Example:</b>
   * Preparing data to be inserted into query for AccessTable
   *      $user_tbl = Table::get( "Tfcnetx", "AccessUser" );
   *      $sql_val=  $user_tbl->getSqlString( "FullName", "%Mike%" );
   *      $user_q = new Query( "Tfcnetx", "SELECT * FROM AccessUser WHERE FullName LIKE $sql_val" );
   *
   * @param string $p_field_name name of the field that the value is being prepared for
   * @param mixed $p_val The value to be prepared
   * @return string the prepared value string.
   */
  public function getSqlString( $p_field_name, $p_val ){
    $fields = $this->getFieldDefinitions();
    return getSqlValueString( $p_val, Db::convertMysqlType($fields[$p_field_name]["Type"]) );
  }

  public function getPreparedSqlString( $p_field_name, $p_val ){
    if( $this->EncryptedFields[$p_field_name] ){
      $key_sql = getSqlValueString($this->EncryptedFields[$p_field_name],"Text");
      return "AES_ENCRYPT( {$this->getSqlString($p_field_name,$p_val)}, $key_sql )";
    }
    else{
      return $this->getSqlString($p_field_name, $p_val );
    }
  }

  public function getSqlSelectString( $p_field_name ){
    if( $this->EncryptedFields[$p_field_name] ){
      $key_sql = getSqlValueString($this->EncryptedFields[$p_field_name],"Text");
      return "AES_DECRYPT( `{$this->getTableName()}`.`$p_field_name`, $key_sql ) AS `$p_field_name`";
    }
    else{
      return "`{$this->getTableName()}`.`$p_field_name`";
    }
  }

  public function getSqlSelectAllString(){
    $str_arr = array();
    foreach( $this->getFieldDefinitions() as $f ){
      $str_arr[] = $this->getSqlSelectString($f["Field"]);
    }

    return implode( ", ",$str_arr );
  }

  /**
   * Deletes a table from the database. <b>BE CAREFUL!</b>
   *
   * @return object mysql_query result
   */
  public function delete(){
    if( strlen($this->getTableName() ) ){
      $del_q = new Query( $this->getConnection(), "DROP TABLE IF EXISTS `".$this->getTableName()."`" );
      return $del_q->execute();
    }
  }

  public function resetFieldDefinitions(){
    $db_name_sql = getSqlValueString($this->getConnection(),"Text");
    $table_name_sql = getSqlValueString($this->getTableName(),"Text");
    $q = new Query( "DELETE FROM TableColumn WHERE DbName=$db_name_sql AND TableName=$table_name_sql" );
    $q->execute();
    $this->setFieldDefinitions( NULL );
  }

  public function initClassName(){
    $conn = Db::connectionInfo($this->getConnection());
    $prefix = array_key_exists("Prefix",$conn)? $conn["Prefix"]: "";
    $db = $conn["DbName"];
    $this->ClassName = "Ncg\\CartographerBundle\\Table\\Obj\\$db\\{$this->getTableName()}";
  }

  public function initName(){
    $conn = Db::connectionInfo($this->getConnection());
    $prefix = array_key_exists("Prefix",$conn)? $conn["Prefix"]: "";
    $db = $conn["DbName"];
    $this->Name= "$prefix{$this->getTableName()}";
  }

}

Table::initialize();
