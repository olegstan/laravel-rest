<?php

namespace LaravelRest\Http\Response;

use Illuminate\Http\Response as IlluminateResponse;
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
     * @param mixed                $content     Исходные (сырые) данные
     * @param int                  $status      HTTP-статус
     * @param array                $headers     HTTP-заголовки
     * @param BaseTransformer|null $transformer Пользовательский трансформер
     * @param               $type        Тип трансформации (json, collect, paginator...)
     */
    public function __construct(
        $content,
        int $status = 200,
        array $headers = [],
        ?BaseTransformer $transformer = null,
        $type = 'json'//msgpack
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

        if ($type === 'msgpack') {
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
        $this->prepareTransformData();
        $payload = [
            'meta'   => $this->meta,
            'result' => $this->result,
            'data'   => $this->formatData($this->rawContent),
        ];

        return $payload;
    }

    public function convertResponse($payload)
    {
        if ($this->type === 'msgpack') {
            return $this->encodeMessagePack($payload);
        }

        return json_encode($payload);
    }

    /**
     * @param array $data
     * @return mixed
     */
    protected function encodeMessagePack(array $data)
    {
        $packer = new Packer();
        return $packer->pack($data);
    }

    /**
     * @return void
     */
    public function prepareTransformData()
    {
        // Используем фабрику, чтобы получить нужную стратегию по типу
        $strategy = TransformationStrategyFactory::make($this->type);

        if($strategy)
        {
            // Применяем стратегию к текущим данным
            $this->rawContent  = $strategy->transform($this->rawContent, $this->transformer);
        }
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
    public function getContent()
    {
        return $this->convertResponse($this->buildPayload());
    }

    /**
     * @return Response
     */
    public function send()
    {
        parent::setContent($this->convertResponse($this->buildPayload()));
        return parent::send();
    }
}