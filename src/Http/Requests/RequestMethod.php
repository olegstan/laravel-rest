<?php

namespace LaravelRest\Http\Requests;

use Illuminate\Support\Facades\Request as RequestHelper;

/**
 * HTTP-метод текущего запроса с учётом подмены через `_method`.
 */
class RequestMethod
{
    /**
     * Методы, которые только читают данные.
     */
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Метод запроса: `_method` из query, тела формы или JSON важнее реального HTTP-метода.
     *
     * @return string
     */
    public static function resolve()
    {
        $method = RequestHelper::method();

        if (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        if (isset($_POST['_method'])) {
            $method = $_POST['_method'];
        }

        $data = json_decode(file_get_contents('php://input'));

        if (isset($data->_method)) {
            $method = $data->_method;
        }

        if (isset($data->data) && isset($data->data->_method)) {
            $method = $data->data->_method;
        }

        return $method;
    }

    /**
     * Запрос только читает данные и ничего не сохраняет.
     *
     * @return bool
     */
    public static function isRead()
    {
        return in_array(strtoupper((string) self::resolve()), self::READ_METHODS, true);
    }
}
