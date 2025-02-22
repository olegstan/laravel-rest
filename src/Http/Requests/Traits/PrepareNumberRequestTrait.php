<?php

namespace LaravelRest\Http\Requests\Traits;

/**
 * Trait RestRequestTrait
 *
 * @mixin \LaravelRest\Http\Requests\DefaultRequest
 */
trait PrepareNumberRequestTrait
{
    /**
     * @var array
     */
    public $prepareNumberKeys = [];

    /**
     *
     */
    public function trimInput()
    {
        //получим из data и переназначим реквест чтобы не нужно было писать
        //data.user_id, data.sum, реквест съедает это
        $input = $this->input('data', []);

        if(is_array($input))
        {
            unset($input['timestamp'], $input['unique_hash']);

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

        $this->replace($input);
    }

    /**
     * Подготовка input-данных:
     * - Для ключей, перечисленных в $this->prepare['number'],
     *   убираем пробелы и пустые строки превращаем в 0.
     *
     * @param  $request
     * @return void
     */
    public function prepareInputNumbers()
    {
        $input = $this->all();
        $input = is_array($input) ? $input : [];

        foreach ($this->prepareNumberKeys as $key) {
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

        $this->merge($input);
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