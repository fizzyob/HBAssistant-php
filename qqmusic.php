<?php
/**
 * 仅用于黄白助手QQ音乐点歌接口
 * 使用方法: qqmusic.php?name=歌曲名
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 配置区域 - 请填入你的QQ音乐Cookie
$cookie = '你的QQ音乐Cookie';

// 获取歌曲名
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

if (empty($name)) {
    echo json_encode(['code' => 400, 'msg' => '请输入歌曲名'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 搜索歌曲
$searchUrl = 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp?w=' . urlencode($name) . '&p=1&n=1&format=json';
$searchResult = curlGet($searchUrl, $cookie);
$searchData = json_decode($searchResult, true);

if (empty($searchData['data']['song']['list'][0])) {
    echo json_encode(['code' => 404, 'msg' => '未找到歌曲'], JSON_UNESCAPED_UNICODE);
    exit;
}

$song = $searchData['data']['song']['list'][0];
$songmid = $song['songmid'];
$songid = $song['songid'];
$albummid = $song['albummid'];
$title = $song['songname'];
$singer = implode('/', array_column($song['singer'], 'name'));

// 封面图
$cover = 'https://y.gtimg.cn/music/photo_new/T002R300x300M000' . $albummid . '.jpg';

// 歌曲链接
$link = 'https://y.qq.com/n/ryqq/songDetail/' . $songmid;

// 获取播放链接
$musicUrl = getMusicUrl($songmid, $cookie);

// 获取歌词
$lyric = getLyric($songmid, $cookie);

// 返回结果
$result = [
    'code' => 200,
    'title' => $title,
    'singer' => $singer,
    'cover' => $cover,
    'link' => $link,
    'music_url' => $musicUrl,
    'lyric' => $lyric
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * 获取音乐播放链接
 */
function getMusicUrl($songmid, $cookie) {
    $guid = mt_rand(1000000000, 9999999999);
    $data = [
        'req_0' => [
            'module' => 'vkey.GetVkeyServer',
            'method' => 'CgiGetVkey',
            'param' => [
                'guid' => (string)$guid,
                'songmid' => [$songmid],
                'songtype' => [0],
                'uin' => '0',
                'loginflag' => 1,
                'platform' => '20'
            ]
        ]
    ];
    
    $url = 'https://u.y.qq.com/cgi-bin/musicu.fcg?data=' . urlencode(json_encode($data));
    $result = curlGet($url, $cookie);
    $resultData = json_decode($result, true);
    
    if (!empty($resultData['req_0']['data']['midurlinfo'][0]['purl'])) {
        $purl = $resultData['req_0']['data']['midurlinfo'][0]['purl'];
        $sip = $resultData['req_0']['data']['sip'][0] ?? 'https://ws.stream.qqmusic.qq.com/';
        return $sip . $purl;
    }
    
    return '';
}

/**
 * 获取歌词
 */
function getLyric($songmid, $cookie) {
    $url = 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg?songmid=' . $songmid . '&format=json&nobase64=1';
    $headers = [
        'Referer: https://y.qq.com/'
    ];
    $result = curlGet($url, $cookie, $headers);
    $data = json_decode($result, true);
    
    if (!empty($data['lyric'])) {
        $lyric = $data['lyric'];
        $lyric = preg_replace('/\[\d{2}:\d{2}\.\d{2,3}\]/', '', $lyric);
        $lines = explode("\n", $lyric);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        return implode("\n", $lines);
    }
    return '';
}

/**
 * CURL GET请求
 */
function curlGet($url, $cookie = '', $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
