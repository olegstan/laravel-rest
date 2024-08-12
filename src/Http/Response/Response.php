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
     * @var
     */
    protected static $transformer;

    /**
     * @var string
     */
    protected $result = 'success';

    /**
     * @var array
     */
    protected $meta = [];

    /**
     * @var string
     */
    protected $type;

    /**
     * @var
     */
    protected $transformData;

    /**
     * Response constructor.
     * @param $content
     * @param int $status
     * @param array $headers
     * @param null $transformer
     * @param string $type
     */
    public function __construct($content, $status = 200, $headers = [], $transformer = null, $type = 'json')
    {
        parent::__construct($content, $status, $headers);
        static::$transformer = $transformer;
        $this->type = $type;
        $this->transformData = $content;
    }

    /**
     * @return IlluminateResponse
     */
    public function send()
    {
        $this->morph();
        return parent::send();
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
     * @return $this
     */
    public function morph()
    {
        switch ($this->type)
        {
            case 'paginator':
                if (static::$transformer)
                {
                    $this->transformData->getCollection()->transform(static function ($value)
                    {
                        return static::$transformer->transform($value);
                    });
                }else{
                    $this->transformData->transform(static function ($value)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($value, BaseTransformer::getPrefix($value)));

                        return (new $transformerModel)->transform($value);
                    });
                }
                break;
            case 'collect':
                if (static::$transformer)
                {
                    $this->transformData->transform(static function ($value)
                    {
                        return static::$transformer->transform($value);
                    });
                }else{
                    $this->transformData->transform(static function ($value)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($value, BaseTransformer::getPrefix($value)));

                        return (new $transformerModel)->transform($value);
                    });
                }
                break;
            case 'collectOptimize':

                if (static::$transformer)
                {
                    $this->transformData->transform(static function ($value)
                    {
                        return static::$transformer->transform($value);
                    });


                }else{
                    $this->transformData->transform(static function ($value)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($value, BaseTransformer::getPrefix($value)));

                        return (new $transformerModel)->transform($value);
                    });
                }
                break;
            case 'item':
                if (static::$transformer)
                {
                    if ($this->transformData)
                    {
                        $this->transformData = static::$transformer->transform($this->transformData);
                    } else {
                        $this->transformData = [];
                    }
                }else{
                    if ($this->transformData)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($this->transformData, BaseTransformer::getPrefix($this->transformData)));

                        $this->transformData = (new $transformerModel)->transform($this->transformData);
                    } else
                    {
                        $this->transformData = [];
                    }
                }
                break;
        }
        $arr['meta'] = [];
        $arr['result'] = $this->result;
        $arr['data'] = is_array($this->transformData) ? $this->transformData : $this->transformData->toArray();
        $this->setMeta($arr);
        $this->content = json_encode($arr);
        return $this;
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
}
