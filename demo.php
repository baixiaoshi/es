<!DOCTYPE html>
<html>
<head>
    <title>demo</title>
   <!-- 最新版本的 Bootstrap 核心 CSS 文件 -->
<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

<!-- 可选的 Bootstrap 主题文件（一般不用引入） -->
<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

<!-- 最新的 Bootstrap 核心 JavaScript 文件 -->
<script src="https://cdn.bootcss.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script src="http://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js" type="text/javascript"></script>

<script src="./masonry.js" ></script>

<script type="text/javascript" src="./jqPaginator.js"></script>
<style>
.mytitle {
    color:#333;
}
.mytitle em{
    color:red;
}
</style>

</head>
<body>
   <center>
     <form class="form-inline" id="myform" method="get" action="/demo.php">
        <div class="form-group">
          <label class="sr-only" for="keyword"></label>
          <input type="input" class="form-control" value="<?php if (!empty($_GET['keyword'])){echo $_GET['keyword'];}?>" name="keyword" id="keyword" placeholder="keyword">
        </div>

        <input type="hidden" name="page" value="1" />
        <input type="hidden" name="pagesize" value="100"/>


        <input type="hidden" name="desc" value="<?php if (!empty($_GET['desc'])) {echo $_GET['desc'];} else {echo 0;} ?>"/>



        <input type="submit" class="btn btn-default" value="search"/>




        <p>排序:


        <label class="radio-inline">
       
        <input type="radio" name="order" id="complex" value="1" <?php if (!empty($_GET['order']) && $_GET['order'] == 1 ) { echo 'checked';} ?> desc="0">  综合
        </label>
        <label class="radio-inline">
        
        <input type="radio" name="order" id="salenum" value="2" <?php if (!empty($_GET['order']) && $_GET['order'] == 2 ) { echo 'checked';} ?> desc="0">销量
        </label>
        <label class="radio-inline">
        
        <input type="radio" name="order" id="price" value="3" <?php if (!empty($_GET['order']) && $_GET['order'] == 3 ) { echo 'checked';} ?> desc="0">价格
        </label>
        价格区间:
        <input type="text" name="min_price" value="<?php if (!empty($_GET['min_price'])) {echo $_GET['min_price'];} ?>" />-
        <input type="text" name="max_price" value="<?php if (!empty($_GET['max_price'])) {echo $_GET['max_price'];}?>"/><a href="javascript:void" id="btn_price">确定</a>
        </p>

      </form>


    </center>


    


<?php
    
    require_once "./EsHandle.php";
    require_once "./SearchQuery.php";
    $esHandle = new EsHandle();
    $query = new SearchQuery();

    $page = isset($_GET['page']) ? trim($_GET['page']) : 1;
    $pagesize = isset($_GET['pagesize']) ? trim($_GET['pagesize']) : 100;

    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    //排序字段
    $order = isset($_GET['order']) ? trim($_GET['order']) : 1;
    //顺序还是逆序
    $desc = isset($_GET['desc']) ? trim($_GET['desc']) : 1;
    
    $min_price = isset($_GET['min_price']) ? $_GET['min_price'] : 0;
    $max_price = isset($_GET['max_price']) ? $_GET['max_price'] : 0;

    if (!empty($keyword)) {
        $query->title = ['keys' => $keyword, 'flag' => true];
    }
    $range_arr = [];
    if (!empty($min_price) && !empty($max_price)) {
        if ($min_price >= $max_price) {
            return ;
        }

        $range_arr = [
            [
                'range' => [
                    'costprice' => [
                        'gte' => $min_price,
                        'lte' => $max_price
                    ]
                ]
            ]
        ];
    }
    

    $esResult = $esHandle->doSearch($query, $order, $desc, $page, $pagesize, $range_arr);

    $total = $esResult['hits']['total'];
    $result = $esResult['hits']['hits'];
    
    // echo '<pre>';
    //     print_r($result);
    // echo '</pre>';
    // exit;
    //循环显示商品
    $item = '';
    foreach ($result as $_key => $_val) {
        $source = $_val['_source'];
        $highlight = $_val['highlight'];
        $item .= '<div class="item" style="border:2px solid #eee;">
        <div class="mywrap" style="width:190px;">
            <img width="190px" height="190px" src="http://img01.shunliandongli.com/attachment/' . $source['thumb'] . '"/>
            <p>价格:￥'.$source['costprice'].'</p>
            <p>销量:￥'.$source['sales'].'</p>
            <p class="mytitle" style="width:190px;height:20x;overflow:hidden;word-break:break-all"  >'. $highlight['title'][0] .'</p>
        </div>
        </div>';
    }




?>


<div id="container" style="width:900px;margin:0px auto;" class="js-masonry" data-masonry-options='{ "columnWidth": 200, "itemSelector": ".item" }'>
    <?php echo $item; ?>
</div>


<center>
    <h1>总共:<?php echo $total; ?>条记录, 页数：<?php echo ceil($total / $pagesize); ?></h1>
</center>


</body>
</html>




<script type="text/javascript">



    // 瀑布流
    $('#container').masonry({
    columnWidth: 200,
    itemSelector: '.item',
    horizontalOrder: true
    });
    $("input[name='order']").click(function() {
        let desc = $('input[name="desc"]').val();
        desc = (desc == '0') ? '1' : '0';

        console.log(desc + typeof desc);
        $('input[name="desc"]').val(desc);
        $('#myform').submit();
    });

    $('#btn_price').click(function() {
        let min_price = $('input[name="min_price"]').val();
        let max_price = $('input[name="max_price"]').val();

        $('#myform').submit();
    });

</script>


