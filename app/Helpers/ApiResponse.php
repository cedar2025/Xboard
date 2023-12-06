<?php

namespace App\Helpers;

use App\Helpers\ResponseEnum;
use App\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait ApiResponse
{
    /**
     * 成功
     * @param mixed $data
     * @param array $codeResponse
     * @return JsonResponse
     */
    public function success($data = null, $codeResponse=ResponseEnum::HTTP_OK): JsonResponse
    {
        return $this->jsonResponse('success', $codeResponse, $data, null);
    }

    /**
     * 失败
     * @param array $codeResponse
     * @param mixed $data
     * @param mixed $error
     * @return JsonResponse
     */
    public function fail($codeResponse=ResponseEnum::HTTP_ERROR, $data = null, $error=null): JsonResponse
    {
        return $this->jsonResponse('fail', $codeResponse, $data, $error);
    }

    /**
     * json响应
     * @param $status
     * @param $codeResponse
     * @param $data
     * @param $error
     * @return JsonResponse
     */
    private function jsonResponse($status, $codeResponse, $data, $error): JsonResponse
    {
        list($code, $message) = $codeResponse;
        return response()
            ->json([
            'status'  => $status,
            // 'code'    => $code,
            'message' => $message,
            'data'    => $data ?? null,
            'error'  => $error,
            ],(int)substr(((string) $code),0,3));
    }

    /**
     * 成功分页返回
     * @param $page
     * @return JsonResponse
     */
    protected function successPaginate($page): JsonResponse
    {
        return $this->success($this->paginate($page));
    }

    private function paginate($page)
    {
        if ($page instanceof LengthAwarePaginator){
            return [
                'total'  => $page->total(),
                'page'   => $page->currentPage(),
                'limit'  => $page->perPage(),
                'pages'  => $page->lastPage(),
                'list'   => $page->items()
            ];
        }
        if ($page instanceof Collection){
            $page = $page->toArray();
        }
        if (!is_array($page) && !is_object($page)){
            return $page;
        }
        $total = count($page);
        return [
            'total'  => $total, //数据总数
            'page'   => 1, // 当前页码
            'limit'  => $total, // 每页的数据条数
            'pages'  => 1, // 最后一页的页码
            'list'   => $page // 数据
        ];
    }

    /**
     * 业务异常返回
     * @param array $codeResponse
     * @param string $info
     * @throws BusinessException
     */
    public function throwBusinessException(array $codeResponse=ResponseEnum::HTTP_ERROR, string $info = '')
    {
        throw new BusinessException($codeResponse, $info);
    }
}