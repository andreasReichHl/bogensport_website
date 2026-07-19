<?php

$calendarUrl = 'https://nx92511.your-storageshare.de/remote.php/dav/public-calendars/5JpmMnqbqQrMCyYL?export';

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: public, max-age=300');

function send_error($message, $statusCode = 502) {
    http_response_code($statusCode);
    echo $message;
    exit;
}

if (function_exists('curl_init')) {
    $curl = curl_init($calendarUrl);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Bad Oldesloer Bogensport Website',
    ]);

    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($response === false || $statusCode >= 400) {
        send_error($error ?: 'Calendar request failed');
    }

    echo $response;
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'header' => "User-Agent: Bad Oldesloer Bogensport Website\r\n",
    ],
]);

$response = file_get_contents($calendarUrl, false, $context);

if ($response === false) {
    send_error('Calendar request failed');
}

echo $response;
