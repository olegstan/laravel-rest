<?php
namespace LaravelRest\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class StartRequest
 * @package App\Api\V1\Requests
 */
class StartRequest extends Request implements RequestInterface
{
    /**
     * @var string|null
     */
    public $routeController;
    /**
     * @var string|null
     */
    public $routeMethod;

    /**
     * @param array                $query      The GET parameters
     * @param array                $request    The POST parameters
     * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array                $cookies    The COOKIE parameters
     * @param array                $files      The FILES parameters
     * @param array                $server     The SERVER parameters
     * @param string|resource|null $content    The raw body data
     */
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);

        $input = [];
        if(is_array($this->get('data')))
        {
            $input = $this->get('data');
            array_walk_recursive($input, function(&$item)
            {
                if(is_array($item)){

                }else{
                    $item = trim($item);

                    if($item === '' || $item === [])
                    {
                        $item = null;
                    }
                }
                return $item;
            });
        }

        $this->setCustomData($input);
    }

    /**
     * @var array
     */
    public $officialKeys = [
		'arguments',
		'query',
		'data',
		'session',
	];
    /**
     * @var
     */
    public $customData;

    /**
     * @param $input
     */
	public function setCustomData($input)
    {
		$this->customData = new ParameterBag($input);
	}

    /**
     *
     */
    public function trimInput()
    {
        $input = $this->all();
        $input = is_array($input) ? $input : [];

        array_walk_recursive($input, function(&$item)
        {
            $item = trim($item);
        });

        $this->replace($input);
    }

    /**
     * @param bool $key
     */
	public function transformToNull($key = false)
    {
		$input = $this->all();
		foreach($input as $k => &$val)
		{
			if(!$key){
				
			}else{
				if($k === $key){
					if($val === '')
					{
						$val = null;
					}
				}
			}
		}
		
		$this->setCustomData($input);
	}

    /**
     * @return array|mixed
     */
    public function getArguments()
    {
		$arr = $this->get('arguments', []);
		foreach($arr as &$val)
		{
			if($val == 'false' || $val == 'true')
				$val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
		}
		return $arr;
	}

    /**
     * @return array|mixed
     */
    public function getQuery()
    {
		return $this->get('query', []);
	}

    /**
     * @param string $key
     * @param array $default
     * @return array|mixed
     */
    public function get($key, $default = null)
    {
		if(in_array($key, $this->officialKeys))
		{
			return parent::get($key, $default);
		}
		
		if($this->customData)
		{
            return !is_null($this->customData->get($key, null)) ? $this->customData->get($key) : $default;
        }

		return $default;
	}

    /**
     * @param string $key
     * @param array $default
     * @return array|mixed
     */
    public function input($key = null, $default = null)
    {
		return $this->get($key, $default);
	}

    /**
     * @param array|string $key
     * @return bool
     */
    public function has($key)
    {
		if(in_array($key, $this->officialKeys))
			return parent::has($key);

		
		if($this->customData)
			return $this->customData->has($key);
		
		return false;
	}

    /**
     * @param null $keys
     * @return array
     */
    public function all($keys = null)
    {
		if($this->customData)
			return $this->customData->all($keys);
		
		return [];
	}

    /**
     * @param array $params
     * @return bool
     */
    public function merge(array $params)
    {
        foreach ($params as $key => $value) {
            $this->customData->set($key, $value);
        }

        return true;
    }

    /**
     * @param $keys
     * @return mixed
     */
	public function only($keys)
    {
		return Arr::only($this->all(), $keys);
	}

    /**
     * @return string
     */
	public function getRouteController()
    {
        return $this->routeController;
    }

    /**
     * @return string
     */
	public function getRouteMethod()
    {
        return $this->routeMethod;
    }

    /**
     * @return array|string
     */
    public function getApiToken()
    {
        return $this->request->get('api_token');
    }
}