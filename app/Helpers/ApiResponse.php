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
    public function success($data = null, $codeResponse = ResponseEnum::HTTP_OK): JsonResponse
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
    public function fail($codeResponse = ResponseEnum::HTTP_ERROR, $data = null, $error = null): JsonResponse
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
                'status' => $status,
                // 'code'    => $code,
                'message' => $message,
                'data' => $data ?? null,
                'error' => $error,
            ], (int) substr(((string) $code), 0, 3));
    }


    public function paginate(LengthAwarePaginator $page)
    {
        return response()->json([
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'data' => $page->items()
        ]);
    }

    /**
     * 业务异常返回
     * @param array $codeResponse
     * @param string $info
     * @throws BusinessException
     */
    public function throwBusinessException(array $codeResponse = ResponseEnum::HTTP_ERROR, string $info = '')
    {
        throw new BusinessException($codeResponse, $info);
    }
}