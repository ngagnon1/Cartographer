<?php

namespace Ncg\CartographerBundle\Core;

class FieldDefinition extends StdClass {

  public function initWriteable(){
    $this->Writeable = !$this->isPrimaryKey() && !$this->isAutoTimeStamp();
  }

  public function initInsertable(){
    $this->Insertable = !$this->isPrimaryKey() && !$this->isAutoTimeStamp();
  }

  public function isPrimaryKey(){
    return $this['Key'] == 'PRI';
  }

  public function isAutoTimeStamp(){
    return $this->getDefault() == 'CURRENT_TIMESTAMP';
  }

  public function isAutoIncrement(){
    return strpos($this['Extra'],"AutoIncrement")  !== false;
  }

  public function initFieldName(){
    $this->FieldName = $this['Field'];
  }

  public function getSqlValue( $p_val ){
    $value = getSqlValueString($p_val,Db::convertMysqlType($this->getType()));
    return $value;
  }

  public function getPreparedSqlValue($p_val ){
    $value = $this->getSqlValue($p_val);
    if( $this->getEncryptKey() !== NULL ){
      $key = getSqlValueString($this->getEncryptKey(),"Text");
      $value = "AES_ENCRYPT( $value, $key )";
    }
    return $value;
  }

}
