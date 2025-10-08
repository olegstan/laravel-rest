<?php

use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str as IlluminateStr;
use Mockery\MockInterface;

// Инициализируем контейнер
$container = Container::getInstance();

// Регистрируем базовые биндинги
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

if (!function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('response')) {
    function response()
    {
        return new class {
            public function json($data = [], $status = 200, array $headers = [])
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        if ($key === null) {
            return app('config');
        }
        return app('config')->get($key, $default);
    }
}

// Создаём алиас для Str, если его нет
if (!class_exists('Str') && class_exists('Illuminate\Support\Str')) {
    class_alias('Illuminate\Support\Str', 'Str');
}

if (!class_exists('Auth')) {
    class Auth
    {
        protected static $instance;

        public static function shouldReceive(...$args)
        {
            if (!static::$instance) {
                static::$instance = Mockery::mock('Auth');
            }
            return static::$instance->shouldReceive(...$args);
        }

        public static function getRole()
        {
            if (static::$instance) {
                return static::$instance->getRole();
            }
            return 'admin';
        }

        public static function clearResolvedInstances()
        {
            static::$instance = null;
        }
    }
}

if (!class_exists('Illuminate\Support\Facades\Auth')) {
    class_alias('Auth', 'Illuminate\Support\Facades\Auth');
}

if (!class_exists('Illuminate\Support\Facades\Config')) {
    class Config
    {
        protected static $instance;

        public static function shouldReceive(...$args)
        {
            if (!static::$instance) {
                static::$instance = Mockery::mock('Config');
            }
            return static::$instance->shouldReceive(...$args);
        }

        public static function get($key, $default = null)
        {
            if (static::$instance) {
                return static::$instance->get($key, $default);
            }
            return config($key, $default);
        }

        public static function clearResolvedInstances()
        {
            static::$instance = null;
        }
    }
    class_alias('Config', 'Illuminate\Support\Facades\Config');
}

if (!class_exists('Illuminate\Support\Facades\Request')) {
    class RequestFacade
    {
        protected static $instance;

        public static function shouldReceive(...$args)
        {
            if (!static::$instance) {
                static::$instance = Mockery::mock('RequestFacade');
            }
            return static::$instance->shouldReceive(...$args);
        }

        public static function method()
        {
            if (static::$instance) {
                return static::$instance->method();
            }
            return 'GET';
        }

        public static function clearResolvedInstances()
        {
            static::$instance = null;
        }
    }
    class_alias('RequestFacade', 'Illuminate\Support\Facades\Request');
}