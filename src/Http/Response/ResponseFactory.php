<?php

namespace LaravelRest\Http\Response;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;

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
        $response->success();
        if (!empty($text)) {
            $response->addMeta('text', $text);
        }
        return $response;
    }

    /**
     * @param mixed|null $errors
     * @param int $code
     * @param string $context
     * @param array $data
     * @return Response
     */
    public function error($errors = null, $code = 422, $context = '', array $data = [])
    {
        $response = $this->createResponse([], $code);
        $response->error($code);
        if ($errors) {
            if (is_array($errors) || is_object($errors)) {
                $response->addMeta('errors', $errors);
                $response->addMeta('message', 'Не удалось сохранить данные');
            } elseif (is_string($errors)) {
                $response->addMeta('text', $errors);
            }

            if ($context) {
                $response->addMeta('context', $context);
            }
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
