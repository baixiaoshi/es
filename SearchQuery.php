<?php

class SearchQuery
{   
    public $id = null; //商品id 商品id ['keys' => [1, 2, 3], 'flag' => true]
    public $sellerid = null; //商家id
    public $pcate = null; //一级类目id
    public $ccate = null; //二级类目id
    public $status = null; //状态
    public $title = null; //标题 ['keys' => "keyword", 'flag' => true]

    public $thumb = null; //图片地址

    public $goodssn = null; //商品条形码
    public $productsn = null; //商品编号

    public $marketprice = null; 
    public $productprice = null;
    public $costprice = null;
    public $total = null;
    public $sales = null;
    public $createtime = null;
    public $skus = null;
}