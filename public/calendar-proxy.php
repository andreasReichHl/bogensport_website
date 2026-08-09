<?php

declare(strict_types=1);

$calendarUrl = 'https://nx92511.your-storageshare.de/remote.php/dav/public-calendars/5JpmMnqbqQrMCyYL?export';

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: public, max-age=300');

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'header' => "User-Agent: BadOldesloerBogensportWebsite/1.0\r\n",
    ],
]);

$calendarText = file_get_contents($calendarUrl, false, $context);

if ($calendarText === false) {
    http_response_code(502);
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Bad Oldesloer Bogensport//Calendar Proxy Error//DE\r\nEND:VCALENDAR\r\n";
    exit;
}

echo $calendarText;
