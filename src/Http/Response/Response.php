<?php

namespace LaravelRest\Http\Response;

use Illuminate\Http\Response as IlluminateResponse;
use LaravelRest\Http\Transformers\BaseTransformer;


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
     * @param string               $type        Тип трансформации (json, collect, paginator...)
     */
    public function __construct(
        $content,
        int $status = 200,
        array $headers = [],
        ?BaseTransformer $transformer = null,
        string $type = 'json'
    ) {
        // Передаём в родительский конструктор (Response) пустую строку,
        // чтобы сразу не записывать "сырые" данные в $this->content
        parent::__construct('', $status, $headers);

        $this->transformer = $transformer;
        $this->type        = $type;
        $this->rawContent  = $content;

        // Сразу выставим (трансформируем) контент в нужный формат
        // через наш метод setContent, чтобы всё было централизовано
        $this->setContent($this->rawContent);
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
     * @param $content
     * @return $this|Response
     */
    public function setContent($content): Response
    {
        // Запомним новые «сырые» данные
        $this->rawContent = $content;

        // Трансформируем (если нужно), результат снова остаётся в $this->rawContent
        $this->prepareTransformData();

        // Собираем итоговый массив для JSON
        $payload = [
            'meta'   => [],            // будет заполнено ниже
            'result' => $this->result,
            'data'   => $this->formatData($this->rawContent),
        ];

        // Добавляем метаданные (если были)
        $this->setMeta($payload);

        // Вызываем родительский setContent, чтобы не ломать логику Response
        parent::setContent(json_encode($payload));

        return $this;
    }

    /**
     * @return void
     */
    public function prepareTransformData()
    {
        // Используем фабрику, чтобы получить нужную стратегию по типу
        $strategy = TransformationStrategyFactory::make($this->type);

        // Применяем стратегию к текущим данным
        $this->transformData = $strategy->transform($this->transformData, $this->transformer);
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
     * @param $arr
     */
    protected function setMeta(&$arr)
    {
        foreach ($this->meta as $key => &$val)
        {
            $arr['meta'][$key] = $val;
        }
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
}
