<?php

namespace LaravelRest\Http\Response;

use App;
use Auth;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Session;

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
    public function collection(Collection $collection, $transformer = null, $headers = [])
    {
        return new Response($collection, 200, $headers, $transformer, 'collect');
    }

    /**
     * @param Paginator $paginator
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function paginator(Paginator $paginator, $transformer = null, $headers = [])
    {
        return new Response($paginator, 200, $headers, $transformer, 'paginator');
    }

    /**
     * @param $item
     * @param $transformer
     * @param array $headers
     * @return Response
     */
    public function item($item, $transformer = null, $headers = [])
    {
        return new Response($item, 200, $headers, $transformer, 'item');
    }

    /**
     * @param array $data
     * @return Response
     */
    public function json(array $data)
    {
		return new Response($data);
	}

    /**
     * @param string $text
     * @return Response
     */
    public function success($text = ''){
		$response = new Response([]);
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
		$response = new Response([]);
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
