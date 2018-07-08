<?php
/**
 * 根据sitemap 抓取网站静态源码
 * $root 为文件所在目录
 * $site 网站域名
 * $siteMaps 为网站sitemap 集合
 *
 */
error_reporting(0); //抑制所有错误信息
$root     = '/home/bae/app/'; //文件所在的路径
$site     = 'https://www.helingqi.com/'; //主域名
$siteMaps = [
    'sitemap.xml',
    'mip_sitemap.xml']; //siteMap 集合
foreach ($siteMaps as $map) {
    if (!is_file($root . $map)) {
        file_put_contents($root . $map, file_get_contents($site . $map));
    }
}

//根据类型抓取
if (!isset($_GET['type'])) {
    exit('error');
} else {
    $type = $_GET['type'];
    switch ($type) {
        case 'mip':
            $siteMap = $root . 'mip_sitemap.xml';
            break;
        default:
            $siteMap = $root . 'sitemap.xml';
            break;
    }
}

$data = xmlToArray($siteMap);
$num  = 5; //每次生成条数
$urls = arrToUrl($data['url']);

array_unshift($urls, ''); //追加数据
$page    = isset($_GET['page']) ? (int) ($_GET['page']) : 1;
$all     = count($urls); //总条数
$pageAll = ceil($all / $num);
$header  = [
    'accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
    'accept-encoding' => 'gzip, deflate, br',
    'accept-language' => 'zh-CN,zh;q=0.9',
    'cache-control'   => 'no-cache',
    'user-agent'      => 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)',
];

if ($page <= $pageAll) {
    $arr2 = array_slice($urls, ($page - 1) * $num, $num);
    foreach ($arr2 as $k => $v) {
        echo create(trim($v), $header), trim($v), " <br>";
    }
    $page += 1;
    echo "第{$page}页已抓取", '<br>';
    echo "
    <script>
    window.setTimeout(\"location='//{$_SERVER['HTTP_HOST']}/wget.php?type={$type}&page={$page}'\", 5000);
    </script>";
} else {
    echo '生成完成，已抓取', $all, '条';
}

/**
 * 文件夹 or 文件创建函数
 * @param  string $path 路径
 * @param  array  $header header 参数
 * @return bool   状态
 */
function create($url, $header)
{
    global $root;
    if (empty($url)) {
        $msg = '参数错误';
    } else {
        $post    = ['']; //提交的参数
        $content = curl_https($url, $post, $header);
        $data    = parse_url($url); //解析URL
        $path    = pathinfo($data['path']); //解析路径
        $dir     = $root . $path['dirname'];
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $filename = $dir . '/' . $path['basename'];
        if (is_file($filename)) {
            $msg = '已抓取，跳过';
        } else {
            $bool = file_put_contents($filename, $content);
            if ($bool) {
                $msg = '抓取成功';
            } else {
                $msg = '抓取失败，请检查权限';
            }
        }
    }
    return $msg;
}

/**
 * XML 转数组
 * @param  string $xml
 */
function xmlToArray($xml)
{
    $xml   = simplexml_load_file($xml); //创建 SimpleXML对象
    $array = json_encode($xml); //对除resource（资源类型，保存了到外部资源的一个引用）类型之外的任何数据类型进行JSON编码
    $array = json_decode($array, true); //接受一个 JSON 格式的字符串并且把它转换为 PHP 变量，当该参数为 TRUE 时，将返回 array 而非 object 。
    return $array;

}

/**
 * curl 请求
 * @param  string  $url     请求的url
 * @param  array   $data    要发送的数据
 * @param  array   $header  请求时发送的header
 * @param  integer $timeout 超时时间，默认30s
 */
function curl_https($url, $data = array(), $header = array(), $timeout = 30)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);
    if ($error = curl_error($ch)) {
        die($error);
    }
    curl_close($ch);
    return $response;
}

/**
 * 打散xml数组
 * @param  array $data  获取到的xml 数组
 * @return array        新数组
 */
function arrToUrl($data)
{
    if ($data) {
        $newArr = [];
        foreach ($data as $k => $val) {
            array_push($newArr, $val['loc']);
        }
        return $newArr;
    } else {
        return false;
    }
}
