<?php

namespace LaravelRest\Http\Response;

use Illuminate\Http\Response as IlluminateResponse;
use LaravelRest\Http\Services\Msgpack\Extensions\CarbonExtension;
use LaravelRest\Http\Transformers\BaseTransformer;
use MessagePack\Packer;

/**
 * Class Response
 * @package LaravelRest\Http\Response
 */
class Response extends IlluminateResponse
{
    /**
     * Признак: success/error
     *
     * @var string
     */
    protected $result = 'success';

    /**
     * Массив метаданных, которые добавляются в итоговый ответ
     *
     * @var array
     */
    protected $meta = [];

    /**
     * @var string
     */
    protected $type;
    /**
     * @var string
     */
    protected $format;

    /**
     * Сырой контент, который надо трансформировать.
     * (Вместо прежнего transformData)
     *
     * @var mixed
     */
    protected $rawContent;

    /**
     * Трансформер, если передаётся извне
     *
     * @var BaseTransformer|null
     */
    protected $transformer;
    /**
     * @var null
     */
    protected $payload = null;
    /**
     * @var bool
     */
    protected $isPayloadBuild = false;

    /**
     * Обработчик неудачной сериализации ответа: function (string $error, array $paths).
     *
     * Задаётся приложением (например, в сервис-провайдере) — пакет не тянет
     * за собой ничей логгер и по умолчанию молчит.
     *
     * @var callable|null
     */
    public static $encodeFailureHandler = null;

    /**
     * @param mixed                $content     Исходные (сырые) данные
     * @param int                  $status      HTTP-статус
     * @param array                $headers     HTTP-заголовки
     * @param BaseTransformer|null $transformer Пользовательский трансформер
     * @param                      $type
     * @param                      $format
     */
    public function __construct(
        $content,
        int $status = 200,
        array $headers = [],
        ?BaseTransformer $transformer = null,
        $type = null,
        $format = 'json'//msgpack
    ) {
        // Передаём в родительский конструктор (Response) пустую строку,
        // чтобы сразу не записывать "сырые" данные в $this->content
        parent::__construct('', $status, $headers);

        if ($status >= 400 && $status <= 599) {
            $this->result = 'error';
        } else {
            $this->result = 'success'; // Опционально: если нужно обозначить успешный статус
        }

        $this->transformer = $transformer;
        $this->type        = $type;
        $this->rawContent  = $content;
        $this->format  = $format;

        if ($format === 'msgpack') {
            $this->headers->set('Content-Type', 'application/msgpack');
        } else {
            $this->headers->set('Content-Type', 'application/json');
        }
    }

    /**
     *
     */
    public function success()
    {
        $this->result = 'success';
    }

    /**
     * @param $code
     */
    public function error($code)
    {
        $this->setStatusCode($code);
        $this->result = 'error';
    }

    /**
     * @return array
     */
    protected function buildPayload()
    {
        if(!$this->isPayloadBuild)
        {
            $this->isPayloadBuild = true;

            $transfomedData = $this->prepareTransformData();

            $this->payload = [
                'meta'   => $this->meta,
                'result' => $this->result,
                'data'   => $this->formatData($transfomedData),
            ];
        }

        return $this->payload;
    }

    /**
     * Единая точка сериализации ответа.
     *
     * Перед кодированием payload чистится от NaN/INF: json_encode на таких
     * значениях падает, а msgpack молча отдаёт наружу нечисловые float.
     *
     * @param array|mixed $payload
     * @return false|string
     */
    public function convertResponse($payload)
    {
        JsonSanitizer::sanitize($payload);

        if ($this->format === 'msgpack') {
            return $this->encodeMessagePack($payload);
        }

        return $this->encodeJson($payload);
    }

    /**
     * Кодирует payload в JSON, не отдавая наружу пустое тело.
     *
     * json_encode на битой UTF-8 строке (её легко привозят импорты брокеров)
     * возвращает false, Response::setContent(false) приводит его к пустой
     * строке, и клиент получает 200 с Content-Type: application/json и нулевым
     * телом — "Unexpected end of JSON input". Поэтому при ошибке кодируем
     * повторно с заменой некорректных байт и пишем в лог пути до полей,
     * из-за которых сломалась сериализация.
     *
     * @param mixed $payload
     * @return false|string
     */
    protected function encodeJson($payload)
    {
        $encoded = json_encode($payload);

        if ($encoded !== false) {
            return $encoded;
        }

        $error = json_last_error_msg();
        $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $this->reportEncodeFailure($error, JsonSanitizer::findInvalidUtf8Paths($payload));

        return $encoded;
    }

    /**
     * Отдаёт информацию о неудачной сериализации наружу, приложению.
     *
     * Пакет не знает, чем логирует хост-приложение, поэтому сам ничего не
     * пишет: если обработчик не задан, ошибка просто не репортится.
     *
     * @param string $error сообщение json_last_error_msg()
     * @param array  $paths пути до полей с некорректным UTF-8
     *
     * @return void
     */
    protected function reportEncodeFailure(string $error, array $paths): void
    {
        $handler = static::$encodeFailureHandler;

        if (!is_callable($handler)) {
            return;
        }

        // Ответ важнее репорта: падение обработчика не должно превращать
        // испорченный ответ в 500.
        try {
            $handler($error, $paths);
        } catch (\Throwable $e) {
            //
        }
    }

    /**
     * @param array $data
     * @return mixed
     */
    protected function encodeMessagePack(array $data)
    {
        /**
         * @var Packer $packer
         */
        $packer = new Packer();
        $packer = $packer->extendWith(new CarbonExtension(0));

        return $packer->pack($data);
    }

    /**
     * @return array|\Illuminate\Pagination\LengthAwarePaginator|mixed
     */
    public function prepareTransformData()
    {
        // Используем фабрику, чтобы получить нужную стратегию по типу
        $strategy = TransformationStrategyFactory::make($this->type);

        if($strategy)
        {
            // Применяем стратегию к текущим данным
            return $strategy->transform($this->rawContent, $this->transformer);
        }

        return $this->rawContent;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addMeta($key, $value)
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Приводит результат трансформации к массиву (если это коллекция или модель)
     * либо оставляет как есть, если это уже массив или скаляр.
     *
     * @param mixed $data
     * @return array|mixed
     */
    protected function formatData($data)
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        // Если это не массив и не объект со своим toArray(),
        // возвращаем как есть (могут быть строки, числа и т.д.)
        return $data;
    }

    /**
     * @return false|string
     */
    public function getContent(): string|false
    {
        return $this->convertResponse($this->buildPayload());
    }

    /**
     * @param bool $flush Сбросить буферы вывода (параметр появился в Symfony 7).
     *
     * @return static
     */
    public function send(bool $flush = true): static
    {
        parent::setContent($this->convertResponse($this->buildPayload()));
        return parent::send($flush);
    }
}