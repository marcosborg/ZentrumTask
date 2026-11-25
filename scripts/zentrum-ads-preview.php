<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$router = $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
$log = $root.'/storage/logs/ads-preview-server.log';
$streams = [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']];
$http = stream_context_create(['http' => ['timeout' => 4]]);
foreach (range(8098, 8108) as $port) {
    $origin = "http://127.0.0.1:{$port}";
    putenv("APP_URL={$origin}");
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $router], $streams, $pipes, $root.'/public');
    if (!is_resource($server)) {
        continue;
    }
    usleep(450000);
    if (!proc_get_status($server)['running']) {
        proc_close($server);
        continue;
    }
    register_shutdown_function(static function () use ($server): void {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }
    });
    $url = $origin.'/publicidade';
    $deadline = time() + 40;
    do {
        $page = @file_get_contents($url, false, $http);
        if ($page !== false && str_contains($page, 'id="ads-form"')) {
            echo "Pagina verificada: {$url}", PHP_EOL, "Mantenha esta janela aberta.", PHP_EOL;
            pclose(popen('start "" "'.$url.'"', 'r'));
            while (proc_get_status($server)['running']) {
                sleep(1);
            }
            exit(0);
        }
        usleep(400000);
    } while (time() < $deadline && proc_get_status($server)['running']);
    fwrite(STDERR, "Nao foi possivel confirmar a resposta de {$url}. Consulte {$log}.".PHP_EOL);
    exit(1);
}
fwrite(STDERR, "Nenhuma porta disponivel entre 8098 e 8108.".PHP_EOL);
exit(1);
