<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-19
 * Time: 下午6:20
 */

namespace Cute;


class Migrate extends Service
{

    protected $tableName = '';



    protected $pk = [
        'id',
        'int(11) UNSIGNED',
        'PRIMARY KEY',
        'AUTO_INCREMENT'
    ];

    protected $autoIncrement = false;

    protected $timestamp = true;

    protected $fields = [
    ];

    protected $timestampFields = [
        ['created_at', 'timestamp', 'not null default CURRENT_TIMESTAMP'],
        ['updated_at', 'timestamp', 'not null default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ];

    public function setFields($fields) {
        $this->fields[] = $fields;
    }

    public function create()
    {
        $createStatements = [];
        $createStatements[] = "CREATE TABLE IF NOT EXISTS `{$this->tableName}`(";
        $fields = [];
        $fields[] = implode(' ', $this->pk);
        foreach($this->fields as $field) {
            $fields[] = implode(' ', $field);
        }
        if($this->timestamp) {
            foreach($this->timestampFields as $field) {
                $fields[] = implode(' ', $field);
            }
        }
        $createStatements[] = implode(',', $fields);
        $createStatements[] = ') engine=InnoDB DEFAULT CHARSET=utf8mb4;';
        $sql = implode('', $createStatements);
        return app()->db('mysql')->execCmd($sql);
    }

    public function drop()
    {
        $sql = "drop table {$this->tableName}";
        return app()->db('mysql')->execCmd($sql);
    }
}