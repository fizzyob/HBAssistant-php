<?php
/**
 * 仅用于黄白助手网易云音乐点歌接口
 * 使用方法: wyy.php?name=歌曲名
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 配置区域 - 请填入你的网易云音乐Cookie
$cookie = '';

// 获取歌曲名
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

if (empty($name)) {
    echo json_encode(['code' => 400, 'msg' => '请输入歌曲名'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 搜索歌曲（优先使用 cloudsearch，国外/风控环境下 web 搜索接口可能返回加密 result 字符串）
$searchUrl = 'https://music.163.com/api/cloudsearch/pc?s=' . urlencode($name) . '&type=1&offset=0&limit=1';
$searchResult = curlGet($searchUrl, $cookie);
$searchData = json_decode($searchResult, true);

// 兼容：如果 cloudsearch 未返回 songs，则回退旧接口
if (empty($searchData['result']['songs'][0])) {
    $fallbackUrl = 'https://music.163.com/api/search/get/web?s=' . urlencode($name) . '&type=1&offset=0&limit=1';
    $fallbackResult = curlPost($fallbackUrl, '', $cookie);
    $fallbackData = json_decode($fallbackResult, true);
    if (!empty($fallbackData['result']['songs'][0])) {
        $searchData = $fallbackData;
    }
}

if (empty($searchData['result']['songs'][0])) {
    echo json_encode(['code' => 404, 'msg' => '未找到歌曲'], JSON_UNESCAPED_UNICODE);
    exit;
}

$song = $searchData['result']['songs'][0];
$songId = $song['id'] ?? 0;
$title = $song['name'] ?? '';

// cloudsearch: ar/al；旧接口: artists/album
if (!empty($song['ar'])) {
    $singer = implode('/', array_column($song['ar'], 'name'));
} else {
    $singer = implode('/', array_column($song['artists'] ?? [], 'name'));
}

$albumId = 0;
if (!empty($song['al']['id'])) {
    $albumId = $song['al']['id'];
} elseif (!empty($song['album']['id'])) {
    $albumId = $song['album']['id'];
}

// 封面图
$cover = '';

// 优先：用 albumId 再拉一次（兼容旧逻辑）
if (!empty($albumId)) {
    $albumApi = 'https://music.163.com/api/album/' . $albumId;
    $albumResult = curlGet($albumApi, $cookie);
    $albumData = json_decode($albumResult, true);
    if (!empty($albumData['album']['picUrl'])) {
        $cover = $albumData['album']['picUrl'];
    }
}

// 回退：直接用搜索结果里的封面字段
if (empty($cover)) {
    if (!empty($song['al']['picUrl'])) {
        $cover = $song['al']['picUrl'];
    } elseif (!empty($song['album']['picUrl'])) {
        $cover = $song['album']['picUrl'];
    }
}

// 统一 https + 缩略图尺寸（避免部分客户端因图片过大不展示）
if (!empty($cover)) {
    if (str_starts_with($cover, 'http://')) {
        $cover = 'https://' . substr($cover, 7);
    }
    // Netease CDN 支持 param=WxH
    $cover .= (str_contains($cover, '?') ? '&' : '?') . 'param=300y300';
}

// 歌曲链接
$link = 'https://music.163.com/#/song?id=' . $songId;

// 获取播放链接
$musicUrl = getMusicUrl($songId, $cookie);

// 获取歌词
$lyric = getLyric($songId, $cookie);

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
function getMusicUrl($songId, $cookie) {
    // 尝试获取播放链接
    $url = 'https://music.163.com/api/song/enhance/player/url?ids=[' . $songId . ']&br=320000';
    $result = curlGet($url, $cookie);
    $data = json_decode($result, true);
    
    if (!empty($data['data'][0]['url'])) {
        return $data['data'][0]['url'];
    }
    
    // 备用外链方式
    return 'https://music.163.com/song/media/outer/url?id=' . $songId . '.mp3';
}

/**
 * 获取歌词
 */
function getLyric($songId, $cookie) {
    $url = 'https://music.163.com/api/song/lyric?id=' . $songId . '&lv=1&tv=1';
    $result = curlGet($url, $cookie);
    $data = json_decode($result, true);
    
    if (!empty($data['lrc']['lyric'])) {
        $lyric = $data['lrc']['lyric'];
        // 去掉时间戳 [00:00.00] 格式
        $lyric = preg_replace('/\[\d{2}:\d{2}\.\d{2,3}\]/', '', $lyric);
        // 按行分割，过滤空行，重新组合
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
function curlGet($url, $cookie = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://music.163.com/'
    ]);
    
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * CURL POST请求
 */
function curlPost($url, $data = '', $cookie = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://music.163.com/'
    ]);
    
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
