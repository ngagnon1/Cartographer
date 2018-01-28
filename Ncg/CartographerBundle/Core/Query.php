<?php

namespace Ncg\CartographerBundle\Core;

use Ncg\CartographerBundle\Objects;

/**
 * An OO interface for querying the DB.  
 *
 * This class wraps around the functions in Db to provide an OO interface for database queries.  
 */
class Query extends StdClass {

  /**
   * Constructor for Query class
   *
   * Initializes member variables using the connection name and the query string.
   *
   * <b>Example:</b>
   * Simple query to run against the tfcnetx databse
   *      $query = Query( "Tfcnetx", "SELECT * FROM AccessUser WHERE UserName LIKE "Ngagnon"" );
   *
   * @param string $p_conn name of the connection to run query on.
   *
   * @param string $p_query query to be run
   */
  public function __construct( $p_conn, $p_query=UNDEFINED_VAL ){

    if( $p_query === UNDEFINED_VAL ){
    Db::initialize();
      $this->setConnection( Db::$DefaultConnection );
      $this->setQuery( $p_conn );
    } 
    else{
      $this->setConnection( $p_conn );
      $this->setQuery( $p_query );
    }
  }

  /**
   * Retreives the set of rows using Util::GetRows
   *
   * Will execute the query if it hasn't been  already.
   *
   * <b>Example:</b>
   * Retreives all login attempts within last day
   *      $logins_q = Query( "Tfcnetx", "SELECT * FROM AccessLogin WHERE DateTime >  NOW() - INTERVAL 1 DAY" );
   *      $logins = $logins_q->getRows();
   *
   * @param int $p_start Optional. Determines the starting index of set to return.  If blank, will start at beginning.
   *
   * @param int $p_count Optional. Determines the number of rows to return.  If blank will return all.  If less rows, will just return all of them
   *
   * @return array set of rows from query
   */
  public function getRows( $p_start=-1, $p_count=-1  ){
    if( !$this->getResult() ) $this->execute();
    return Db::getRows( $this->getResult(), $p_start, $p_count );
  }

  /**
   * Retreives the next row from query results using Db::GetRow
   *
   * Will execute the query if it hasn't been  already.
   *
   * <b>Example:</b>
   * Gets Just The last login row
   *      $logins_q = Query( "Tfcnetx", "SELECT * FROM AccessLogin ORDER BY DateTime DESC" );
   *      $logins = $logins_q->getRow();
   *
   * @return array the result row
   */
  public function getRow(){
    if( !$this->getResult() ) $this->execute();
    return Db::getRow( $this->getResult() );
  }

  /**
   * Returns the number of rows in a query result.
   *
   * Will execute the query if it hasn't been  already.
   *
   * <b>Example:</b>
   * Gets Count of number of User login attempts in the last day
   *      $logins_q = Query( "Tfcnetx", "SELECT * FROM AccessLogin WHERE DateTime >  NOW() - INTERVAL 1 DAY" );
   *      $login_cnt = $logins_q->getCount();
   *
   * @return array the result row
   */
  public function getCount(){
    if( !$this->getResult() ) $this->execute();
    return Db::getCount($this->getResult());
  }

  /**
   * Returns the Last Insert Id for a connection
   *
   * Will execute the query if it hasn't been  already.
   *
   * <b>Example:</b>
   * Gets insert Id 
   *      $insert_q = Query( "Tfcnetx", "INSERT INTO AccessUsers (UserName) VALUES ("Mytestuser")" );
   *      $insert_id = $logins_q->getInsertId();
   *
   *
   * @return int the last insert Id
   */
  public function getInsertId(){
    if( !$this->getResult() ) $this->execute();
    return Db::getInsertId($this->getConnection());
  }

  /**
   * TODO: doc this
   * 
   * @param mixed $ 
   *
   * @return mixed 
   */
  public function numRows(){
    if( !$this->getResult() ) $this->execute();
    return Db::getNumRows($this->getResult());
  }

  /**
   * Executes a query
   *
   * <b>Example:</b>
   * Executes an insert query
   *      $insert_q = Query( "Tfcnetx", "INSERT INTO AccessUsers (UserName) VALUES ("Mytestuser")" );
   *      $insert_q->execute();
   *
   * @return object the mysql_query result
   */
  public function execute(){
    $this->setResult( Db::query($this->getConnection(), $this->getQuery() ) );
    if( !$this->getResult() ){
      throw new \Exception( strval(mysql_error()) );
    }

    return $this->getResult();
  }
}

