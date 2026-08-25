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
}
