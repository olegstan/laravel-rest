<?php

namespace LaravelRest\Http\Response;

use App\Api\V1\Transformers\Base\CreditLogTransformer;
use App\Models\CreditLog;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Collection;
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
     * @var Collection|null
     */
    public static $logs = null;

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
     * @var
     */
    protected $transformLogsData;
    /**
     * @var
     */
    protected $transformLogsIds = [];

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
                    $this->transformData->getCollection()->transform(function ($value)
                    {
                        return static::$transformer->transform($value);
                    });
                }else{
                    $this->transformData->transform(function ($value)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($value, 'App\Models\\'));

                        return (new $transformerModel)->transform($value);
                    });
                }
                break;
            case 'collect':
                if (static::$transformer)
                {
                    $this->transformData->transform(function ($value)
                    {
                        return static::$transformer->transform($value);
                    });
                }else{
                    $this->transformData->transform(function ($value)
                    {
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($value, 'App\Models\\'));

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
                        $transformerModel = BaseTransformer::getTransformClass(BaseTransformer::getClass($this->transformData, 'App\Models\\'));

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

        if(CreditLog::$isUpdated)
        {
            foreach (CreditLog::$userIds as $userId)
            {
                CreditLog::clearTransferLogs($userId);
                CreditLog::recalculateAccountsBalance($userId);
            }
        }

        $arr['logs'] = $this->transformLogs();
        $this->setMeta($arr);
        $this->content = json_encode($arr);
        return $this;
    }

    /**
     * @param $log
     */
    public static function addLog($log)
    {
        if (is_null(static::$logs))
        {
            static::$logs = new Collection();
        }

        if($log)
        {
            static::$logs->push($log);
        }
    }

    /**
     * @return array|Collection
     */
    public function transformLogs()
    {
        if (is_null(static::$logs))
        {
            return [];
        }

        static::$logs->transform(function ($value)
        {
            if(!in_array($value->id, $this->transformLogsIds))
            {
                $this->transformLogsIds[] = $value->id;
                $this->transformLogsData[] = (new CreditLogTransformer())->transform($value);
            }

            return $value;
        });

        return $this->transformLogsData;
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
