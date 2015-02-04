<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
/**
 * 获取、操作表数据
 * 
 * 
 *
 * @copyright  版权所有(C) 2014-2015 沈阳工业大学ACM实验室 沈阳工业大学网络管理中心 *Chen
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt   GPL2.0 License
 * @version    2.0
 * @link       http://acm.sut.edu.cn/
 * @since      File available since Release 2.0
*/
class TableInfo extends CI_Controller{
    function __construct() {
        parent::__construct();
    }
    
    public function index(){
        $this->load->library('session');
        $this->load->library('secure');
        $this->load->model('sql_lib');
        
        $data = array();
        
        $data['start'] = 0;
        $data['end'] = 30;        
        $data['table'] = htmlentities($this->input->get('t', TRUE), ENT_QUOTES);
        $data['database'] = htmlentities($this->input->get('db', TRUE), ENT_QUOTES);
        
        //获取浏览数据
        $data_temp = array();
        if (0 == ($data_temp = $this->sql_lib->getTableData($data['database'], 
                $data['table'], 
                $data['start'], 
                $data['end']))){
            echo '<script>alert("该表不存在");</script>';
            return 0;
        } else {
            $data = array_merge($data, $data_temp);
        }
        
        unset($data_temp);
        $data = array_merge($data, $this->sql_lib->getColData($data['database'], $data['table']));
        
        $backup_list = $this->GetSnapshot($this->session->userdata('db_type'), $data['database'], $data['table']);
        
        
        $this->load->view('TableInfoView', array('data' => $data,
                            'user_key' => $this->secure->CreateUserKey($this->session->userdata('db_username'),
                                    $this->session->userdata('db_password')),
                            'user_name' => $this->session->userdata('db_username'),
                            'db_type' => $this->session->userdata('db_type'),
                            'db_host' => $this->session->userdata('db_host'),
                            'db_port' => $this->session->userdata('db_port')));
    } 
    
    
    /**    
     *  @Purpose:    
     *  获取快照列表   
     *  @Method Name:
     *  GetSnapshot()
     *  @Parameter: 
     *  $db_type        数据库类型
     *  $database       数据库名称
     *  $table          表名称
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    private function GetSnapshot($db_type, $database, $table){
        // ./snapshot/$db_type/$database/$table
        
        $data = array();
        
        //先获取数据库总体快照        
        if (is_dir('./snapshot/' . $db_type . '/' . $database . '/')){
            $i_db = new FilesystemIterator('./snapshot/' . $db_type . '/' . $database . '/');
            foreach ($i_db as $db_snap){
                if ($db_snap->isFile()){
                    $data['db'][$db_snap->getFilename()]['size'] = $db_snap->getSize();
                    $data['db'][$db_snap->getFilename()]['m_time'] = date('Y-m-d H:i:s', $db_snap->getMTime());
                }
            }
                        
            if (is_dir('./snapshot/' . $db_type . '/' . $database . '/' . $table . '/')){
                $i_table = new FilesystemIterator('./snapshot/' . $db_type . '/' . $database . '/' . $table . '/');
                foreach ($i_table as $table_snap){
                    if ($table_snap->isFile()){
                        $data['table'][$table_snap->getFilename()]['size'] = $table_snap->getSize();
                        $data['table'][$table_snap->getFilename()]['m_time'] = date('Y-m-d H:i:s', $db_snap->getMTime());
                    }                    
                }      
                return $data;
            } else{                
                return $data;
            }
            
        } else {
            //没有数据库文件夹，直接返回0
            return 0;
        }        
    }
    
    /**    
     *  @Purpose:    
     *  创建快照   
     *  @Method Name:
     *  SetSnapshot()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     *  POST snap_type快照类型(0:表备份，1:数据库备份，2:整库备份)
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function SetSnapShot(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        if (!ctype_digit($this->input->post('snap_type', TRUE))){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -4, '快照类型错误');
        }
        
        //执行SQL语句，为记录模式
        $data = $this->sql_lib->setSnapShot($this->input->post('snap_type', TRUE), $this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (isset($data['data']) && is_string($data['data'])){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, '快照创建出错,出错信息:' . $data['data']);
        }
        
        if (!is_dir('./snapshot/')){
            mkdir('./snapshot/');
        }
        
        if (!is_dir('./snapshot/' . $this->input->post('db_type', TRUE) . '/')){
            mkdir('./snapshot/' . $this->input->post('db_type', TRUE) . '/');
        }
        
        if (!is_dir('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/')){
            mkdir('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/');
        }        
        
        if (!is_dir('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/' . $this->input->post('table', TRUE) . '/')){
            mkdir('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/' . $this->input->post('table', TRUE) . '/');
        }
        
        switch ($this->input->post('snap_type', TRUE)){
            case '0':
                try{
                //必须在使用SPL之前将目录从浅到深全部创建完毕
                    $output = new SplFileObject('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/' . $this->input->post('table', TRUE) . '/' . date('Y-m-d H:i:s') . '_' . rand(0, 999) . '.sql', 'w');
                } catch (Exception $ex) {
                    echo $ex->getMessage();
                }
                
                
                $sql_output = "-- WSPDM2 SQL Dump\n";
                $sql_output .= "-- version 2.0\n";
                $sql_output .= "-- https://github.com/SUTFutureCoder/intelligence_server/tree/master/WSPDM2\n";
                $sql_output .= "-- \n";
                $sql_output .= '-- 生成时间: ' . date("Y-m-d H:i:s") . "\n";
                $sql_output .= "-- \n";
                $sql_output .= "\n";
                $sql_output .= "\n";
                $sql_output .= "-- \n";
                $sql_output .= '-- 数据库: `' . $this->input->post('database', TRUE) . "`\n";
                $sql_output .= "-- \n";
                $sql_output .= "\n";
                $sql_output .= "-- --------------------------------------------------------\n";
                
                
                //建表      
                $sql_output .= "\n";
                $sql_output .= "-- \n";
                $sql_output .= "-- 表的结构 `" . $this->input->post('table', TRUE)  . "`\n";
                $sql_output .= "-- \n";
                $sql_output .= "\n";
                $sql_output .= 'CREATE TABLE IF NOT EXISTS ' . $this->input->post('database', TRUE) . '.' . $this->input->post('table', TRUE) . " (\n";
                
                $i = 0;
                foreach ($data['struct'] as $struct_key => $struct_value){
                    if (0 != $i){
                        $sql_output .= ",\n";
                    }
                    $sql_output .= '`' . $struct_value['COLUMN_NAME'] . '` ' . $struct_value['COLUMN_TYPE'];
                    if ('NO' == $struct_value['IS_NULLABLE']){
                        $sql_output .= ' NOT NULL ';
                    }
                    
                    if (NULL != $struct_value['COLUMN_DEFAULT']){
                        $sql_output .= " DEFAULT '" . $struct_value['COLUMN_DEFAULT'] . "' ";
                    }
                    
                    if ('' != $struct_value['COLUMN_COMMENT']){
                        $sql_output .= " COMMENT '" . $struct_value['COLUMN_DEFAULT'] . "' ";
                    }
                    ++$i;
                }
                $sql_output .= ') ENGINE=' . $data['engine']['STORAGE_ENGINE'] . ' DEFAULT CHARSET=' . $data['engine']['CHARACTER_SET_SYSTEM'] . ";\n";
                

                
                if (isset($data['data'])){
                //填充数据
                    $sql_output .= "\n";
                    $sql_output .= "--\n";
                    $sql_output .= "-- 转存表中的数据`" . $this->input->post('table', TRUE) . "`\n";
                    $sql_output .= "--\n";
                    $sql_output .= "\n";
                    
                    $sql_output .= "INSERT INTO " . $this->input->post('database', TRUE) . '.' . $this->input->post('table', TRUE) . " VALUES\n";
                    $i_a = 0;
                    foreach ($data['data'] as $data_key => $data_value){
                        if (0 != $i_a){
                            $sql_output .= "),\n";
                        }
                        $sql_output .= '(';
                        $i_b = 0;
                        foreach ($data_value as $key => $value){
                            if (0 != $i_b){
                                $sql_output .= ', ';
                            }
                            $sql_output .= "'" . $value . "'";
                            ++$i_b;
                        }
                        ++$i_a;
                    }
                    $sql_output .= ");\n";
                }
                
                //额外的设定
                $sql_output .= "\n";
                $sql_output .= "--\n";
                $sql_output .= "-- 额外的设定于表 `" . $this->input->post('table', TRUE) . "`\n";
                $sql_output .= "--\n";
                $sql_output .= "\n";
                
                
                foreach ($data['struct'] as $struct_key => $struct_value){
                    if ('' != $struct_value['COLUMN_KEY']){
                        //UNI => UNIQUE() / PRI => PRIMARY KEY (..)
                        $sql_output .= "ALTER TABLE " . $this->input->post('database', TRUE) . '.' . $this->input->post('table', TRUE) . "\n";
                        $flag = 0;
                        if ('UNI' == $struct_value['COLUMN_KEY']){                            
                            ++$flag;
                            $sql_output .= "ADD UNIQUE (" . $struct_key . ")\n";
                        }
                        
                        if ('PRI' == $struct_value['COLUMN_KEY']){
                            if ($flag){
                                $sql_output .= ', ';
                            }
                            $sql_output .= "ADD PRIMARY KEY (" . $struct_key . ")\n";
                        }
                        $sql_output .= ";\n\n";
                    }
                    if ('auto_increment' == $struct_value['EXTRA']){
                        $sql_output .= 'ALTER TABLE ' . $this->input->post('database', TRUE) . '.' . $this->input->post('table', TRUE) . "\n";
                        $sql_output .= 'MODIFY `' . $struct_key . '` ' . $struct_value['COLUMN_TYPE'];                        
                        if ('NO' == $struct_value['IS_NULLABLE']){
                            $sql_output .= ' NOT NULL ';
                        }
                        $sql_output .= ' AUTO_INCREMENT ';
                        if ('' != $struct_value['COLUMN_COMMENT']){
                            $sql_output .= " COMMENT '" . $struct_value['COLUMN_DEFAULT'] . "' ";
                        }
                        $sql_output .= ";\n";
                    }
                }
                $sql_output .= "\n";
                $sql_output .= "--\n";
                $sql_output .= "-- EOF -- 文件结束 --\n";
                $sql_output .= "--\n";
                $sql_output .= "\n";
                $output->fwrite($sql_output);
                unset($output);
                break;
                
            case '1':
                try{
                //必须在使用SPL之前将目录从浅到深全部创建完毕
                    $output = new SplFileObject('./snapshot/' . $this->input->post('db_type', TRUE) . '/' . $this->input->post('database', TRUE) . '/' . date('Y-m-d H:i:s') . '_' . rand(0, 999) . '.sql', 'w');
                } catch (Exception $ex) {
                    echo $ex->getMessage();
                }
                
                
                $sql_output = "-- WSPDM2 SQL Dump\n";
                $sql_output .= "-- version 2.0\n";
                $sql_output .= "-- https://github.com/SUTFutureCoder/intelligence_server/tree/master/WSPDM2\n";
                $sql_output .= "-- \n";
                $sql_output .= '-- 生成时间: ' . date("Y-m-d H:i:s") . "\n";
                $sql_output .= "-- \n";
                $sql_output .= "\n";
                $sql_output .= "\n";
                $sql_output .= "-- \n";
                $sql_output .= '-- 数据库: `' . $this->input->post('database', TRUE) . "`\n";
                $sql_output .= "-- \n";
                $sql_output .= "\n";
                $sql_output .= 'CREATE DATABASE IF NOT EXISTS ' . $this->input->post('database', TRUE) . ' DEFAULT CHARSET ' . $data['engine']['CHARACTER_SET_SYSTEM'] . ";\n";
                $sql_output .= "\n";
                $sql_output .= "-- --------------------------------------------------------\n";
                
                //建表
                foreach ($data['struct'] as $struct_key => $struct_value){
                    $sql_output .= "\n";
                    $sql_output .= "-- \n";
                    $sql_output .= "-- 表的结构 `" . $struct_key  . "`\n";
                    $sql_output .= "-- \n";
                    $sql_output .= "\n";
                    $sql_output .= 'CREATE TABLE IF NOT EXISTS ' . $this->input->post('database', TRUE) . '.' . $struct_key . " (\n";
                    
                    $i = 0;
                    foreach ($struct_value as $struct_row){
                        if (0 != $i){
                            $sql_output .= ",\n";
                        }
                        $sql_output .= '`' . $struct_row['COLUMN_NAME'] . '` ' . $struct_row['COLUMN_TYPE'];
                        if ('NO' == $struct_row['IS_NULLABLE']){
                            $sql_output .= ' NOT NULL ';
                        }

                        if (NULL != $struct_row['COLUMN_DEFAULT']){
                            $sql_output .= " DEFAULT '" . $struct_row['COLUMN_DEFAULT'] . "' ";
                        }

                        if ('' != $struct_row['COLUMN_COMMENT']){
                            $sql_output .= " COMMENT '" . $struct_row['COLUMN_DEFAULT'] . "' ";
                        }
                        ++$i;
                    }
                    $sql_output .= ') ENGINE=' . $data['engine']['STORAGE_ENGINE'] . ' DEFAULT CHARSET=' . $data['engine']['CHARACTER_SET_SYSTEM'] . ";\n";
                    
                    
                    if (isset($data['data'][$struct_key])){
                        //填充数据
                        $sql_output .= "\n";
                        $sql_output .= "--\n";
                        $sql_output .= "-- 转存表中的数据`" . $struct_key . "`\n";
                        $sql_output .= "--\n";
                        $sql_output .= "\n";

                        $sql_output .= "INSERT INTO " . $this->input->post('database', TRUE) . '.' . $struct_key .  " VALUES\n";
                        $i_a = 0;
                        foreach ($data['data'][$struct_key] as $data_key => $data_value){                        
                            if (0 != $i_a){
                                $sql_output .= "),\n";
                            }
                            $sql_output .= '(';
                            $i_b = 0;
                            foreach ($data_value as $key => $value){
                                if (0 != $i_b){
                                    $sql_output .= ', ';
                                }
                                $sql_output .= "'" . $value . "'";
                                ++$i_b;
                            }
                            ++$i_a;
                        }
                        
                        $sql_output .= ");\n";
                    }
                    
                    
                    //额外的设定
                    $sql_output .= "\n";
                    $sql_output .= "--\n";
                    $sql_output .= "-- 额外的设定于表 `" . $this->input->post('table', TRUE) . "`\n";
                    $sql_output .= "--\n";
                    $sql_output .= "\n";


                    foreach ($struct_value as $struct_row){
                        if ('' != $struct_row['COLUMN_KEY']){
                            //UNI => UNIQUE() / PRI => PRIMARY KEY (..)
                            $sql_output .= "ALTER TABLE " . $this->input->post('database', TRUE) . '.' . $struct_key . "\n";
                            $flag = 0;
                            if ('UNI' == $struct_row['COLUMN_KEY']){                            
                                ++$flag;
                                $sql_output .= "ADD UNIQUE (" . $struct_row['COLUMN_NAME'] . ")\n";
                            }

                            if ('PRI' == $struct_row['COLUMN_KEY']){
                                if ($flag){
                                    $sql_output .= ', ';
                                }
                                $sql_output .= "ADD PRIMARY KEY (" . $struct_row['COLUMN_NAME'] . ")\n";
                            }
                            $sql_output .= ";\n\n";
                        }
                        
                        if ('auto_increment' == $struct_row['EXTRA']){
                            $sql_output .= 'ALTER TABLE ' . $this->input->post('database', TRUE) . '.' . $struct_key . "\n";
                            $sql_output .= 'MODIFY `' . $struct_row['COLUMN_NAME'] . '` ' . $struct_row['COLUMN_TYPE'];                        
                            if ('NO' == $struct_row['IS_NULLABLE']){
                                $sql_output .= ' NOT NULL ';
                            }
                            $sql_output .= ' AUTO_INCREMENT ';
                            if ('' != $struct_row['COLUMN_COMMENT']){
                                $sql_output .= " COMMENT '" . $struct_row['COLUMN_DEFAULT'] . "' ";
                            }
                            $sql_output .= ";\n";
                        }
                    }
                }
                $sql_output .= "\n";
                $sql_output .= "--\n";
                $sql_output .= "-- EOF -- 文件结束 --\n";
                $sql_output .= "--\n";
                $sql_output .= "\n";
                
                $output->fwrite($sql_output);
                unset($output);
                break;
            case '2':
                break;
        }
    }
    

    /**    
     *  @Purpose:    
     *  执行SQL语句   
     *  @Method Name:
     *  ExecSQL()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST sql      SQL指令
     *  POST memcache 使用缓存
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     *  
     *  @Return: 
     *  状态码|说明
     *      1|data
     *      0|错误信息
     * 
     *  
    */ 
    public function ExecSQL(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $data = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$sql = trim($this->input->post('sql'), TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL命令不能为空', 'sql_area');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name']){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -4, 'SQL信息缺失，请重新登录');
        }
        
        
        switch (substr($sql, 0, 6)){
            case 'SELECT':
            case 'select':
                $data = $this->sql_lib->execSQL($query = 1, $this->input->post('sql', TRUE), 
                        $this->input->post('db_type', TRUE), 
                        $db['user_name'], 
                        $db['password'], 
                        $this->input->post('db_host', TRUE),
                        $this->input->post('db_port', TRUE), 
                        $this->input->post('memcache', TRUE));

                if (is_string($data)){
                    $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
                }

                if (isset($data['data'])){
                    foreach ($data['data'] as $key => $value){
                        foreach ($value as $col_name => $col_value){
                            $data['cols'][] = $col_name;
                        }
                        break;
                    }
                } else {
                    $data['cols'] = 0;
                }
                
                
                break;
                
            default :
                $data = $this->sql_lib->execSQL($query = 0, $this->input->post('sql', TRUE), 
                        $this->input->post('db_type', TRUE), 
                        $db['user_name'], 
                        $db['password'], 
                        $this->input->post('db_host', TRUE), 
                        $this->input->post('db_port', TRUE));
                if (is_string($data)){
                    $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
                }
                break;
        } 
            
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'ExecSQL', $data);
               
    }
    
       
    /**    
     *  @Purpose:    
     *  插入行   
     *  @Method Name:
     *  InsertData()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table      表名
     *  POST array data 插入数据（key => value）
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function InsertData(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }        
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE) ||
                null == $this->input->post('data', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        //取出post进的数据
        $post_data = $this->input->post('data', TRUE);
        
        $data = $this->sql_lib->insertData($this->input->post('database', TRUE), 
                $this->input->post('table', TRUE),
                $post_data,
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'InsertData', $data);
               
    }
    
    
       
    /**    
     *  @Purpose:    
     *  搜索数据   
     *  @Method Name:
     *  SearchData()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table      表名
     *  POST array data 搜索数据（key => value）
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function SearchData(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }        
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE) ||
                null == $this->input->post('data', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        //取出post进的数据
        $post_data = $this->input->post('data', TRUE);
        
        $data = $this->sql_lib->searchData($this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $post_data,
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }      
        if (isset($data['data'])){
            foreach ($data['data'][0] as $key => $value){  
                $data['cols'][] = $key;   
            }
        }
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'SearchData', $data);
               
    }
    
    
    
    /**    
     *  @Purpose:    
     *  删除列   
     *  @Method Name:
     *  DeleCol()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST col_name   列名
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function DeleCol(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE) || 
                null == $this->input->post('col_name', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
                
        //执行SQL语句，为记录模式
        //ALTER TABLE `activity` DROP `act_section`
        $data = $this->sql_lib->deleCol($this->input->post('database', TRUE), 
                $this->input->post('table', TRUE), 
                $this->input->post('col_name', TRUE), 
                $this->input->post('db_type', TRUE), 
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
                
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }
        
        $data['col_name'] = $this->input->post('col_name', TRUE);
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'DeleCol', $data);
               
    }    
    
    /**    
     *  @Purpose:    
     *  删除表   
     *  @Method Name:
     *  DeleTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function DeleTable(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        //执行SQL语句，为记录模式
        $data = $this->sql_lib->deleTable($this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }
        
        $data['table'] = $this->input->post('table', TRUE);
        $data['database'] = $this->input->post('database', TRUE);
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'DeleTable', $data);
               
    }
    
    /**    
     *  @Purpose:    
     *  清除表   
     *  @Method Name:
     *  TruncateTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function TruncateTable(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        //执行SQL语句，为记录模式
        $data = $this->sql_lib->truncateTable($this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }
        
        $data['table'] = $this->input->post('table', TRUE);
        $data['database'] = $this->input->post('database', TRUE);
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'TruncateTable', $data);
               
    }
    
    /**    
     *  @Purpose:    
     *  修改表名   
     *  @Method Name:
     *  RenameTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST old_table_name    操作表(旧表名)
     *  POST new_table_name    新表名
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function RenameTable(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('old_table_name', TRUE) || 
                null == $this->input->post('new_table_name', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }

        //执行SQL语句，为记录模式
        $data = $this->sql_lib->renameTable($this->input->post('database', TRUE),
                $this->input->post('old_table_name', TRUE),
                $this->input->post('new_table_name', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE));
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, 'SQL语句出错,出错信息:' . $data);
        }
        $data['old_table_name'] = $this->input->post('old_table_name', TRUE);
        $data['new_table_name'] = $this->input->post('new_table_name', TRUE);
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'RenameTable', $data);
               
    }
    
    
    /**    
     *  @Purpose:    
     *  修改数据   
     *  @Method Name:
     *  UpdateData()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     *  POST old_data Array 旧数据数组
     *  POST col_name Array 数据列名
     *  POST new_data Array 新数据数组
     * 
     *  @Return: 
     *  状态码|说明
     *      0|修改失败或未更改
     *      1|修改成功
     * 
     * 
     *  
    */ 
    public function UpdateData(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        $old_data = array();
        $old_data = $this->input->post('old_data', TRUE);
        
        $new_data = array();
        $new_data = $this->input->post('new_data', TRUE);
        
        $col_name = array();
        $col_name = $this->input->post('col_name', TRUE);
        
        $data = array();
        $data = $this->sql_lib->updateData($this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE),
                $old_data,
                $col_name,
                $new_data);    
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, '修改出错,出错信息:' . $data);
        }
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'UpdateData', $data);
    }
    
    
    /**    
     *  @Purpose:    
     *  删除数据   
     *  @Method Name:
     *  DeleData()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 操作数据库
     *  POST table    操作表
     *  POST db_type  数据库类型
     *  POST db_host  数据库地址
     *  POST db_port  数据库端口
     *  POST old_data Array 旧数据数组
     *  POST col_name Array 数据列名
     * 
     *  @Return: 
     *  状态码|说明
     *      0|修改失败或未更改
     *      1|修改成功
     * 
     * 
     *  
    */ 
    public function DeleData(){
        $this->load->library('secure');
        $this->load->library('data');
        $this->load->model('sql_lib');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                $this->data->Out('iframe', $this->input->post('src', TRUE), -1, '密钥无法通过安检');
            }
        } else {
            $this->data->Out('iframe', $this->input->post('src', TRUE), -2, '未检测到密钥');
        }
        
        if (!$this->input->post('db_type', TRUE) || !$db['user_name'] || 
                null == $this->input->post('database', TRUE) || 
                null == $this->input->post('table', TRUE)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), -3, 'SQL信息缺失，请重新登录');
        }
        
        $old_data = array();
        $old_data = $this->input->post('old_data', TRUE); 
        
        $col_name = array();
        $col_name = $this->input->post('col_name', TRUE);
        
        $data = array();
        $data = $this->sql_lib->deleData($this->input->post('database', TRUE),
                $this->input->post('table', TRUE),
                $this->input->post('db_type', TRUE),
                $db['user_name'],
                $db['password'],
                $this->input->post('db_host', TRUE),
                $this->input->post('db_port', TRUE),
                $old_data,
                $col_name);    
        
        if (is_string($data)){
            $this->data->Out('iframe', $this->input->post('src', TRUE), 0, '修改出错,出错信息:' . $data);
        }
        
        $this->data->Out('iframe', $this->input->post('src', TRUE), 1, 'DeleData', $data);
    }
    
    
    /**    
     *  @Purpose:    
     *  广播刷新表（表数据已被更改）   
     *  @Method Name:
     *  B_ReFreshTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST sql      其他用户执行的sql指令
     *  POST col      其他用户执行的sql指令影响的行数
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function B_ReFreshTable(){
        $this->load->library('secure');
        $this->load->library('data');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                return 0;
            }
        } else {
            return 0;
        }
        
        $data = array();
        $data['sql'] = $this->input->post('sql', TRUE);
        $data['col'] = $this->input->post('col', TRUE);
        $data['user_name'] = $this->input->post('user_name', TRUE);
        
        $this->data->Out('group', $this->input->post('src', TRUE), 1, 'B_ReFreshTable' ,  $data);
    }
    
    /**    
     *  @Purpose:    
     *  广播删除列   
     *  @Method Name:
     *  B_DeleCol()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST col_name   列名
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function B_DeleCol(){
        $this->load->library('secure');
        $this->load->library('data');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                return 0;
            }
        } else {
            return 0;
        }       
        $this->data->Out('group', $this->input->post('src', TRUE), 1, 'B_DeleCol' ,  $this->input->post('col_name', TRUE));
    }
    
    /**    
     *  @Purpose:    
     *  广播删除表   
     *  @Method Name:
     *  B_DeleTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 数据库名
     *  POST table 表
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function B_DeleTable(){
        $this->load->library('secure');
        $this->load->library('data');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                return 0;
            }
        } else {
            return 0;
        }       
        
        $data['database'] = $this->input->post('database', TRUE);
        $data['table'] = $this->input->post('table', TRUE);
        $this->data->Out('group', $this->input->post('src', TRUE), 1, 'B_DeleTable', $data);
    }
    
    /**    
     *  @Purpose:    
     *  广播清除表   
     *  @Method Name:
     *  B_TruncateTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 数据库名
     *  POST table 表
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function B_TruncateTable(){
        $this->load->library('secure');
        $this->load->library('data');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                return 0;
            }
        } else {
            return 0;
        }       
        
        $data['database'] = $this->input->post('database', TRUE);
        $data['table'] = $this->input->post('table', TRUE);
        $this->data->Out('group', $this->input->post('src', TRUE), 1, 'B_TruncateTable', $data);
    }
    
    /**    
     *  @Purpose:    
     *  广播重命名表   
     *  @Method Name:
     *  B_RenameTable()
     *  @Parameter: 
     *  POST user_name 数据库用户名
     *  POST user_key 用户密钥
     *  POST src      目标地址
     *  POST database 数据库名
     *  POST old_table_name 旧表名
     *  POST new_table_name 新表名
     * 
     *  @Return: 
     *  状态码|说明
     *      data
     * 
     *  
    */ 
    public function B_RenameTable(){
        $this->load->library('secure');
        $this->load->library('data');
        
        $db = array();
        if ($this->input->post('user_name', TRUE) && $this->input->post('user_key', TRUE)){
            $db = $this->secure->CheckUserKey($this->input->post('user_key', TRUE));
            if ($this->input->post('user_name', TRUE) != $db['user_name']){
                return 0;
            }
        } else {
            return 0;
        }       
        
        $data['database'] = $this->input->post('database', TRUE);
        $data['new_table_name'] = $this->input->post('new_table_name', TRUE);
        $data['old_table_name'] = $this->input->post('old_table_name', TRUE);
        $this->data->Out('group', $this->input->post('src', TRUE), 1, 'B_RenameTable', $data);
    }
    
}