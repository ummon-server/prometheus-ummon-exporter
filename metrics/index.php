<?php

require_once '../vendor/autoload.php';

$scheme = getenv('HTTP_PROTO') ?: 'https';
$exporter = new \WayToHealth\OpenMetrics\Ummon\UmmonExporter(
    $scheme . '://' . getenv('UMMON_HOST'),
    getenv('UMMON_USER'),
    getenv('UMMON_PASSWORD')
);
$exporter->run();

