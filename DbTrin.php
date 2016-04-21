<?php

class DbTrin extends Eng{
    var $__name__ = "DbTrin";
    
    var $__config__ = array();
    var $__query_master = array(
        "show all table"    => "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='%s'"
    );
    var $__connection;
    var $__all_tables;
    var $__config_db_index = 0;
    var $eng;
    var $autoGenerateCLass = true;
    
    private function __connect( $self=null ){
        if($self){
            $this->__config__ = $self->data['Config']['Database'][ $this->__config_db_index ];
        }
        $this->__connection = new mysqli( $this->__config__['server'], $this->__config__['user'], $this->__config__['password'], $this->__config__['database'] );
        
        if ( $this->__connection->connect_error ) {
            die("Connection failed: " . $this->__connection->connect_error);
        }else{
            return $this->__connection;
        }
    }
    
    public function strQuery( $str ){
        $this->__connect();
        $result = $this->__connection->query( $str );
        return $result;
    }
    
    public function query( $str ){
        $this->__connect();
        $objData = $this->__connection->query( $str );
        
        $data = array();
        while($data[] = $objData->fetch_assoc() );
        array_pop( $data );
        $this->__connection->close();
        return $data;
    }
    
    public function DbTrin( $self=null, $autoGenerateCLass=true, $__config_db_index=0 ){
        $this->autoGenerateCLass = $autoGenerateCLass;
        $this->eng = $self;
        $this->__config_db_index = $__config_db_index;
        if($self){
            $this->__config__ = $self ? $self->data['Config']['Database'][ $this->__config_db_index ] : array();
            if(!$this->autoGenerateCLass){
                return $this;
            }
            $result = $this->strQuery( sprintf($this->__query_master["show all table"], $this->__config__['database']) );
            $dataAllTables = array();
            while( $dataAllTables[] = $result->fetch_assoc() );
            $this->__connection->close();
            
            $dataMain = array();
            foreach( $dataAllTables as $key => $val ){
                $dataMain[ $val['TABLE_NAME'] ] = array(
                    "fields" => array(),
                    "information" => $val
                );
            }
            foreach( $dataAllTables as $key => $val ){
                 $dataMain[ $val['TABLE_NAME'] ][ 'fields' ][ $val[ 'COLUMN_NAME' ] ] = $val;
            }
            //$dataMain = (object) $dataMain;
            //print_r( $dataMain->tb_akses );
            
            $this->__all_tables = array();
            foreach( $dataMain as $key => $val ){
                $key = str_replace( "tb_", "", $key );
                if($key){
                    //echo "--[$key]--<br>";
                    $this->{ $key } = new TableGen( $this, $key, $val );
                }
            }
            
            return $this;
        }
    }
    
    
}

define("JSON", "json");
define("XML", "xml");
define("ARRAY", "array");
define("STRUCT", "struct");

class TableFunction{
    
    
    var $setField = "*";
    var $dbTrin, $tableName, $information, $fieldName;
    public function TableFunction( $dbTrin, $tableName, $fieldName, $information ){
        $this->dbTrin = $dbTrin;
        $this->tableName = $tableName;
        $this->information = $information;
        $this->fieldName = $fieldName;
        $this->setField = $fieldName;
    }
    
    public function callback($results, $method){
        switch($method){
            case "struct":
                return array(
                    "data"   => $results,
                    "struct" => $this->information['fields']
                );
                break;
            case "array":
                return $results;
                break;
            case "json":
                return json_encode($results);
                break;
            case "xml":
                return $this->dbTrin->instance->eng->Arr->toXml( $results );
                break;
            default:
                return $results;
                break;
        }
    }
    
    public function field( $setField ){
        $this->setField = $setField;
        return $this;
    }
    
    var $setLimit = "";
    public function limit( $setLimit ){
        $this->setLimit = $setLimit;
        return $this;
    }
    
    var $setAscDesc = "";
    var $setAscDescField = "";
    public function asc( $setAsc="ID" ){
        $this->setAscDesc = "ASC";
        $this->setAscDescField = $setAsc;
        return $this;
    }
    public function desc( $setDesc="ID" ){
        $this->setAscDesc = "DESC";
        $this->setAscDescField = $setDesc;
        return $this;
    }
    
    var $setWhere = "";
    public function where( $setWhere ){
        if(gettype($setWhere) == 'string'){
            $this->setWhere = $setWhere;
        }else{
            $tmp = array();
            foreach($setWhere as $k => $v){
                $tmp[] = $k . "='". $v ."'";
            }
            $this->setWhere = implode( " AND ", $tmp );
        }
        return $this;
    }
    
    //set mode
    var $setData = array();
    var $MODE_UPDATE = false;
    var $MODE_UPDATE_AND = true;
    public function set( $strValue, $mode="AND" ){
        $this->MODE_UPDATE = true;
        $this->MODE_UPDATE_AND = $mode=="AND" ? true : false;
        $this->setData = $strValue;
        return $this;
    }
    
    //COUNT ROWS BY data
    public function size( $args="" ){
        $this->args = $args;
        return $this->__select_mode( "count_rows" );
    }
    
    //JUST FOR EXISTS CHECK
    public function exists( $args="" ){
        $this->args = $args;
        
        switch( gettype($this->args) ){
            case "string":
                $q = "SELECT ". $this->fieldName ." FROM ". $this->tableName ." WHERE ". $this->fieldName ." LIKE '". $this->args ."'";
                return $this->dbTrin->instance->strQuery( $q )->num_rows ? true: false;
                break;
        }
    }
    
    //JUST FOR SEARCH
    public function find( $args="", $method='array' ){
        $this->args = $args;
        
        if( $this->MODE_UPDATE ){
            return $this->__update_mode();
        }else{
            return $this->__select_mode( $method );
        }
    }
    
    private function __update_mode(){
        $query = "";
        switch( gettype($this->args) ){
            case "string":
                if( gettype($this->setData) == "array" ){
                    $__set_field = [];
                    foreach( $this->setData as $key => $val ){
                        $__set_field[] = $key . "='". $val ."'";
                    }
                    $query = "UPDATE ". $this->tableName ." SET ". (implode(", ", $__set_field)) ." WHERE ". $this->fieldName ."='". $this->args ."'";
                }else{
                    $query = "UPDATE ". $this->tableName ." SET ". $this->fieldName ."='". $this->setData ."' WHERE ". $this->fieldName ."='". $this->args ."'";
                }
                break;
                
            case "array":
                if( gettype($this->setData) == "array" ){
                    $__set_field = [];
                    foreach( $this->setData as $key => $val ){
                        $__set_field[] = $key . "='". $val ."'";
                    }
                    if( gettype($this->args) == "array" ){
                        $__where_field = [];
                        foreach( $this->args as $keys => $vals ){
                            $__where_field[] = $keys . "='". $vals ."'";
                        }
                        $strArgMode = $this->MODE_UPDATE_AND ? "AND" : "OR";
                        $query = "UPDATE ". $this->tableName ." SET ". (implode(", ", $__set_field)) ." WHERE ". (implode(" ". $strArgMode ." ", $__where_field)) ;
                    }else{
                        $query = "UPDATE ". $this->tableName ." SET ". (implode(", ", $__set_field)) ." WHERE ". $this->fieldName ."='". $this->args ."'";
                    }
                }else{
                    if( gettype($this->args) == "array" ){
                        $__where_field = [];
                        foreach( $this->args as $keys => $vals ){
                            $__where_field[] = $keys . "='". $vals ."'";
                        }
                        $strArgMode = $this->MODE_UPDATE_AND ? "AND" : "OR";
                        $query = "UPDATE ". $this->tableName ." SET ". $this->fieldName ."='". $this->setData ."' WHERE ". (implode(" ". $strArgMode ." ", $__where_field)) ;
                    }else{
                        $query = "UPDATE ". $this->tableName ." SET ". $this->fieldName ."='". $this->setData ."' WHERE ". $this->fieldName ."='". $this->args ."'";
                    }
                }
                break;
        }
        
        
        //return $query;
        return $this->dbTrin->run_update( $query );
    }
    
    private function __select_mode( $method="" ){
        $__whereClause = "";
        switch( gettype($this->args) ){
            case "string":
                if($this->setField == "all"){
                    $__setField = "*";
                    $tmp = array();
                    foreach( $this->information['fields'] as $k => $v ){
                        if( $k != "all" ){
                            $tmp[] = $k . " LIKE '". $this->args ."'";
                        }
                    }
                    $__whereClause = " WHERE " . implode( " OR ", $tmp );
                }else{
                    $__whereClause = " WHERE ".$this->fieldName." LIKE '".$this->args."' ";
                }
                if($this->args == ''){ $__whereClause=''; }
                
                $query = "SELECT ". $this->setField ." FROM " .$this->tableName. $__whereClause;
                if( $this->setWhere ){
                    $query .= "AND " . $this->setWhere . " ";
                }
                if( $this->setAscDesc ){
                    $query .= " ORDER BY ". $this->setAscDescField ." ". $this->setAscDesc ." ";
                }
                if( $this->setLimit ){
                    $query .= " LIMIT " . $this->setLimit . " ";
                }
                
                if($method == "count_rows"){
                    return $this->dbTrin->instance->strQuery( $query )->num_rows;
                }else{
                    $results = $this->dbTrin->std()->run( $query );
                    return $this->callback( $results, $method );
                }
                break;
                
            case "array":
                if($this->setField == "all"){
                    $__setField = "*";
                    $tmps = [];
                    foreach( $this->information['fields'] as $k => $v ){
                        if( $k != "all" ){
                            $tmp = [];
                            foreach( $this->args as $key => $val ){
                                $tmp[] = $k . " LIKE '". $val ."'";
                            }
                            $tmps[] = "(". implode( " AND ", $tmp ) .")";
                        }
                    }
                    $__whereClause = " WHERE " . implode( " OR ", $tmps );
                }else{
                    $tmp = [];
                    foreach( $this->args as $key => $val ){
                        $tmp[] = $__setField . " LIKE '". $val ."'";
                    }
                    $__whereClause = " WHERE " . implode(" OR ", $tmp);
                }
                
                $query = "SELECT ". $__setField ." FROM " . $this->tableName . $__whereClause;
                
                if($method == "count_rows"){
                    return $this->dbTrin->instance->strQuery( $query )->num_rows;
                }else{
                    $results = $this->dbTrin->std()->run( $query );
                    return $this->callback( $results, $method );
                }
                break;
        }
    }
    
}


class TableGen{
    var $__name__ = "TableGen";
    
    var $tableName;
    var $instance;
    var $__formatData = "std";
    var $fields;
    var $information;
    
    public function TableGen( $dbTrin, $tableName, $information ){
        $this->instance = $dbTrin;
        $this->tableName = "tb_" . $tableName;
        $this->information = $information;
        $information['fields'][ "all" ] = "";
        foreach( $information['fields'] as $keys => $vals ){
            $key = str_replace( $tableName."_", "", $keys );
            if($key){
                //echo ">> --[$key]--<br>";
                $this->{ $key } = new TableFunction( $this, $this->tableName, $keys, $information );
            }
        }
    }
    
    private function fetch_assoc( $objData ){
        $data = array();
        while($data[] = $objData->fetch_assoc() );
        array_pop( $data );
        $this->instance->__connection->close();
        return $data;
    }
    
    public function __return( $objData ){
        if($this->__formatData === "std"){
            return $this->fetch_assoc( $objData );
        }else{
            switch($this->__formatData){
                case "row":
                    return mysql_num_rows( $objData );
                    break;
                
            }
        }
    }
    
    public function std(){
        $this->__formatData = "std";
        return $this;
    }
    public function row(){
        $this->__formatData = "row";
        return $this;
    }
    
    public function run_update( $query ){
        return $this->instance->strQuery( $query );
    }
    
    public function run( $query ){
        $dataAllTable = $this->instance->strQuery( $query );
        return $this->__return( $dataAllTable );
    }
    
    public function set( $setData, $mode="AND" ){
        $key = str_replace(  "tb_", "", $this->tableName );
        return $this->all->set( $setData, $mode );
    }
    
    public function size( $args="" ){
        return $this->all->size( $args );
    }
    
    public function last_rows(){
        $query = "SELECT Auto_increment as row FROM information_schema.tables WHERE table_name='". $this->tableName ."'";
        $dataAllTable = $this->instance->strQuery( $query );
        return $dataAllTable->num_rows;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}









/*
Fungsi where digantikan dengan fungsi find
SELECT * FROM tb_user WHERE user_name = 'ukung' 
digantikan 
SELECT * FROM tb_user WHERE user_name LIKE 'ukung'
Code: $db->user->name->field("*")->find( 'ukung' ); //return array

get all total rows
    $cek = $db->user->size();

get all total rows //return number
    $cek = $db->user->size([
            "user_name" => "ukung",
            "user_status" => "1"
        ]);  

get all total rows walaupun sudah di remove ( urutan )
    $cek = $db->user->last_rows();

check exists fields //return boolean
    $cek = $db->user->name->exists( "ukung" );

//UPDATE #1 //return array data
    $cek = $db->user->name->set("muhi")->find("salwa"); 
    
//UPDATE #2 //return array data
    $cek = $db->user->name->set("muhi")
        ->find([
            "ID" => "1",
            "user_status" => "1"
        ]);
    
//UPDATE #3 //return array data
    $cek = $db->user
        ->set([
            "user_name" => "salwa",
            "user_password" => "12345"
        ])
        ->find([
            "ID" => "2",    
            "user_status" => "1"    
        ]);
*/


























