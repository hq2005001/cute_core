<?php

namespace Cute\search;

use Cute\Search;
use Elasticsearch\ClientBuilder;
use Cute\exceptions\SearchException;


class Elasticsearch extends Search
{

    protected $client = null;

    protected function connect()
    {
        $this->client = ClientBuilder::create()->setHosts($this->searchConf)->build();
        return $this->client;
    }

    /**
     * 设置数据
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function insert($data = null)
    {
        if(is_null($data)) {
            $data = $this->data;
        }
        if(array_is_column($data)) {
            //批量操作
            $insertData = ['body' => []];
            foreach($data as $d) {
                $tempData = [
                    'index' => [
                        '_index' => $this->index,
                        '_type' => $this->type,
                    ]
                ];
                if(isset($d['_id'])) {
                    $tempData['index']['_id'] = $d['_id'];
                    unset($d['_id']);
                }
                $insertData['body'][] = $tempData;
                $insertData['body'][] = $d;
            }
            $response = $this->execute('bulk', $insertData);
        } else {
            $insertData = [
                'index' => $this->index,
                'type' => $this->type,
            ];
            if(isset($data['_id'])) {
                $insertData['id'] = $data['_id'];
                unset($data['_id']);
                $insertData['body'] = $data;
            }
            $response = $this->execute('index', $insertData);
        }
        return $response;
    }

    public function query($options=[])
    {
        if(empty($this->query)) {
            throw new SearchException('未指定搜索条件');
        }
        if(!empty($options)) {
            $this->options($options);
        }
        $params =[
            'index' => $this->index,
            'type' => $this->type,
            'body' => [
                'query' => $this->query
            ]
        ];
        if(!is_null($this->skip)) {
            $params['body']['from'] = $this->skip;
        }
        if(!is_null($this->limit)) {
            $params['body']['size'] = $this->limit;
        }
        if(!is_null($this->sort)) {
            $params['body']['sort'] = $this->sort;
        }
        $response = $this->execute('search', $params);
        return $response;
    }

    public function getAll($options=[])
    {
        $data = $this->query($options);
        return $this->formatData($data);
    }

    protected function formatData($data)
    {
        $items = [];
        foreach($data['hits']['hits'] as $d) {
            $d['_source']['id'] = $d['_id'];
            $items[] = $d['_source'];
        }
        return $items;
    }

    /**
     * 取得分页的数据
     */
    public function getPaging($options=[])
    {
        $row = array_value($options, 'row', 10);
        $page = array_value($options, 'page', 1);
        $from = $row * ($page-1);
        $data = $this->skip($from)->limit($row)->query();
        $count = $data['hits']['total'];
        $items = [];
        foreach($data['hits']['hits'] as $d) {
            $items[] = $d['_source'];
        }
        return ['count' => $count, // 总条数
            'page' => ceil($count / $row), // 总页数
            'data' => $items
        ];
    }

    public function delete($id)
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => $id
        ];
        $response = $this->execute('delete', $params);
        return $response;
    }

    public function update($data = null, $type='doc', $params=[], $upsert=[])
    {
        if(is_null($data)) {
            $data = $this->data;
        }
        if(empty($this->query['id'])) {
            throw new SearchException('未指定条件');
        }

        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => $this->query['id'],
            
        ];
        switch($type) {
            case 'doc':
                $params['body'] = [
                    'doc' => $data,
                ];
                break;
            case 'script':
                $params['body'] = [
                    'script' => $data,
                    'params' => $params,
                ];
                break;
            default:
                throw new SearchException('非法的更新类型');
        }
        if(!empty($upsert)) {
            $params['body']['upsert'] = $upsert;
        }
        $response = $this->execute('update', $params);
        return $response;
    }

    protected function execute($type, $data)
    {
        return $this->conn()->$type($data);
    }

}