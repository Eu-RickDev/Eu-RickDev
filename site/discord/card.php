<?php

const CARD_VERSION = '1.1.0';

const DISCORD_ID = '904957329718542387';
const CARD_NAME = 'RickDev';
const PHRASE = ['明鏡止水', '行雲流水'];
const CODE_APPS = '/visual studio code|^code$|vs ?code|vscodium|cursor/i';
const MUSIC_APPS = '/youtube music|spotify|deezer|apple music|soundcloud|tidal|amazon music/i';
const MUSIC_APP_IDS = ['1549518283747885188'];

const PRESENCE_TTL = 10;
const PRESENCE_STALE = 600;
const AVATAR_TTL = 3600;
const IMAGE_TTL = 86400;
const IMAGE_FAIL_TTL = 300;
const IMAGE_SIZE = 128;
const TICK_MAX = 1200;
const GAME_TICK_MAX = 180;
const USER_AGENT = 'rickdev.space status card';

const C = [
    'paper' => '#f3ebdd', 'paper2' => '#ece0c9', 'ink' => '#1b1815', 'inkSoft' => '#4a423a',
    'gold' => '#d9a441', 'goldDeep' => '#b8842c', 'seal' => '#8f3b34', 'sakura' => '#c98a8a',
];
const STATUS = [
    'online' => ['ONLINE', '#23a55a'],
    'idle' => ['AUSENTE', '#c98a8a'],
    'dnd' => ['NÃO PERTURBE', '#8f3b34'],
    'offline' => ['OFFLINE', '#4a423a'],
];
const LABELS = [0 => 'JOGANDO', 1 => 'TRANSMITINDO', 3 => 'ASSISTINDO', 5 => 'COMPETINDO'];

const SERIF = "Georgia, 'Times New Roman', serif";
const SANS = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif";
const JP = "'Yu Mincho', 'Hiragino Mincho ProN', 'Noto Serif JP', 'Noto Serif CJK JP', 'MS Mincho', serif";

const W = 500;
const TEXT_MAX = W - 170;
const TICK_CSS = '@keyframes tick{from,to{opacity:1}}.k{opacity:0;animation:tick 1s linear}';

function cache_path(string $key): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rickdev-card';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_-]/i', '_', $key);
}

function cache_read(string $key, int $ttl): ?string
{
    $file = cache_path($key);
    if (!is_file($file) || time() - (int) @filemtime($file) > $ttl) {
        return null;
    }
    $data = @file_get_contents($file);
    return $data === false ? null : $data;
}

function cache_write(string $key, string $data): void
{
    $file = cache_path($key);
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $data) !== false) {
        @rename($tmp, $file);
    }
}

function http_get(string $url, int $timeout = 4): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        return is_string($body) && $code === 200 ? [$body, $type] : null;
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'user_agent' => USER_AGENT,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = isset($http_response_header) ? $http_response_header : [];
    if ($body === false || !$headers) {
        return null;
    }
    $code = 0;
    $type = '';
    foreach ($headers as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $mm)) {
            $code = (int) $mm[1];
        } elseif (stripos($h, 'content-type:') === 0) {
            $type = trim(substr($h, 13));
        }
    }
    return $code === 200 ? [$body, $type] : null;
}

function presence(): ?array
{
    $raw = cache_read('presence', PRESENCE_TTL);
    if ($raw === null) {
        $res = cache_read('fail_presence', PRESENCE_TTL * 3) === null
            ? http_get('https://api.lanyard.rest/v1/users/' . DISCORD_ID)
            : null;
        $json = $res ? json_decode($res[0], true) : null;
        if (is_array($json) && !empty($json['success']) && is_array($json['data'] ?? null)) {
            $raw = json_encode($json['data']);
            cache_write('presence', $raw);
        } else {
            if ($res !== null || cache_read('fail_presence', PRESENCE_TTL * 3) === null) {
                cache_write('fail_presence', '1');
            }
            $raw = cache_read('presence', PRESENCE_STALE);
            if ($raw === null && ($old = cache_read('presence', 30 * 86400)) !== null) {
                $data = json_decode($old, true);
                if (is_array($data)) {
                    $data['discord_status'] = 'offline';
                    $data['activities'] = [];
                    $data['listening_to_spotify'] = false;
                    return $data;
                }
            }
        }
    }
    $data = $raw === null ? null : json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function sniff_mime(string $bytes): ?string
{
    if (strncmp($bytes, "\x89PNG", 4) === 0) {
        return 'image/png';
    }
    if (strncmp($bytes, "\xFF\xD8", 2) === 0) {
        return 'image/jpeg';
    }
    if (strncmp($bytes, 'GIF8', 4) === 0) {
        return 'image/gif';
    }
    if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    return null;
}

function to_data_uri(string $bytes, string $type = ''): ?string
{
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring($bytes);
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $s = min($w, $h);
            $dst = imagecreatetruecolor(IMAGE_SIZE, IMAGE_SIZE);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 0xf3, 0xeb, 0xdd));
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 2), IMAGE_SIZE, IMAGE_SIZE, $s, $s);
            ob_start();
            imagejpeg($dst, null, 86);
            return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
        }
    }
    $mime = strtolower(trim(explode(';', $type)[0]));
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        $mime = sniff_mime($bytes);
    }
    return $mime ? 'data:' . $mime . ';base64,' . base64_encode($bytes) : null;
}

function image_data_uri(string $url, int $ttl = IMAGE_TTL): ?string
{
    $id = md5($url);
    $hit = cache_read('img_' . $id, $ttl);
    if ($hit !== null) {
        return $hit;
    }
    if (cache_read('fail_' . $id, IMAGE_FAIL_TTL) !== null) {
        return null;
    }
    $res = http_get($url);
    $uri = $res ? to_data_uri($res[0], $res[1]) : null;
    if ($uri === null) {
        cache_write('fail_' . $id, '1');
        return null;
    }
    cache_write('img_' . $id, $uri);
    return $uri;
}

function app_icon_url(?string $app): ?string
{
    if (!$app || !ctype_digit($app)) {
        return null;
    }
    $icon = cache_read('app_' . $app, IMAGE_TTL);
    if ($icon === null) {
        if (cache_read('fail_app_' . $app, IMAGE_FAIL_TTL) !== null) {
            return null;
        }
        $res = http_get('https://discord.com/api/v10/applications/' . $app . '/rpc');
        $json = $res ? json_decode($res[0], true) : null;
        if (!is_array($json)) {
            cache_write('fail_app_' . $app, '1');
            return null;
        }
        $icon = (string) ($json['icon'] ?? '');
        cache_write('app_' . $app, $icon);
    }
    return $icon === '' ? null : 'https://cdn.discordapp.com/app-icons/' . $app . '/' . $icon . '.png?size=' . IMAGE_SIZE;
}

function resolve_image(array $urls, ?string $app = null, int $ttl = IMAGE_TTL): ?string
{
    foreach ($urls as $url) {
        if ($url && ($uri = image_data_uri($url, $ttl))) {
            return $uri;
        }
    }
    $icon = app_icon_url($app);
    return $icon ? image_data_uri($icon, $ttl) : null;
}

function asset_url(array $a, string $which): ?string
{
    $img = $a['assets'][$which] ?? null;
    if (!is_string($img) || $img === '') {
        return null;
    }
    if (strncmp($img, 'mp:', 3) === 0) {
        $path = substr($img, 3);
        $sep = strpos($path, '?') === false ? '?' : '&';
        return 'https://media.discordapp.net/' . $path . $sep . 'width=' . IMAGE_SIZE . '&height=' . IMAGE_SIZE;
    }
    if (strncmp($img, 'spotify:', 8) === 0) {
        return 'https://i.scdn.co/image/' . substr($img, 8);
    }
    if (strncmp($img, 'youtube:', 8) === 0) {
        return 'https://i.ytimg.com/vi/' . substr($img, 8) . '/hqdefault.jpg';
    }
    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }
    if (strpos($img, ':') !== false || !ctype_digit((string) ($a['application_id'] ?? ''))) {
        return null;
    }
    return 'https://cdn.discordapp.com/app-assets/' . $a['application_id'] . '/' . $img . '.png?size=' . IMAGE_SIZE;
}

function str_of($v): string
{
    return is_scalar($v) ? trim((string) $v) : '';
}

function card_model(?array $p): array
{
    $status = str_of($p['discord_status'] ?? 'offline');
    if (!isset(STATUS[$status])) {
        $status = 'offline';
    }
    $hash = str_of($p['discord_user']['avatar'] ?? '');
    $m = [
        'status' => $status,
        'avatar' => $hash !== ''
            ? 'https://cdn.discordapp.com/avatars/' . DISCORD_ID . '/' . $hash . '.png?size=' . IMAGE_SIZE
            : 'https://cdn.discordapp.com/embed/avatars/0.png',
        'activity' => null,
        'music' => null,
        'custom' => null,
    ];
    if ($status === 'offline' || !$p) {
        return $m;
    }

    $spotify = $p['spotify'] ?? null;
    if (!empty($p['listening_to_spotify']) && is_array($spotify)) {
        $m['music'] = [
            'title' => str_of($spotify['song'] ?? ''),
            'artist' => str_of($spotify['artist'] ?? ''),
            'cover' => [str_of($spotify['album_art_url'] ?? '')],
            'app' => null,
            'start' => $spotify['timestamps']['start'] ?? null,
            'end' => $spotify['timestamps']['end'] ?? null,
        ];
    }

    foreach (is_array($p['activities'] ?? null) ? $p['activities'] : [] as $a) {
        if (!is_array($a)) {
            continue;
        }
        $type = (int) ($a['type'] ?? -1);
        $name = str_of($a['name'] ?? '');

        if ($type === 4) {
            $emoji = $a['emoji'] ?? null;
            $text = str_of($a['state'] ?? '');
            $glyph = is_array($emoji) && empty($emoji['id']) ? str_of($emoji['name'] ?? '') : '';
            if ($text !== '' || $glyph !== '') {
                $m['custom'] = ['text' => $text, 'emoji' => $glyph];
            }
            continue;
        }

        if ($type === 2) {
            if (!$m['music'] && str_of($a['id'] ?? '') !== 'spotify:1') {
                $title = str_of($a['details'] ?? '');
                $m['music'] = [
                    'title' => $title !== '' ? $title : $name,
                    'artist' => $title !== '' ? str_of($a['state'] ?? '') : '',
                    'cover' => [asset_url($a, 'large_image'), asset_url($a, 'small_image')],
                    'app' => str_of($a['application_id'] ?? ''),
                    'start' => $a['timestamps']['start'] ?? null,
                    'end' => $a['timestamps']['end'] ?? null,
                ];
            }
            continue;
        }

        if (preg_match(MUSIC_APPS, $name) === 1 || in_array(str_of($a['application_id'] ?? ''), MUSIC_APP_IDS, true)) {
            continue;
        }

        if (isset(LABELS[$type]) && !$m['activity'] && $name !== '') {
            $coding = $type === 0 && preg_match(CODE_APPS, $name) === 1;
            $order = $coding ? ['small_image', 'large_image'] : ['large_image', 'small_image'];
            $m['activity'] = [
                'label' => $coding ? 'CODANDO' : LABELS[$type],
                'name' => $name,
                'coding' => $coding,
                'icon' => [asset_url($a, $order[0]), asset_url($a, $order[1])],
                'app' => str_of($a['application_id'] ?? ''),
                'since' => $a['timestamps']['start'] ?? ($a['created_at'] ?? null),
            ];
        }
    }

    return $m;
}

function esc(string $s): string
{
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return htmlspecialchars($clean === null ? $s : $clean, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function num(float $v): string
{
    $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    return $s === '-0' ? '0' : $s;
}

function chars(string $s): array
{
    $list = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($list) ? $list : str_split($s);
}

function char_em(string $ch, bool $serif): float
{
    if (strlen($ch) > 2) {
        return 1.0;
    }
    if ($ch === ' ') {
        return 0.27;
    }
    if (strpos("il.,:;'|!`", $ch) !== false) {
        return 0.3;
    }
    if (strpos('fjrtI()[]-', $ch) !== false) {
        return 0.42;
    }
    if (strpos('mwMW@', $ch) !== false) {
        return 0.9;
    }
    if (ctype_upper($ch)) {
        return $serif ? 0.76 : 0.68;
    }
    if (ctype_digit($ch)) {
        return $serif ? 0.62 : 0.56;
    }
    return $serif ? 0.6 : 0.53;
}

function text_width(string $s, float $size, bool $serif = false, float $spacing = 0): float
{
    $w = 0.0;
    foreach (chars($s) as $ch) {
        $w += char_em($ch, $serif) * $size + $spacing;
    }
    return $w;
}

function fit(string $s, float $max, float $size, bool $serif = false, float $spacing = 0): string
{
    if (text_width($s, $size, $serif, $spacing) <= $max) {
        return $s;
    }
    $list = chars($s);
    while ($list && text_width(rtrim(implode('', $list)) . '…', $size, $serif, $spacing) > $max) {
        array_pop($list);
    }
    return rtrim(implode('', $list)) . '…';
}

function fit_title(string $s, float $max): array
{
    foreach ([20, 18, 16] as $size) {
        if (text_width($s, $size, true) <= $max) {
            return [$s, $size];
        }
    }
    return [fit($s, $max, 16, true), 16];
}

function wrap_lines(string $s, float $max, float $size, bool $serif, int $lines): array
{
    $out = [];
    $line = '';
    foreach (preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
        $try = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && text_width($try, $size, $serif) > $max) {
            $out[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $out[] = $line;
    }
    if (count($out) > $lines) {
        $out = array_slice($out, 0, $lines);
        $out[$lines - 1] .= ' …';
    }
    return array_map(fn($l) => fit($l, $max, $size, $serif), $out);
}

function clock(float $sec): string
{
    $sec = max(0, (int) floor($sec));
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function minutes_text(int $min): string
{
    if ($min < 1) {
        return 'agora mesmo';
    }
    if ($min < 60) {
        return 'há ' . $min . ' min';
    }
    $h = intdiv($min, 60);
    $rest = $min % 60;
    return 'há ' . $h . ' h' . ($rest ? ' ' . $rest . ' min' : '');
}

function brush(float $x, float $y, float $len, float $h, string $color, float $opacity = 0.9): string
{
    $p = fn($a, $b) => num($x + $a * $len) . ',' . num($y + $b * $h);
    $d = 'M' . $p(0, 0.15) . ' C' . $p(0.04, -0.5) . ' ' . $p(0.22, -0.62) . ' ' . $p(0.48, -0.52)
        . ' C' . $p(0.74, -0.44) . ' ' . $p(0.92, -0.34) . ' ' . $p(1, 0)
        . ' C' . $p(0.9, 0.18) . ' ' . $p(0.72, 0.4) . ' ' . $p(0.46, 0.48)
        . ' C' . $p(0.24, 0.55) . ' ' . $p(0.05, 0.62) . ' ' . $p(0, 0.15) . ' Z';
    $streaks = '';
    foreach ([-0.22, 0.05, 0.28] as $i => $o) {
        $streaks .= '<path d="M' . $p(0.7 + $i * 0.04, $o) . ' L' . $p(1.02 - $i * 0.02, $o * 0.6 - 0.02)
            . '" stroke="' . $color . '" stroke-width="' . num(max(1, $h * 0.09)) . '" stroke-linecap="round" opacity=".55"/>';
    }
    return '<g opacity="' . num($opacity) . '" filter="url(#brushF)"><path d="' . $d . '" fill="' . $color . '"/>' . $streaks . '</g>';
}

function tile(float $x, float $y, float $size, ?string $uri, string $kanji, float $rx): string
{
    $key = 't' . (int) $x . '_' . (int) $y;
    if ($uri) {
        return '<clipPath id="' . $key . '"><rect x="' . num($x) . '" y="' . num($y) . '" width="' . num($size) . '" height="' . num($size) . '" rx="' . num($rx) . '"/></clipPath>'
            . '<image href="' . $uri . '" x="' . num($x) . '" y="' . num($y) . '" width="' . num($size) . '" height="' . num($size)
            . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#' . $key . ')"/>'
            . '<rect x="' . num($x) . '" y="' . num($y) . '" width="' . num($size) . '" height="' . num($size) . '" rx="' . num($rx)
            . '" fill="none" stroke="' . C['ink'] . '" stroke-opacity=".25" stroke-width="1"/>';
    }
    return '<rect x="' . num($x) . '" y="' . num($y) . '" width="' . num($size) . '" height="' . num($size) . '" rx="' . num($rx) . '" fill="' . C['ink'] . '" filter="url(#roughS)"/>'
        . '<text x="' . num($x + $size / 2) . '" y="' . num($y + $size * 0.68) . '" text-anchor="middle" font-family="' . JP
        . '" font-weight="700" font-size="' . num($size * 0.52) . '" fill="' . C['gold'] . '">' . $kanji . '</text>';
}

function side_layout(float $icon, array $texts): array
{
    $tw = 0.0;
    foreach ($texts as $t) {
        $tw = max($tw, $t);
    }
    $x0 = round((W - ($icon + 14 + $tw)) / 2);
    return [$x0, $x0 + $icon + 14];
}

function label_text(float $x, float $y, string $text, string $anchor = 'start'): string
{
    return '<text x="' . num($x) . '" y="' . num($y) . '"' . ($anchor !== 'start' ? ' text-anchor="' . $anchor . '"' : '')
        . ' font-family="' . SANS . '" font-weight="700" font-size="11" letter-spacing="3" fill="' . C['inkSoft'] . '">' . esc($text) . '</text>';
}

function ticker(float $x, float $y, string $attrs, array $frames): string
{
    $svg = '<g ' . $attrs . '>';
    $last = count($frames) - 1;
    foreach ($frames as $i => [$text, $delay, $len]) {
        $style = 'animation-delay:' . num($delay) . 's;animation-duration:' . num(max(0.01, $len)) . 's'
            . ($i === $last ? ';animation-fill-mode:forwards' : '');
        $svg .= '<text class="k' . ($i === 0 ? ' k0' : '') . '" x="' . num($x) . '" y="' . num($y) . '" style="' . $style . '">' . esc($text) . '</text>';
    }
    return $svg . '</g>';
}

function since_frames($since, int $nowMs): array
{
    if (!is_numeric($since) || $since <= 0) {
        return [];
    }
    $el = max(0, ($nowMs - (float) $since) / 1000);
    $first = (int) floor($el / 60);
    $frames = [];
    for ($m = $first; $m <= $first + GAME_TICK_MAX; $m++) {
        $frames[] = [
            minutes_text($m),
            $m === $first ? 0 : $m * 60 - $el,
            $m === $first ? ($first + 1) * 60 - $el : 60,
        ];
    }
    return $frames;
}

function activity_block(array $a, float $s, int $nowMs, ?string $icon): array
{
    [$name, $size] = fit_title($a['name'], TEXT_MAX);
    $frames = since_frames($a['since'], $nowMs);
    $subW = 0.0;
    foreach ($frames as [$text]) {
        $subW = max($subW, text_width($text, 12));
    }
    [$x0, $tx] = side_layout(52, [
        text_width($a['label'], 11, false, 3),
        text_width($name, $size, true),
        $subW,
    ]);
    $svg = tile($x0, $s + 10, 52, $icon, $a['coding'] ? '工' : '遊', 8)
        . label_text($tx, $s + 24, $a['label'])
        . '<text x="' . num($tx) . '" y="' . num($s + 47) . '" font-family="' . SERIF . '" font-weight="700" font-size="' . $size . '" fill="' . C['ink'] . '">' . esc($name) . '</text>'
        . ($frames ? ticker($tx, $s + 65, 'font-family="' . SANS . '" font-size="12" fill="' . C['inkSoft'] . '"', $frames) : '');
    return [$svg, 84, $frames ? TICK_CSS : ''];
}

function music_block(array $mu, float $s, int $nowMs, ?string $cover): array
{
    [$title, $size] = fit_title($mu['title'], TEXT_MAX);
    $artist = fit($mu['artist'], TEXT_MAX, 13);
    [$x0, $tx] = side_layout(54, [
        text_width('OUVINDO', 11, false, 3),
        text_width($title, $size, true),
        text_width($artist, 13),
    ]);
    $svg = tile($x0, $s + 10, 54, $cover, '音', 6)
        . label_text($tx, $s + 25, 'OUVINDO')
        . '<text x="' . num($tx) . '" y="' . num($s + 48) . '" font-family="' . SERIF . '" font-weight="700" font-size="' . $size . '" fill="' . C['ink'] . '">' . esc($title) . '</text>'
        . ($artist !== '' ? '<text x="' . num($tx) . '" y="' . num($s + 66) . '" font-family="' . SANS . '" font-size="13" fill="' . C['inkSoft'] . '">' . esc($artist) . '</text>' : '');

    $start = is_numeric($mu['start']) ? (float) $mu['start'] : 0;
    $end = is_numeric($mu['end']) ? (float) $mu['end'] : 0;
    if ($start <= 0 || $end <= $start) {
        return [$svg, 82, ''];
    }

    $dur = ($end - $start) / 1000;
    $el = min($dur, max(0, ($nowMs - $start) / 1000));
    $left = $dur - $el;
    $p = $dur > 0 ? $el / $dur : 1;
    $bx = 90;
    $bw = W - 180;
    $css = '@keyframes fade{to{opacity:.35}}.song{animation:fade 1.5s ease ' . num(max(0, $left)) . 's forwards}';

    $svg .= '<rect x="' . $bx . '" y="' . num($s + 84) . '" width="' . $bw . '" height="5" rx="2.5" fill="' . C['ink'] . '" opacity=".14"/>'
        . '<rect class="bar" x="' . $bx . '" y="' . num($s + 83) . '" width="' . $bw . '" height="7" rx="3.5" fill="' . C['gold'] . '" filter="url(#barF)" style="transform:scaleX(' . num($p) . ')"/>';

    $timeAttrs = 'font-family="' . SANS . '" font-size="11" fill="' . C['inkSoft'] . '"';
    $ty = num($s + 106);
    if ($left > 0.5) {
        $css .= '@keyframes bar{from{transform:scaleX(' . num($p) . ')}to{transform:scaleX(1)}}'
            . '.bar{transform-box:fill-box;transform-origin:0 50%;animation:bar ' . num($left) . 's linear both}';
    }
    if ($left > 0.5 && $left <= TICK_MAX) {
        $css .= TICK_CSS;
        $first = (int) floor($el);
        $frames = [];
        for ($v = $first; $v <= (int) floor($dur); $v++) {
            $frames[] = [clock($v), $v === $first ? 0 : $v - $el, $v === $first ? $first + 1 - $el : 1];
        }
        $svg .= ticker($bx, $s + 106, $timeAttrs, $frames);
    } else {
        $svg .= '<text x="' . $bx . '" y="' . $ty . '" ' . $timeAttrs . '>' . clock($el) . '</text>';
    }
    $svg .= '<text x="' . ($bx + $bw) . '" y="' . $ty . '" text-anchor="end" ' . $timeAttrs . '>' . clock($dur) . '</text>';

    return ['<g class="song">' . $svg . '</g>', 120, $css];
}

function custom_block(array $c, float $s): array
{
    $text = trim(($c['emoji'] !== '' ? $c['emoji'] . ' ' : '') . $c['text']);
    $lines = wrap_lines($text, W - 90, 18, true, 2);
    $svg = '';
    $y = count($lines) > 1 ? $s + 38 : $s + 44;
    foreach ($lines as $i => $line) {
        $svg .= '<text x="' . num(W / 2) . '" y="' . num($y + $i * 25) . '" text-anchor="middle" font-family="' . SERIF
            . '" font-style="italic" font-size="18" fill="' . C['inkSoft'] . '">' . esc($line) . '</text>';
    }
    return [$svg, count($lines) > 1 ? 80 : 66];
}

function phrase_block(float $s): array
{
    $svg = '<text x="' . num(W / 2 + 2.5) . '" y="' . num($s + 46) . '" text-anchor="middle" font-family="' . JP . '" font-weight="700" font-size="30" letter-spacing="5" fill="'
        . C['ink'] . '" filter="url(#roughT)">' . esc(PHRASE[0]) . '<tspan fill="' . C['seal'] . '">・</tspan>' . esc(PHRASE[1]) . '</text>';
    return [$svg, 66];
}

function header_block(array $m, ?string $avatar): string
{
    [$label, $color] = STATUS[$m['status']];
    $offline = $m['status'] === 'offline';
    $nameW = text_width(CARD_NAME, 26, true);
    $labelW = 14 + text_width($label, 11, false, 3);
    $x0 = round((W - (74 + 13 + max($nameW, $labelW))) / 2);
    $cx = $x0 + 37;
    $cy = 66;
    $tx = $x0 + 74 + 13;

    $img = $avatar
        ? '<image href="' . $avatar . '" x="' . num($cx - 32) . '" y="' . num($cy - 32) . '" width="64" height="64" preserveAspectRatio="xMidYMid slice" clip-path="url(#avatar)"'
            . ($offline ? ' filter="url(#gray)" opacity=".7"' : '') . '/>'
        : '<circle cx="' . num($cx) . '" cy="' . $cy . '" r="32" fill="' . C['ink'] . '"/><text x="' . num($cx) . '" y="' . ($cy + 11) . '" text-anchor="middle" font-family="'
            . SERIF . '" font-weight="700" font-size="30" fill="' . C['gold'] . '">R</text>';

    return '<clipPath id="avatar"><circle cx="' . num($cx) . '" cy="' . $cy . '" r="32"/></clipPath>'
        . '<circle cx="' . num($cx) . '" cy="' . $cy . '" r="37" fill="none" stroke="' . C['ink'] . '" stroke-width="3" opacity=".85" filter="url(#rough)"/>'
        . $img
        . '<circle cx="' . num($cx + 26) . '" cy="' . ($cy + 26) . '" r="8" fill="' . $color . '" stroke="' . C['paper'] . '" stroke-width="3"/>'
        . '<text x="' . num($tx) . '" y="64" font-family="' . SERIF . '" font-weight="700" font-size="26" fill="' . C['ink'] . '">' . esc(CARD_NAME) . '</text>'
        . '<circle cx="' . num($tx + 4) . '" cy="82" r="4" fill="' . $color . '"/>'
        . label_text($tx + 14, 86, $label)
        . brush(W * 0.12, 120, W * 0.76, 10, C['gold'], 0.85);
}

function card_label(array $m): string
{
    $parts = [CARD_NAME . ' no Discord', mb_strtolower_safe(STATUS[$m['status']][0])];
    if ($m['activity']) {
        $parts[] = mb_strtolower_safe($m['activity']['label']) . ' ' . $m['activity']['name'];
    }
    if ($m['music']) {
        $parts[] = 'ouvindo ' . $m['music']['title'] . ($m['music']['artist'] !== '' ? ' de ' . $m['music']['artist'] : '');
    }
    return implode(' · ', $parts);
}

function mb_strtolower_safe(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function render_card(array $m, int $nowMs, callable $resolve): string
{
    $avatar = $resolve([$m['avatar']], null, AVATAR_TTL);
    $body = header_block($m, $avatar);
    $css = '';
    $y = 130.0;
    $kanji = '静';

    if ($m['activity']) {
        [$svg, $h, $c] = activity_block($m['activity'], $y, $nowMs, $resolve($m['activity']['icon'], $m['activity']['app'], IMAGE_TTL));
        $body .= $svg;
        $css .= $c;
        $y += $h;
        $kanji = $m['activity']['coding'] ? '工' : '遊';
    }
    if ($m['music']) {
        if ($m['activity']) {
            $body .= '<line x1="' . num(W / 2 - 90) . '" y1="' . num($y) . '" x2="' . num(W / 2 + 90) . '" y2="' . num($y) . '" stroke="' . C['ink'] . '" stroke-opacity=".12"/>';
            $y += 2;
        }
        [$svg, $h, $c] = music_block($m['music'], $y, $nowMs, $resolve($m['music']['cover'], $m['music']['app'], IMAGE_TTL));
        $body .= $svg;
        $css .= $c;
        $y += $h;
        if (!$m['activity']) {
            $kanji = '音';
        }
    }
    if (!$m['activity'] && !$m['music']) {
        [$svg, $h] = $m['custom'] ? custom_block($m['custom'], $y) : phrase_block($y);
        $body .= $svg;
        $y += $h;
    }

    $H = (int) ceil($y + 14);
    $ks = max(140, min(190, $H * 0.6));
    $back = '<text x="' . num(W / 2) . '" y="' . num($H - 12 - $ks * 0.08) . '" text-anchor="middle" font-family="' . JP . '" font-size="' . num($ks)
        . '" fill="' . C['ink'] . '" opacity=".045">' . $kanji . '</text>';
    if (substr_count($css, TICK_CSS) > 1) {
        $css = str_replace(TICK_CSS, '', $css) . TICK_CSS;
    }
    $css .= '@media (prefers-reduced-motion:reduce){.bar,.k{animation:none}.k0{opacity:1}}';
    $label = esc(card_label($m));

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . W . ' ' . $H . '" width="' . W . '" height="' . $H . '" role="img" aria-label="' . $label . '">'
        . '<title>' . $label . '</title>'
        . '<defs>'
        . '<filter id="rough" x="-10%" y="-30%" width="120%" height="160%"><feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="2" seed="4" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="4" xChannelSelector="R" yChannelSelector="G"/></filter>'
        . '<filter id="roughS" x="-5%" y="-30%" width="110%" height="160%"><feTurbulence type="fractalNoise" baseFrequency="0.06" numOctaves="1" seed="9" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="1.6" xChannelSelector="R" yChannelSelector="G"/></filter>'
        . '<filter id="roughT" x="-5%" y="-20%" width="110%" height="140%"><feTurbulence type="fractalNoise" baseFrequency="0.08" numOctaves="2" seed="11" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="1.6" xChannelSelector="R" yChannelSelector="G"/></filter>'
        . '<filter id="barF" x="-2%" y="-200%" width="104%" height="500%"><feTurbulence type="fractalNoise" baseFrequency="0.05 0.3" numOctaves="2" seed="9" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="3" xChannelSelector="R" yChannelSelector="G"/></filter>'
        . '<filter id="brushF" x="-3%" y="-120%" width="106%" height="340%"><feTurbulence type="fractalNoise" baseFrequency="0.03 0.12" numOctaves="2" seed="5" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="5" xChannelSelector="R" yChannelSelector="G"/></filter>'
        . '<filter id="grain" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" seed="11" stitchTiles="stitch"/><feColorMatrix values="0 0 0 0 0.106  0 0 0 0 0.094  0 0 0 0 0.082  1.4 0 0 0 -0.62"/></filter>'
        . '<filter id="fibers" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.005 0.08" numOctaves="2" seed="2" stitchTiles="stitch"/><feColorMatrix values="0 0 0 0 0.72  0 0 0 0 0.52  0 0 0 0 0.17  1.6 0 0 0 -0.85"/></filter>'
        . '<filter id="gray"><feColorMatrix type="saturate" values="0"/></filter>'
        . '<radialGradient id="vignette" cx="50%" cy="45%" r="75%"><stop offset="55%" stop-color="' . C['paper'] . '" stop-opacity="0"/><stop offset="100%" stop-color="' . C['paper2'] . '"/></radialGradient>'
        . '<clipPath id="frame"><rect width="' . W . '" height="' . $H . '" rx="14"/></clipPath>'
        . '</defs>'
        . '<style>' . $css . '</style>'
        . '<g clip-path="url(#frame)">'
        . '<rect width="' . W . '" height="' . $H . '" fill="' . C['paper'] . '"/>'
        . '<rect width="' . W . '" height="' . $H . '" filter="url(#fibers)" opacity=".45"/>'
        . '<rect width="' . W . '" height="' . $H . '" fill="url(#vignette)"/>'
        . $back
        . '<rect x="8" y="8" width="' . (W - 16) . '" height="' . ($H - 16) . '" rx="10" fill="none" stroke="' . C['ink'] . '" stroke-opacity=".2" stroke-width="1.3" filter="url(#rough)"/>'
        . $body
        . '<rect width="' . W . '" height="' . $H . '" filter="url(#grain)" opacity=".55"/>'
        . '</g></svg>';
}

function serve(): void
{
    ini_set('display_errors', '0');
    $svg = render_card(card_model(presence()), (int) round(microtime(true) * 1000), 'resolve_image');
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Card-Version: ' . CARD_VERSION);
    echo $svg;
}

if (PHP_SAPI !== 'cli') {
    serve();
}
