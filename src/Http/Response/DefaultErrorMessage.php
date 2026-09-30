<?php

namespace LaravelRest\Http\Response;

use LaravelRest\Http\Requests\RequestMethod;

/**
 * Текст ошибки по умолчанию — когда вызывающий код не передал своё сообщение.
 *
 * Текст подбирается по HTTP-статусу ответа: «не удалось сохранить» уместно
 * только для 422 на запросе, который что-то сохраняет.
 */
class DefaultErrorMessage
{
    public const FALLBACK = 'Ошибка';
    public const SAVE_FAILED = 'Не удалось сохранить данные';
    public const READ_FAILED = 'Не удалось получить данные';

    private const UNPROCESSABLE = 422;

    /**
     * Тексты для статусов, смысл которых не зависит от метода запроса.
     */
    private const BY_STATUS = [
        401 => 'Требуется авторизация',
        403 => 'Действие недоступно',
        404 => 'Не найдено',
    ];

    /**
     * @param int|string $code HTTP-статус ответа
     * @return string
     */
    public static function resolve($code)
    {
        $code = (int) $code;

        if (isset(self::BY_STATUS[$code])) {
            return self::BY_STATUS[$code];
        }

        if ($code !== self::UNPROCESSABLE) {
            return self::FALLBACK;
        }

        return RequestMethod::isRead() ? self::READ_FAILED : self::SAVE_FAILED;
    }
}
