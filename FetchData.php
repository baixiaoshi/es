<?php

require_once "./MySqlDb.php";

class FetchData
{

    private $db = null;
    private $esHandle = null;
    public function __construct() {
        $this->db = new Db\MySqlDb();
        $this->esHandle = new EsHandle();
     
    }

    /**
     * 获取商品类目信息
     * @return [type] [description]
     */
    public function getCategory() {
        $sql = "select id,name from ims_bj_qmxk_category";
        $allRows = $this->db->getAll($sql);
        $ret_categories = [];
        foreach ($allRows as $_row) {
            if (isset($_row['id'])) {
                $ret_categories[$_row['id']] = $_row;
            }
        }
        return $ret_categories;
    }

    /**
     * 获取商品sku信息
     * @param  [type] $goodids [description]
     * @return [type]          [description]
     */
    public function getSkus($goodids) {
        if (empty($goodids) || !is_array($goodids)) {
            return false;
        }

        $skulist = [];
        foreach ($goodids as $_goodid) {
            $sql = "select a.goodsid, a.title as sku_cate_name, b.id as sku_id ,b.specid, b.title as sku_name from ims_bj_qmxk_spec a left join ims_bj_qmxk_spec_item b on a.id=b.specid where a.goodsid=$_goodid";
            
            $skus_rows = $this->db->getAll($sql);
            $skulist[$_goodid] = $skus_rows;
        }

        $ret_skulist = [];

        foreach ($skulist as $_goodid => $_val) {
            $sku_cates = $skus = [];
            foreach ($_val as $_key => $_skus) {
                $tmp_specid = isset($_skus['specid']) ? $_skus['specid'] : 0;
                $sku_cate_name = isset($_skus['sku_cate_name']) ? $_skus['sku_cate_name'] : '';
                $sku_id = isset($_skus['sku_id']) ? $_skus['sku_id'] : 0;
                $sku_name = isset($_skus['sku_name']) ? $_skus['sku_name'] : '';

                $sku_cates[$tmp_specid] = $sku_cate_name;
                $skus[$sku_id]['sku_cate_id'] = $tmp_specid;
                $skus[$sku_id]['sku_name'] = $sku_name;
            }

            $ret_skulist[$_goodid]['sku_cates'] = array_values($sku_cates);
            $ret_skulist[$_goodid]['skus'] = array_values($skus);
        }
        return $ret_skulist;
    }

    /**
     * 获取商品信息
     * @param  [type] $start_id [description]
     * @param  [type] $end_id   [description]
     * @return [type]           [description]
     */
    public function getGoods($start_id, $end_id) {
        $sql = "select id,sellerid,pcate, ccate, status, title, thumb, goodssn, productsn, marketprice, productprice, costprice, total, sales, createtime from ims_bj_qmxk_goods where id >=$start_id AND id < $end_id";
        $goods = $this->db->getAll($sql);

        $ret_goods = [];
        if (!empty($goods)) {
            foreach ($goods as $_good) {
                if (isset($_good['id'])) {
                    $ret_goods[$_good['id']] = $_good;
                }
            }
        }
        return $ret_goods;
    }

    /**
     * build数据到 es索引当中
     * @return [type] [description]
     */
    public function buildData($index, $type) {

       $categories = $this->getCategory();
       //$maxid = 298445;
       $maxid = 40000;
       $pagesize = 200;
       $pagenum = ceil($maxid / $pagesize);
        
       for ($page = 1; $page <= $pagenum; $page++) {
            $start_id = ($page - 1) * $pagesize;
            $end_id = $start_id + $pagesize;
            $goods = $this->getGoods($start_id, $end_id);
            $goodids = array_keys($goods);
            $skus = $this->getSkus($goodids);

            if (!empty($goods)) {
                foreach($goods as $_goodid => $_goods) {

                    if (isset($skus[$_goodid])) {
                        $goods[$_goodid]['skus'] = $skus[$_goodid];
                    } else {
                        $goods[$_goodid]['skus'] = [];
                    }

                    $pcate_name = isset($categories[$_goods['pcate']]) ? $categories[$_goods['pcate']]['name'] : '';
                    $ccate_name = isset($categories[$_goods['ccate']]) ? $categories[$_goods['ccate']]['name'] : '';

                    $goods[$_goodid]['pcate_name'] = $pcate_name;
                    $goods[$_goodid]['ccate_name'] = $ccate_name;

                }
            }
            
            $build_ret = $this->esHandle->batchBulk($index, $type, $goods);
            if (!$build_ret) {
                continue;
            }
       }



    }


    /**
     * 拉取线上数据
     * @param  [type] $start_id [description]
     * @param  [type] $end_id   [description]
     * @return [type]           [description]
     */
    public function getRows($start_id, $end_id) {

        //每次循环20000条记录
        $pagesize = 100;
        $pagenum = ceil(($end_id - $start_id) / $pagesize);


        for ($page = 1; $page <= $pagenum; $page++) {
            $tmp_start_id = ($page - 1) * $pagesize + $start_id;
            $tmp_end_id = $tmp_start_id + $pagesize;
            $goods_fields = "sellerid, pcate, ccate, `type`, `status`, title, ptthumb, thumb, goodssn, productsn, marketprice, productprice, costprice, total, sales, spec, createtime, updatetime";
            $sql = "select $goods_fields from ims_bj_qmxk_goods where id >= $tmp_start_id and id < $tmp_end_id";
            file_put_contents("/tmp/test.sql", "$sql\r\n", FILE_APPEND);
            $ret = $this->db->getAll($sql);
            if (!empty($ret)) {
                foreach ($ret as $_key => $_val) {
                    if (is_array($_val)) {
                        $field_str = $field_val_str = '';
                        foreach ($_val as $_field => $_field_val) {
                            $field_str .= "`" . $_field . "`,";
                            $field_val_str .= "'" . $_field_val . "',";
                        }
                        $field_str = rtrim($field_str, ",");
                        $field_val_str = rtrim($field_val_str, ",");
                        $tmpsql = "insert into ims_bj_qmxk_goods($field_str) values($field_val_str);";
                        file_put_contents("/Users/lion/Desktop/es/ims_bj_qmxk_goods.sql", "$tmpsql\r\n", FILE_APPEND);
                    }
                }
            }
            
            sleep(1);
        }
    }

}



