<?php
require 'vendor/autoload.php';
use Elasticsearch\ClientBuilder;
class EsHandle {
    private $client;

    public function __construct() {

        //es索引节点配置
        $hosts = [
            '172.16.17.40:9200'
        ];
        $this->client = ClientBuilder::create()->setHosts($hosts)->build();    
    }


    /**
     * 将索引从老的指向新索引  new_index
     * @param  [type] $alias_index [description]
     * @param  [type] $old_index   [description]
     * @param  [type] $new_index   [description]
     * @return [type]              [description]
     */
    public function doAlias($alias_index, $old_index, $new_index) {


        $params['index'] = $old_index;
        $params['name'] = $alias_index;
        //先删除
        if (!empty($old_index) && $this->client->indices()->getAlias($params)) {
            $this->client->indices()->deleteAlias($params);
        }
        //再重新让别名指向
        $params['index'] = $new_index;
        $params['name'] = $alias_index;
        if (empty($this->client->indices()->getAlias($params))) {
            $this->client->indices()->putAlias($params);
        }
        
        return true;
    }


    /**
     * 每次build从my_index_v_1,my_index_v_2,my_index_v_3指向别名my_index
     * 以此来达到线上无缝切换
     * @param  [type] $alias_index_name [description]
     * @return [type]                   [description]
     */
    public function getIndexInfo($alias_index_name) {
        
        $params = [
            'index' => '*',
            'name' => $alias_index_name
        ];

        $aliasInfo = $this->client->indices()->getAlias($params);
        $old_index_name = '';
        if (!empty($aliasInfo) && is_array($aliasInfo)) {
            foreach ($aliasInfo as $_old_index => $_val) {
                $old_index_name = $_old_index;
            }
        }

        $index_arr = explode('_', $old_index_name);
        $version_num = array_pop($index_arr);
        $new_version_num = $version_num + 1;
        $new_index_name = $alias_index_name . '_v_' . $new_version_num;

        return ['new_index_name' => $new_index_name, 'old_index_name' => $old_index_name];
    }

    /**
     * 创建索引
     * @return [type] [description]
     */
    public function createIndex($index_name) {

        //检测索引是否存在
        $exists = $this->client->indices()->exists(['index' => $index_name]);
        if ($exists) {
            return false;
        }

        $params['index'] = $index_name;
        $response = $this->client->indices()->create($params);
        return true;
    }

    public function deleteIndex($index_name) {
         $params = ['index' => $index_name];
         return $this->client->indices()->delete($params);
    }

    /**
     * 创建mapping
     * @return [type] [description]
     */
    public function createMapping($index, $type) {

        // $params = [
        //     'index' => $this->es_index,
        //     'type' => $this->es_type,
        //     'body' => [
        //             $this->es_type => [
        //                 'properties' => [
        //                     'content' => [
        //                         'type' => 'keyword',
        //                         'analyzer' => 'ik_max_word',
        //                         'search_analyzer' => 'ik_max_word',
        //                         'index' => 'analyzed',
        //                         'term_vector' => 'with_positions_offsets'
        //                     ]
        //                 ]
        //             ]
        //         ]
        // ];



        $params = [
            'index' => $index,
            'type' => $type,
            'body' => [
                $type => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => [
            
                        'sellerid' => [
                            'type' => 'integer'
                        ],
                        'pcate' => [
                            'type' => 'integer'
                        ],
                        'pcate_name' => [
                            'type' => 'string'
                        ],
                        'ccate' => [
                            'type' => 'integer'
                        ],
                        'ccate_name' => [
                            'type' => 'string'
                        ],
                        'status' => [
                            'type' => 'integer'
                        ],
                        'title' => [
                             "type" => "text",
                             "analyzer" => "ik_max_word",
                             "search_analyzer" => "ik_max_word",
                             "index" => "analyzed",
                             "term_vector" => "with_positions_offsets"
                        ],
                        'suggest_title' => [
                            "type" => "completion",
                            "analyzer" => "ik_max_word",
                            "search_analyzer" => "ik_max_word"

                        ],
                        'thumb' => [
                            'type' => 'string'
                        ],
                        'goodssn' => [
                            'type' => 'string'
                        ],
                        'productsn' => [
                            'type' => 'string'
                        ],
                        'marketprice' => [
                            'type' => 'float'
                        ],
                        'productprice' => [
                            'type' => 'float'
                        ],
                        'costprice' => [
                            'type' => 'float'
                        ],
                        'total' => [
                            'type' => 'integer'
                        ],
                        'sales' => [
                            'type' => 'integer'
                        ],
                        'createtime' => [
                            'type' => 'integer'
                        ],
                        'skus' => [
                            'type' => 'object'
                        ]

                    ]
                ]
            ]
        ];

        // Update the index mapping
        $response = $this->client->indices()->putMapping($params);

        return $response;
    }

    // public function putData() {

    //     $params = [
    //         'index' => $this->es_index,
    //         'type' => $this->es_type,
    //         'body' => ['content' => '大家好才是真的好']
    //     ];

    //     $response = $this->client->index($params);
    //     return $response;
    // }

    //批量build索引
    public function batchBulk($index, $type, $goods) {
        if (empty($goods) || !is_array($goods)) {
            return false;
        }

        foreach ($goods as $_good) {
            $params['body'][] = [
                'index' => [
                    '_index' => $index,
                    '_type' => $type
                ]
            ];
            $params['body'][] = $_good;

        }
    
        if (!empty($params['body'])) {
            $responses = $this->client->bulk($params);
        }
        return true;
    }

    /**
     * 获取映射
     * @return [type] [description]
     */
    public function getMapping() {
        $params = ['index' => 'my_index'];
        $response = $this->client->indices()->getMapping($params);
        return $response;
    }

    /**
     * 搜索demo的方法
     * @param  SearchQuery $searchQuery [description]
     * @param  [type]      $order       [description]
     * @param  [type]      $desc        [description]
     * @param  [type]      $page        [description]
     * @param  [type]      $pagesize    [description]
     * @param  [type]      $range_arr   [description]
     * @return [type]                   [description]
     */
    public function doSearch(SearchQuery $searchQuery, $order, $desc, $page, $pagesize, $range_arr) {
        $query['index'] = 'b_index';
        $query['type'] = 'my_type';

        $query_arr = [];
        if (isset($searchQuery->title)) {
            if ($searchQuery->title['flag']) {
                $query_arr['bool']['must'][0]['multi_match']['query'] = $searchQuery->title['keys'];
                $query_arr['bool']['must'][0]['multi_match']['fields'] = ["title"];
                $query_arr['bool']['must'][0]['multi_match']['type'] = 'most_fields';
            } else {
                $query_arr['bool']['must_not'][0]['multi_match']['query'] = $searchQuery->title['keys'];
                $query_arr['bool']['must_not'][0]['multi_match']['fields'] = ["title"];
                $query_arr['bool']['must_not'][0]['multi_match']['type'] = 'most_fields';
            }
        }

        if (!empty($range_arr)) {
            $query_arr['bool']['filter'][] = $range_arr;
        }

        $query['body']['query'] = $query_arr;

        //highlight
        $query['body']['highlight'] = [
            'pre_tags' => '<em>',
            'post_tags' => '</em>',
            'fields' => [
                'title' => new stdClass()
            ]
        ];

        switch($order) {
            case '1':
                $order_field = 'createtime';
                break;
            case '2':
                $order_field = 'sales';
                break;
            case '3':
                $order_field = 'costprice';
                break;
            default:
                $order_field = 'creattime';
                break;
        }

        $desc = $desc ? 'desc' : 'asc';

        $query['body']['sort'] = [
            $order_field => [
                'order' => $desc
            ]
        ];

        $query['body']['from'] = ($page - 1) * $pagesize;
        $query['body']['size'] = $pagesize;
        echo '<pre>';
        echo json_encode($query['body']);
        echo '</pre>';
     
        $results = $this->client->search($query);
    
        return $results;
    }

}




