<?php

namespace LaravelRest\Http\Validators;

use App\Api\V1\Requests\StartRequest;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Class BaseValidator
 *
 * Базовый класс валидации, расширяющий
 * функционал стандартного Validator-а.
 *
 * @package LaravelRest\Http\Validators
 */
class BaseValidator
{
    /**
     * Текущий запрос (StartRequest).
     *
     * @var StartRequest
     */
    public $request;

    /**
     * Экземпляр валидатора.
     *
     * @var \Illuminate\Validation\Validator
     */
    public $validator;

    /**
     * Коллбэк, вызываемый после валидации.
     *
     * @var Closure
     */
    public $after;

    /**
     * Массив нестандартных ошибок,
     * которые нужно добавить к результатам валидации.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Настройки для подготовительной обработки входных данных.
     *
     * @var array
     */
    public $prepare = [
        'number' => []
    ];

    /**
     * BaseValidator constructor.
     *
     * 1. Подготавливает входные данные
     * 2. Trim-ит все поля
     * 3. Устанавливает callback для добавления кастомных ошибок
     * 4. Сохраняет объект $request в $this->request
     * 5. Сливает массив из customRequest() в общий input
     * 6. Запускает валидацию
     *
     * @param  StartRequest  $request
     */
    public function __construct(StartRequest $request)
    {
        $this->prepareInput($request);
        $this->trimInput($request);

        $this->after = function ($validator) {
            foreach ($this->errors as $error) {
                $validator->errors()->add($error['key'], $error['value']);
            }
        };

        $this->request = $request;

        // Данные, которые хотим "добавить" к текущему request
        $this->request->merge($this->customRequest());

        // Запуск валидации
        $this->validate();
    }

    /**
     * Получение текущего ID пользователя.
     *
     * @return int|null
     */
    public function getUserId()
    {
        return Auth::id();
    }

    /**
     * Добавляет кастомную ошибку в массив $this->errors.
     *
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function addError($key, $value)
    {
        $this->errors[] = [
            'key'   => $key,
            'value' => $value,
        ];
    }

    /**
     * Пустая (или не реализованная) функция.
     * @param  string  $text
     * @return void
     */
    public function setText($text)
    {
        // Зарезервировано под будущую реализацию.
    }

    /**
     * Возвращает массив ключей, которые нужно обрабатывать как "number".
     *
     * @param  StartRequest  $request
     * @return array
     */
    public function getNumber($request)
    {
        return $this->prepare['number'];
    }

    /**
     * Подготовка input-данных:
     * - Для ключей, перечисленных в $this->prepare['number'],
     *   убираем пробелы и пустые строки превращаем в 0.
     *
     * @param  StartRequest  $request
     * @return void
     */
    public function prepareInput(StartRequest $request)
    {
        $input = $request->all();
        $input = is_array($input) ? $input : [];

        foreach ($this->getNumber($request) as $key) {
            $parts = explode('.', $key);
            $count = count($parts);

            // Простой случай: один ключ
            if ($count === 1) {
                if (isset($input[$key])) {
                    $input[$key] = $this->cleanNumber($input[$key]);
                }
            }

            // Два уровня вложенности: key1.key2
            elseif ($count === 2) {
                if (isset($input[$parts[0]][$parts[1]])) {
                    $input[$parts[0]][$parts[1]] =
                        $this->cleanNumber($input[$parts[0]][$parts[1]]);
                }
            }

            // Случай >2 уровней, включая обработку '*'
            else {
                $link = &$input;

                // Проверяем, что данные на верхнем уровне вообще есть.
                if (isset($input[$parts[0]])) {
                    foreach ($parts as $k => $part) {
                        // Если встретили '*', значит обрабатываем все подмассивы
                        if ($part === '*') {
                            // Если мы внутри массива, пробегаемся по нему
                            if (is_array($link)) {
                                // Смотрим, не достигли ли мы предпоследнего ключа
                                $isPreLast = ($k + 2) === $count;
                                $nextPart  = $isPreLast ? $parts[$k + 1] : null;

                                // Проставим значения для вложенных элементов
                                foreach ($link as &$subItem) {
                                    if ($isPreLast && is_array($subItem) && $nextPart !== null) {
                                        $subItem[$nextPart] = $this->cleanNumber($subItem[$nextPart] ?? 0);
                                    }
                                }
                            }
                            // Прерываемся, т. к. обработали '*'
                            break;
                        } else {
                            // Идём внутрь массива, если текущий ключ существует
                            if (isset($link[$part])) {
                                $link = &$link[$part];
                            }
                        }
                    }
                }
            }
        }

        $request->merge($input);
    }

    /**
     * Пустая (или не реализованная) функция.
     * Зарезервирована под будущее использование.
     *
     * @return void
     */
    public function getByLink()
    {
        // Пусто
    }

    /**
     * Trim всех строковых значений во входном массиве,
     * пустые строки заменяются на null.
     *
     * @param  StartRequest  $request
     * @return void
     */
    public function trimInput(StartRequest $request)
    {
        $input = $request->all();
        $input = is_array($input) ? $input : [];

        array_walk_recursive($input, function (&$item) {
            // Если строка, trim.
            if (is_string($item)) {
                $item = trim($item);
                if ($item === '') {
                    $item = null;
                }
            }
            return $item;
        });

        $request->merge($input);
    }

    /**
     * Шанс модифицировать входные данные перед самой валидацией.
     *
     * @param  array  $data
     * @return array
     */
    public function setBefore($data)
    {
        return $data;
    }

    /**
     * Устанавливает "финальный" callback после валидации,
     * если необходимо переопределить логику on-the-fly.
     *
     * @param  callable  $callable
     * @return void
     */
    public function setAfter($callable)
    {
        $this->after = $callable;
    }

    /**
     * Магический метод: перенаправляет вызовы
     * к соответствующим методам текущего $this->validator.
     *
     * @param  string  $name
     * @param  array   $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->validator, $name], $arguments);
    }

    /**
     * Переопределяется в потомках при желании добавить
     * логику, исполняемую после "стандартной" валидации.
     *
     * @return \Closure
     */
    public function afterValidate()
    {
        return function ($validator) {
            // пустая реализация
        };
    }

    /**
     * Позволяет добавить/переопределить набор полей для запроса.
     *
     * @return array
     */
    public function customRequest(): array
    {
        return [];
    }

    /**
     * Правила валидации (задаётся в потомках).
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Кастомные сообщения (задаётся в потомках).
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Единая ошибка (если нужна) — переопределяется в потомках.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '';
    }

    /**
     * Запуск валидации:
     *  1. Перед валидацией модифицируются данные (setBefore).
     *  2. Создаётся валидатор.
     *  3. Навешивается callback after.
     *
     * @return void
     */
    public function validate()
    {
        $data = $this->setBefore($this->request->all());

        $this->validator = Validator::make($data, $this->rules(), $this->messages());
        $this->validator->after($this->after);
    }

    /**
     * Вспомогательный метод для "очистки" числовых полей:
     * - Удаляет пробелы
     * - Если результат пустой — подставляет 0
     *
     * @param  mixed  $value
     * @return int|float|string
     */
    protected function cleanNumber($value)
    {
        if (!is_scalar($value)) {
            // Если значение не скалярное, оставим как есть
            return $value;
        }
        $clean = str_replace(' ', '', (string) $value);

        return ($clean === '') ? 0 : $clean;
    }
}