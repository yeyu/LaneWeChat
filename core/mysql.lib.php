<?php
namespace LaneWeChat\Core;
/*
 *  Copyright (C) 2012
 *     Ed Rackham (http://github.com/a1phanumeric/PHP-MySQL-Class)
 *  Changes to Version 0.8.1 copyright (C) 2013
 *  Christopher Harms (http://github.com/neurotroph)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class Mysql{
    
    // Base variables
    public static $lastError;         // Holds the last error
    public static $lastQuery;         // Holds the last query
    public static $result;            // Holds the MySQL query result
    public static $records;           // Holds the total number of records returned
    public static $affected;          // Holds the total number of records affected
    public static $rawResults;        // Holds raw 'arrayed' results
    public static $arrayedResult;     // Holds an array of the result
    
    private static $hostname;          // MySQL Hostname
    private static $username;          // MySQL Username
    private static $password;          // MySQL Password
    private static $database;          // MySQL Database
    
    private static $databaseLink;      // Database Connection Link
    


    /* *******************
     * Class Constructor *
     * *******************/
    
    function __construct($database, $username, $password, $hostname='localhost', $port=3306, $persistant = false){
        self::$database = $database;
        self::$username = $username;
        self::$password = $password;
        self::$hostname = $hostname.':'.$port;
        
        self::Connect($persistant);
    }
    
    /* *******************
     * Class Destructor  *
     * *******************/
    
    function __destruct(){
        self::closeConnection();
    }   
    
    /* *******************
     * Private Functions *
     * *******************/
    
    // Connects class to database
    // $persistant (boolean) - Use persistant connection?
    private static function Connect($persistant = false){
        self::CloseConnection();
        
        if($persistant){
            self::$databaseLink = mysql_pconnect(self::$hostname, self::$username, self::$password);
        }else{
            self::$databaseLink = mysql_connect(self::$hostname, self::$username, self::$password);
        }
        
        if(!self::$databaseLink){
        self::$lastError = 'Could not connect to server: ' . mysql_error(self::$databaseLink);
            return false;
        }
        
        if(!self::UseDB()){
            self::$lastError = 'Could not connect to database: ' . mysql_error(self::$databaseLink);
            return false;
        }
        
        self::setCharset(); // TODO: remove forced charset find out a specific management
        return true;
    }
    
    
    // Select database to use
    private function UseDB(){
        if(!mysql_select_db(self::$database, self::$databaseLink)){
            self::$lastError = 'Cannot select database: ' . mysql_error(self::$databaseLink);
            return false;
        }else{
            return true;
        }
    }
    
    
    // Performs a 'mysql_real_escape_string' on the entire array/string
    private function SecureData($data, $types=array()){
        if(is_array($data)){
            $i = 0;
            foreach($data as $key=>$val){
                if(!is_array($data[$key])){
                    $data[$key] = self::CleanData($data[$key], $types[$i]);
                    $data[$key] = mysql_real_escape_string($data[$key], self::$databaseLink);
                    $i++;
                }
            }
        }else{
            $data = self::CleanData($data, $types);
            $data = mysql_real_escape_string($data, self::$databaseLink);
        }
        return $data;
    }
    
    // clean the variable with given types
    // possible types: none, str, int, float, bool, datetime, ts2dt (given timestamp convert to mysql datetime)
    // bonus types: hexcolor, email
    private function CleanData($data, $type = ''){
        switch($type) {
            case 'none':
                // useless do not reaffect just do nothing
                //$data = $data;
                break;
            case 'str':
            case 'string':
                settype( $data, 'string');
                break;
            case 'int':
            case 'integer':
                settype( $data, 'integer');
                break;
            case 'float':
                settype( $data, 'float');
                break;
            case 'bool':
            case 'boolean':
                settype( $data, 'boolean');
                break;
            // Y-m-d H:i:s
            // 2014-01-01 12:30:30
            case 'datetime':
                $data = trim( $data );
                $data = preg_replace('/[^\d\-: ]/i', '', $data);
                preg_match( '/^([\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2})$/', $data, $matches );
                $data = $matches[1];
                break;
            case 'ts2dt':
                settype( $data, 'integer');
                $data = date('Y-m-d H:i:s', $data);
                break;

            // bonus types
            case 'hexcolor':
                preg_match( '/(#[0-9abcdef]{6})/i', $data, $matches );
                $data = $matches[1];
                break;
            case 'email':
                $data = filter_var($data, FILTER_VALIDATE_EMAIL);
                break;
            default:
                break;
        }
        return $data;
    }

    public static function config($database, $username, $password, $hostname='localhost', $port=3306, $persistant = false){
        self::$database = $database;
        self::$username = $username;
        self::$password = $password;
        self::$hostname = $hostname.':'.$port;
        
        self::Connect($persistant);
    }



    /* ******************
     * Public Functions *
     * ******************/

    // Executes MySQL query
    public static function executeSQL($query){
        self::$lastQuery = $query;
        if(self::$result = mysql_query($query, self::$databaseLink)){
            if (gettype(self::$result) === 'resource') {
                self::$records  = @mysql_num_rows(self::$result);
            } else {
               self::$records  = 0;
            }
            self::$affected = @mysql_affected_rows(self::$databaseLink);

            if(self::$records > 0){
                self::arrayResults();
                return self::$arrayedResult;
            }else{
                return true;
            }

        }else{
            self::$lastError = mysql_error(self::$databaseLink);
            return false;
        }
    }

    public static function commit(){
        return mysql_query("COMMIT", self::$databaseLink);
    }
  
    public static function rollback(){
        return mysql_query("ROLLBACK", self::$databaseLink);
    }

    public static function setCharset( $charset = 'UTF8' ) {
        return mysql_set_charset ( self::SecureData($charset,'string'), self::$databaseLink);
    }
    
    // Adds a record to the database based on the array key names
    public static function insert($table, $vars, $exclude = '', $datatypes=array()){

        // Catch Exclusions
        if($exclude == ''){
            $exclude = array();
        }

        array_push($exclude, 'MAX_FILE_SIZE'); // Automatically exclude this one

        // Prepare Variables
        $vars = self::SecureData($vars, $datatypes);

        $query = "INSERT INTO `{$table}` SET ";
        foreach($vars as $key=>$value){
            if(in_array($key, $exclude)){
                continue;
            }
            $query .= "`{$key}` = '{$value}', ";
        }

        $query = trim($query, ', ');

        return self::executeSQL($query);
    }

    // Deletes a record from the database
    public static function delete($table, $where='', $limit='', $like=false, $wheretypes=array()){
        $query = "DELETE FROM `{$table}` WHERE ";
        if(is_array($where) && $where != ''){
            // Prepare Variables
            $where = self::SecureData($where, $wheretypes);

            foreach($where as $key=>$value){
                if($like){
                    $query .= "`{$key}` LIKE '%{$value}%' AND ";
                }else{
                    $query .= "`{$key}` = '{$value}' AND ";
                }
            }

            $query = substr($query, 0, -5);
        }

        if($limit != ''){
            $query .= ' LIMIT ' . $limit;
        }

        return self::executeSQL($query);
    }


    // Gets a single row from $from where $where is true
    public static function select($from, $where='', $orderBy='', $limit='', $like=false, $operand='AND',$cols='*', $wheretypes=array()){
        // Catch Exceptions
        if(trim($from) == ''){
            return false;
        }

        $query = "SELECT {$cols} FROM `{$from}` WHERE ";

        if(is_array($where) && $where != ''){
            // Prepare Variables
            $where = self::SecureData($where, $wheretypes);

            foreach($where as $key=>$value){
                if($like){
                    $query .= "`{$key}` LIKE '%{$value}%' {$operand} ";
                }else{
                    $query .= "`{$key}` = '{$value}' {$operand} ";
                }
            }

            $query = substr($query, 0, -(strlen($operand)+2));

        }else{
            $query = substr($query, 0, -6);
        }

        if($orderBy != ''){
            $query .= ' ORDER BY ' . $orderBy;
        }

        if($limit != ''){
            $query .= ' LIMIT ' . $limit;
        }

        $result = self::executeSQL($query);
        if(is_array($result)) return $result;
        return array();

    }

    // Updates a record in the database based on WHERE
    public static function update($table, $set, $where, $exclude = '', $datatypes=array(), $wheretypes=array()){
        // Catch Exceptions
        if(trim($table) == '' || !is_array($set) || !is_array($where)){
            return false;
        }
        if($exclude == ''){
            $exclude = array();
        }

        array_push($exclude, 'MAX_FILE_SIZE'); // Automatically exclude this one

        $set    = self::SecureData($set, $datatypes);
        $where  = self::SecureData($where,$wheretypes);

        // SET

        $query = "UPDATE `{$table}` SET ";

        foreach($set as $key=>$value){
            if(in_array($key, $exclude)){
                continue;
            }
            $query .= "`{$key}` = '{$value}', ";
        }

        $query = substr($query, 0, -2);

        // WHERE

        $query .= ' WHERE ';

        foreach($where as $key=>$value){
            $query .= "`{$key}` = '{$value}' AND ";
        }

        $query = substr($query, 0, -5);

        return self::executeSQL($query);
    }

    // 'Arrays' a single result
    public static function arrayResult(){
        self::$arrayedResult = mysql_fetch_assoc(self::$result) or die (mysql_error(self::$databaseLink));
        return self::$arrayedResult;
    }

    // 'Arrays' multiple result
    public static function arrayResults(){
        //if(self::$records == 1){ //a two-dimensional array. by yeyu
        //    return self::arrayResult();
        //}
        self::$arrayedResult = array();
        while ($data = mysql_fetch_assoc(self::$result)){
            self::$arrayedResult[] = $data;
        }
        return self::$arrayedResult;
    }

    // 'Arrays' multiple results with a key
    public static function arrayResultsWithKey($key='id'){
        if(isset(self::$arrayedResult)){
            unset(self::$arrayedResult);
        }
        self::$arrayedResult = array();
        while($row = mysql_fetch_assoc(self::$result)){
            foreach($row as $theKey => $theValue){
                self::$arrayedResult[$row[$key]][$theKey] = $theValue;
            }
        }
        return self::$arrayedResult;
    }

    // Returns last insert ID
    public static function lastInsertID(){
        return mysql_insert_id(self::$databaseLink);
    }

    // Return number of rows
    public static function countRows($from, $where=''){
        $result = self::select($from, $where, '', '', false, 'AND','count(*)');
        return $result["count(*)"];
    }

    // Closes the connections
    public static function closeConnection(){
        if(self::$databaseLink){
            // Commit before closing just in case :)
            self::commit();
            mysql_close(self::$databaseLink);
        }
    }
}
?>
