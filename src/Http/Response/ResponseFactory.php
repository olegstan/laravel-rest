<?php

namespace LaravelRest\Http\Response;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Class ResponseFactory
 * @package LaravelRest\Http\Response
 */
class ResponseFactory
{
    /**
     * @param Collection $collection
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function collectOptimize(Collection $collection, $transformer = null, $headers = [])
    {
        $responseClass = config('rest.response');
        return new $responseClass($collection, 200, $headers, $transformer, 'collectOptimize');
    }
    /**
     * @param Collection $collection
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function collection(Collection $collection, $transformer = null, $headers = [])
    {
        $responseClass = config('rest.response');
        return new $responseClass($collection, 200, $headers, $transformer, 'collect');
    }

    /**
     * @param Paginator $paginator
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function paginator(Paginator $paginator, $transformer = null, $headers = [])
    {
        $responseClass = config('rest.response');
        return new $responseClass($paginator, 200, $headers, $transformer, 'paginator');
    }

    /**
     * @param $item
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function item($item, $transformer = null, $headers = [])
    {
        $responseClass = config('rest.response');
        return new $responseClass($item, 200, $headers, $transformer, 'item');
    }

    /**
     * @param array $data
     * @param array $headers
     * @return Response
     */
    public function json(array $data, $headers = [])
    {
        $responseClass = config('rest.response');
		return new $responseClass($data, 200, $headers);
	}

    /**
     * @param string $text
     * @return Response
     */
    public function success($text = '')
    {
        $responseClass = config('rest.response');
		$response = new $responseClass([]);
		if(!empty($text)){
			$response->addMeta('text', $text);
		}
        $response->morph();
		return $response;
	}

    /**
     * @param null $errors
     * @param int $code
     * @param string $context
     * @param array $data
     * @return Response
     */
    public function error($errors = null, $code = 422, $context = '', $data = [])
    {
        $responseClass = config('rest.response');
        $response = new $responseClass([]);
		$response->error($code);
		if($errors){
			if(gettype($errors) == 'array' || gettype($errors) == 'object'){
				$response->addMeta('errors', $errors);
				$response->addMeta('message', 'Не удалось сохранить данные');
			}elseif(gettype($errors) == 'string'){
				$response->addMeta('text', $errors);
			}
		}
        $response->morph();

		return $response;
	}
}
