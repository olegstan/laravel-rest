<?php

namespace LaravelRest\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class StartRequest
 *
 * Custom request-класс, расширяющий функциональность
 * стандартного Illuminate\Http\Request.
 *
 * @package LaravelRest\Http\Requests
 */
class StartRequest extends Request implements RequestInterface
{
    /**
     * @var string|null
     */
    public $routeController;

    /**
     * @var string|null
     */
    public $routeMethod;

    /**
     * Ключи, которые "принадлежат" исходному Request.
     *
     * @var string[]
     */
    public $officialKeys = [
        'arguments',
        'query',
        'data',
        'session',
    ];

    /**
     * Сюда складываем "кастомные" данные,
     * т.е. всё, что не относится к officialKeys.
     *
     * @var ParameterBag
     */
    public $customData;

    /**
     * Инициализация объекта.
     * В частности, очистка входных данных и преобразование
     * строк 'true'/'false'/' ' и т.д.
     *
     * @return void
     */
    public function init()
    {
        $input = [];

        if (is_array($this->get('data'))) {
            $input = $this->get('data');

            array_walk_recursive($input, function (&$item) {
                if (is_string($item)) {
                    $trimmed = trim($item);

                    // Если после trim строка пустая, делаем null
                    if ($trimmed === '') {
                        $item = null;
                    } elseif ($trimmed === 'true') {
                        $item = true;
                    } elseif ($trimmed === 'false') {
                        $item = false;
                    } else {
                        // Если не пустая, записываем обратно в $item
                        $item = $trimmed;
                    }
                }
            });
        }

        $this->setCustomData($input);
    }

    /**
     * Устанавливаем кастомные данные.
     *
     * @param array $input
     * @return void
     */
    public function setCustomData(array $input)
    {
        $this->customData = new ParameterBag($input);
    }

    /**
     * Пробегаемся по всем входным данным и trim'им строки.
     *
     * @return void
     */
    public function trimInput()
    {
        $input = $this->all();

        array_walk_recursive($input, function (&$item) {
            if (is_string($item)) {
                $item = trim($item);
            }
        });

        $this->replace($input);
    }

    /**
     * Преобразование пустой строки в null
     * для определённого ключа или, при отсутствии $key,
     * для всех полей входного массива.
     *
     * @param string|null $key
     * @return void
     */
    public function transformToNull($key = null)
    {
        $input = $this->all();

        // Если $key не задан, преобразуем все пустые строки к null
        if (is_null($key)) {
            array_walk_recursive($input, function (&$val) {
                if ($val === '') {
                    $val = null;
                }
            });
        } else {
            // Если передан конкретный ключ
            if (array_key_exists($key, $input) && $input[$key] === '') {
                $input[$key] = null;
            }
        }

        $this->setCustomData($input);
    }

    /**
     * Преобразуем 'true'/'false' к boolean в arguments.
     *
     * @return array
     */
    public function getArguments()
    {
        $arr = $this->get('arguments', []);
        foreach ($arr as &$val) {
            if ($val === 'false' || $val === 'true') {
                $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $arr;
    }

    /**
     * Получаем query.
     *
     * @return array
     */
    public function getQuery()
    {
        return $this->get('query', []);
    }

    /**
     * Рекурсивно декодируем url, учитывая особенности GET-запроса.
     *
     * @param mixed  $item
     * @param string $method
     * @return mixed
     */
    public function recursiveUrlDecode($item, $method)
    {
        if (is_array($item)) {
            return array_map(function ($value) use ($method) {
                return $this->recursiveUrlDecode($value, $method);
            }, $item);
        }

        if (is_string($item)) {
            if ($method === 'GET') {
                // Чтобы + не превратилось в пробел
                $item = str_replace('+', '%2B', $item);
                return urldecode($item);
            }

            return $item;
        }

        return $item;
    }

    /**
     * Переопределение метода get(), чтобы для ключей из $officialKeys
     * использовать родительский Request, а для всех остальных — customData.
     *
     * @param string|null $key
     * @param mixed       $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        // Если запрашиваем "официальные" ключи, берём из родительского Request
        if (in_array($key, $this->officialKeys, true)) {
            return parent::get($key, $default);
        }

        // Иначе смотрим в customData
        if ($this->customData) {
            $value = $this->customData->get($key, null);
            return $value !== null ? $value : $default;
        }

        return $default;
    }

    /**
     * Аналогично Laravel-методу input(), возвращает
     * значение ключа с учётом "дот-нотации".
     *
     * @param string|null $key
     * @param mixed       $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        // Если ключ не задан, вернём все данные (аналог all())
        if ($key === null) {
            return $this->get(null, $default);
        }

        // Если ключ содержит точки, ищем вложенные элементы в массиве
        if (str_contains($key, '.')) {
            $keys  = explode('.', $key);
            // Первый уровень забираем через наш кастомный get()
            $value = $this->get(array_shift($keys), $default);

            // Проходим дальше по остальным уровням
            foreach ($keys as $subKey) {
                if (is_array($value) && array_key_exists($subKey, $value)) {
                    $value = $value[$subKey];
                } else {
                    return $default;
                }
            }

            return $value;
        }

        // Обычное поведение
        return $this->get($key, $default);
    }

    /**
     * Проверка наличия ключа либо в официальных ключах,
     * либо в customData.
     *
     * @param string|array $key
     * @return bool
     */
    public function has($key)
    {
        if (in_array($key, $this->officialKeys, true)) {
            return parent::has($key);
        }

        return $this->customData && $this->customData->has($key);
    }

    /**
     * Возвращает все кастомные данные, либо только перечисленные ключи.
     *
     * @param array|string|null $keys
     * @return array
     */
    public function all($keys = null)
    {
        return $this->customData
            ? $this->customData->all($keys)
            : [];
    }

    /**
     * Сливает новый массив с текущими customData.
     *
     * @param array $params
     * @return bool
     */
    public function merge(array $params)
    {
        foreach ($params as $key => $value) {
            $this->customData->set($key, $value);
        }
        return true;
    }

    /**
     * Возвращает только перечисленные ключи из всех данных.
     *
     * @param array $keys
     * @return array
     */
    public function only($keys)
    {
        return Arr::only($this->all(), $keys);
    }

    /**
     * Геттер для $routeController.
     *
     * @return string|null
     */
    public function getRouteController()
    {
        return $this->routeController;
    }

    /**
     * Геттер для $routeMethod.
     *
     * @return string|null
     */
    public function getRouteMethod()
    {
        return $this->routeMethod;
    }

    /**
     * Получение токена из запроса (пример).
     *
     * @return string|null
     */
    public function getApiToken()
    {
        return $this->request->get('api_token');
    }

    /**
     * Получаем заголовок из объекта $this->request.
     *
     * @param string $key
     * @return array|string|null
     */
    public function getHeader($key)
    {
        return $this->request->getHeader($key);
    }
}