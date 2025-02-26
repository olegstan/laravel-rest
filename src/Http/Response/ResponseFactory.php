<?php

namespace LaravelRest\Http\Response;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Class ResponseFactory
 * @package LaravelRest\Http\Response
 */
class ResponseFactory
{
    /**
     * @param Collection $collection
     * @param callable|null $transformer
     * @param array $headers
     * @return Response
     */
    public function collectOptimize(Collection $collection, $transformer = null, array $headers = [])
    {
        return $this->createResponse($collection, 200, $headers, $transformer, 'collectOptimize');
    }

    /**
     * @param Collection $collection
     * @param callable|null $transformer
     * @param array $headers
     * @return Response
     */
    public function collection(Collection $collection, $transformer = null, array $headers = [])
    {
        return $this->createResponse($collection, 200, $headers, $transformer, 'collect');
    }

    /**
     * @param Paginator $paginator
     * @param callable|null $transformer
     * @param array $headers
     * @return Response
     */
    public function paginator(Paginator $paginator, $transformer = null, array $headers = [])
    {
        return $this->createResponse($paginator, 200, $headers, $transformer, 'paginator');
    }

    /**
     * @param mixed $item
     * @param callable|null $transformer
     * @param array $headers
     * @return Response
     */
    public function item($item, $transformer = null, array $headers = [])
    {
        return $this->createResponse($item, 200, $headers, $transformer, 'item');
    }

    /**
     * @param array $data
     * @param array $headers
     * @return Response
     */
    public function json(array $data, array $headers = [])
    {
        return $this->createResponse($data, 200, $headers);
    }

    /**
     * @param $filePath
     * @param $fileName
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($filePath, $fileName, array $headers = [])
    {
        return \Illuminate\Support\Facades\Response::download($filePath, $fileName, $headers);
    }

    /**
     * @param string $text
     * @return Response
     */
    public function success($text = '')
    {
        $response = $this->createResponse([], 200);
        if (!empty($text)) {
            $response->addMeta('text', $text);
        }
        return $response;
    }

    /**
     * @param $errorMessage
     * @param $code
     * @param array $data
     * @return Response
     */
    public function error($errorMessage = 'Не удалось сохранить данные', $code = 422, array $data = [])
    {
        $response = $this->createResponse([], $code);
        $response->addMeta('message', $errorMessage);

        if($data)
        {
            $response->addMeta('data', $data);
        }

        return $response;
    }

    /**
     * @param $errors
     * @param $code
     * @param $errorMessage
     * @param array $data
     * @return Response
     */
    public function validationError($errors = [], $code = 422, $errorMessage = 'Не удалось сохранить данные', array $data = [])
    {
        $response = $this->createResponse([], $code);
        if ($errors && (is_array($errors) || is_object($errors)))
        {
            $response->addMeta('errors', $errors);
            $response->addMeta('message', $errorMessage);
        }

        if($data)
        {
            $response->addMeta('data', $data);
        }

        return $response;
    }


    /**
     * Helper method to create a response instance.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param callable|null $transformer
     * @param string|null $method
     * @return Response
     */
    private function createResponse($data, $status = 200, array $headers = [], $transformer = null, $method = null)
    {
        $responseClass = config('rest.response');
        try {
            return new $responseClass($data, $status, $headers, $transformer, $method);
        } catch (\Exception $e) {
            // Handle the exception (e.g., log it, rethrow it, etc.)
            throw new \RuntimeException('Failed to create response instance.', 0, $e);
        }
    }
}
