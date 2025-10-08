<?php

namespace Tests;

use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Container\Container;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Переинициализируем контейнер
        Container::setInstance(new Container());

        // Регистрируем базовые биндинги
        $container = Container::getInstance();
        $container->singleton('config', function () {
            return new class {
                protected $items = [];

                public function get($key, $default = null)
                {
                    return $this->items[$key] ?? $default;
                }

                public function set($key, $value)
                {
                    $this->items[$key] = $value;
                }
            };
        });

        // Очищаем моки фасадов перед каждым тестом
        if (class_exists('Auth')) {
            \Auth::clearResolvedInstances();
        }
        if (class_exists('Config')) {
            \Config::clearResolvedInstances();
        }
        if (class_exists('RequestFacade')) {
            \RequestFacade::clearResolvedInstances();
        }
    }

    /**
     * Clean up the testing environment before the next test.
     */
    protected function tearDown(): void
    {
        Mockery::close();

        // Очищаем контейнер
        Container::setInstance(null);

        parent::tearDown();
    }
}