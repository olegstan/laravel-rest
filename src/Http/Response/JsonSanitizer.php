<?php

namespace LaravelRest\Http\Response;

/**
 * Class JsonSanitizer
 * @package LaravelRest\Http\Response
 */
class JsonSanitizer
{
    /**
     * Рекурсивно заменяет NaN/INF на 0, чтобы сериализация ответа не падала.
     *
     * Скаляры обрабатываются прямо в цикле, а рекурсия идёт только во вложенные
     * массивы: на больших ответах листьев на порядок больше, чем узлов, и вызов
     * функции на каждый лист обходится дороже самой проверки.
     *
     * @param mixed $data
     * @return void
     */
    public static function sanitize(&$data): void
    {
        if (!is_array($data)) {
            if (is_float($data) && !is_finite($data)) {
                $data = 0;
            }

            return;
        }

        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sanitize($value);
            } elseif (is_float($value) && !is_finite($value)) {
                $value = 0;
            }
        }

        unset($value);
    }

    /**
     * Ищет строки с некорректным UTF-8 и возвращает пути до них.
     *
     * Нужен только для диагностики упавшего json_encode, поэтому вызывается
     * лишь на ветке ошибки. Обход устроен как в sanitize(): накопитель идёт
     * по ссылке, а листья проверяются прямо в цикле — на ответе в мегабайты
     * листьев на порядок больше, чем узлов, и вызов функции на каждый из них
     * обходится дороже самой проверки.
     *
     * @param mixed $data
     * @param int   $limit сколько путей вернуть максимум
     *
     * @return array<int, string> пути вида "data.12.name"
     */
    public static function findInvalidUtf8Paths($data, int $limit = 20): array
    {
        $paths = [];

        if (is_string($data)) {
            return self::isValidUtf8($data) ? [] : [''];
        }

        if (is_array($data)) {
            self::collectInvalidUtf8Paths($data, '', $limit, $paths);
        }

        return $paths;
    }

    /**
     * Рекурсивный обход для findInvalidUtf8Paths().
     *
     * @param array  $data
     * @param string $path   путь до текущего узла
     * @param int    $limit  предел числа найденных путей
     * @param array  $paths  накопитель найденных путей
     *
     * @return void
     */
    private static function collectInvalidUtf8Paths(array $data, string $path, int $limit, array &$paths): void
    {
        $prefix = $path === '' ? '' : $path . '.';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                self::collectInvalidUtf8Paths($value, $prefix . $key, $limit, $paths);
            } elseif (is_string($value) && !self::isValidUtf8($value)) {
                $paths[] = $prefix . $key;
            }

            if (count($paths) >= $limit) {
                return;
            }
        }
    }

    /**
     * Проверяет строку на корректность UTF-8.
     *
     * @param string $value
     * @return bool
     */
    private static function isValidUtf8(string $value): bool
    {
        return $value === '' || preg_match('//u', $value) === 1;
    }
}
