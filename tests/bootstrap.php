<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Загружаем helpers с моками фасадов
require_once __DIR__ . '/helpers.php';

date_default_timezone_set('UTC');

if (!class_exists('Mockery')) {
    throw new RuntimeException('Mockery is required for testing');
}