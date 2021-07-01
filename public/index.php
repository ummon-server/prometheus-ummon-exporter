<?php

require_once '../vendor/autoload.php';

$exporter = new \WayToHealth\OpenMetrics\Ummon\UmmonExporter(
    getenv('UMMON_HOST'),
    getenv('UMMON_USER'),
    getenv('UMMON_PASSWORD'),
    getenv('HTTP_PROTO') ?: 'https'
);
$exporter->run();

