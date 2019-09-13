<?php

namespace Mavinoo\LaravelBatch;

use Illuminate\Support\Facades\DB;
use Mavinoo\LaravelBatch\Common\Helpers;

class Batch
{
    /**
     * Update multiple rows
     * @param string $table Table
     * @param array $values Values
     * @param string $index Index
     *
     * Example
     *
     * $table = 'users';
     * $value = [
     *      [
     *          'id' => 1,
     *          'status' => 'active',
     *          'nickname' => 'Mohammad'
     *      ] ,
     *      [
     *          'id' => 5,
     *          'status' => 'deactive',
     *          'nickname' => 'Ghanbari'
     *      ] ,
     * ];
     *
     * $index = 'id';
     *
     * @return mixed
     */
    public function update($table, $values, $index)
    {
        $final  = array();
        $ids    = array();

        if(!count($values))
            return false;
        if(!isset($index) AND empty($index))
            return false;

        foreach ($values as $key => $val)
        {
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field)
            {
                if ($field !== $index)
                {
                    $value           = (is_null($val[$field]) ? 'NULL' : '"' . Helpers::mysql_escape($val[$field]) . '"');
                    $final[$field][] = 'WHEN `'. $index .'` = "' . $val[$index] . '" THEN ' . $value . ' ';
                }
            }
        }

        $cases = '';
        foreach ($final as $k => $v)
        {
            $cases .=  '`'. $k.'` = (CASE '. implode("\n", $v) . "\n"
                            . 'ELSE `'.$k.'` END), ';
        }

        $query = "UPDATE `$table` SET " . substr($cases, 0, -2) . " WHERE `$index` IN(" . '"' . implode('","', $ids) . '"' . ");";

        return DB::statement($query);
    }


    /**
     * Insert Multi rows
     * $table String
     * $columns Array
     * $values Array
     * $batchSize Int
     *
     * Example
     *
     * $table = 'users';
     *
     * $columns = [
     *      'firstName',
     *      'lastName',
     *      'email',
     *      'isActive',
     *      'status',
     * ];
     *
     * $values = [
     *      [
     *          'Mohammad',
     *          'Ghanbari',
     *          'emailSample_1@gmail.com',
     *          '1',
     *          '0',
     *      ] ,
     *      [
     *          'Saeed',
     *          'Mohammadi',
     *          'emailSample_2@gmail.com',
     *          '1',
     *          '0',
     *      ] ,
     *      [
     *          'Avin',
     *          'Ghanbari',
     *          'emailSample_3@gmail.com',
     *          '1',
     *          '0',
     *      ] ,
     * ];
     *
     * $batchSize = 500; // insert 500 (default), 100 minimum rows in one query
     *
     */
    public function insert($table, $columns, $values, $batchSize = 500)
    {
        if(!isset($table) AND empty($table))
            return false;

        if(!count($values))
            return false;

        if(!count($columns))
            return false;

        if(count($columns) != count($values[0]))
            return false;


        $minChunck          = 100;

        $totalValues        = count($values);
        $batchSizeInsert    = ($totalValues < $batchSize AND $batchSize < $minChunck) ? $minChunck : $batchSize;

        $totalChunk         = ($batchSizeInsert < $minChunck) ? $minChunck : $batchSizeInsert;

        $values             = array_chunk($values, $totalChunk,true);


        foreach ($columns as $key => $column)
            $columns[$key]  = "`" . Helpers::mysql_escape($column) ."`";


        $query              = [];
        foreach ($values as $value)
        {
            $valueArray = [];
            foreach ($value as $data)
            {
                foreach ($data as $key => $item)
                {
                    $item = is_null($item) ? 'NULL' : "'" . Helpers::mysql_escape($item) ."'";
                    $data[$key]  = $item;
                }

                $valueArray[] =  '(' . implode(',', $data) . ')';
            }

            $valueString = implode(', ', $valueArray);

            $query []= "INSERT INTO `$table` (".implode(',', $columns).") VALUES $valueString;";

        }

        if(count($query))
        {
            return DB::transaction(function () use ($totalValues, $totalChunk, $query) {

                $totalQuery = 0;
                foreach ($query as $value)
                    $totalQuery += DB::statement($value) ? 1 : 0;

                return [
                    'totalRows'     =>  $totalValues,
                    'totalBatch'    =>  $totalChunk,
                    'totalQuery'    =>  $totalQuery
                ];

            });
        }

        return false;
    }
}
