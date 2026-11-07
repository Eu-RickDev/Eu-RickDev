<?php

require __DIR__ . '/../site/discord/card.php';

$out = $argv[1] ?? (__DIR__ . '/../preview-card');
if (!is_dir($out)) {
    mkdir($out, 0775, true);
}

$avatar = to_data_uri((string) file_get_contents(__DIR__ . '/../avatar/rickdev-avatar.jpg'), 'image/jpeg');
$now = (int) round(microtime(true) * 1000);
$cover = 'mp:external/NNZtJPjF6VP-62JXeYITxVbu0Tx8usTl8ftPmZNsJ1c/https/is1-ssl.mzstatic.com/image/thumb/Music113/v4/eb/9d/18/eb9d188a-bb6d-477c-5dce-22a06f9abfa0/0.jpg/512x512bb.jpg';
$user = ['avatar' => 'local'];

$music = fn(string $title, string $artist, int $elapsed, int $dur) => [
    'type' => 2, 'name' => 'Youtube Music▶️', 'application_id' => '1549518283747885188',
    'details' => $title, 'state' => $artist, 'assets' => ['large_image' => $cover],
    'timestamps' => ['start' => $now - $elapsed * 1000, 'end' => $now + ($dur - $elapsed) * 1000],
];
$game = fn(string $name, int $mins, array $assets = []) => [
    'type' => 0, 'name' => $name, 'assets' => $assets,
    'timestamps' => ['start' => $now - $mins * 60000],
];
$with = fn(string $status, array $activities) => [
    'discord_status' => $status, 'discord_user' => $user, 'activities' => $activities,
];

$states = [
    'jogando' => $with('online', [$game('Deadlock', 23)]),
    'ouvindo' => $with('online', [$music('Stay Tonight', 'KASINO', 102, 215)]),
    'jogando-e-ouvindo' => $with('dnd', [$game('Deadlock', 23), $music('Stay Tonight', 'KASINO', 102, 215)]),
    'codando' => $with('idle', [$game('Visual Studio Code', 80)]),
    'status-personalizado' => $with('online', [['type' => 4, 'name' => 'Custom Status', 'state' => "Fazendo o Fish N' Chips", 'emoji' => ['name' => '🎣']]]),
    'sem-atividade' => $with('online', []),
    'offline' => $with('offline', []),
    'api-fora' => null,
    'nomes-longos' => $with('online', [
        $game('Grand Theft Auto V Premium Online Edition Remastered', 135),
        $music('An Extremely Long Song Title That Keeps Going (feat. Somebody) [Remastered]', 'Artist One, Artist Two & The Orchestra', 30, 4000),
    ]),
];

$resolve = function (array $urls, ?string $app, int $ttl) use ($avatar) {
    foreach ($urls as $u) {
        if ($u && strpos($u, '/local.png') !== false) {
            return $avatar;
        }
    }
    return resolve_image($urls, $app, $ttl);
};

foreach ($states as $name => $p) {
    $svg = render_card(card_model($p), $now, $resolve);
    file_put_contents($out . '/' . $name . '.svg', $svg);
    printf("%-22s %6.1f KB\n", $name, strlen($svg) / 1024);
}
