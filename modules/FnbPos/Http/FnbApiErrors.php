<?php

namespace Modules\FnbPos\Http;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbDomainException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FnbApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);

        try {
            $response = $next($request);
        } catch (FnbDomainException $exception) {
            $code = match ($exception->status) {
                404 => 'FNB_RESOURCE_NOT_FOUND',
                409 => 'FNB_STATE_CONFLICT',
                default => 'FNB_VALIDATION_FAILED',
            };
            $response = $this->error($exception->status, $code, $exception->getMessage(), $requestId, $exception->details);
        } catch (ValidationException $exception) {
            $response = $this->error(422, 'FNB_VALIDATION_FAILED', $exception->getMessage(), $requestId, ['errors' => $exception->errors()]);
        } catch (AuthorizationException $exception) {
            $response = $this->error(403, 'FNB_PERMISSION_DENIED', $exception->getMessage(), $requestId);
        } catch (ModelNotFoundException $exception) {
            $response = $this->error(404, 'FNB_RESOURCE_NOT_FOUND', 'Không tìm thấy dữ liệu trong điểm bán hiện tại.', $requestId);
        } catch (HttpExceptionInterface $exception) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                403 => 'FNB_PERMISSION_DENIED',
                404 => 'FNB_RESOURCE_NOT_FOUND',
                409 => 'FNB_STATE_CONFLICT',
                422 => 'FNB_VALIDATION_FAILED',
                423 => 'FNB_REAUTH_REQUIRED',
                429 => 'FNB_RATE_LIMITED',
                default => 'FNB_REQUEST_FAILED',
            };
            $details = method_exists($exception, 'details') ? $exception->details() : [];
            $response = $this->error($status, $code, $exception->getMessage(), $requestId, $details);
        }

        // Laravel's routing pipeline may render before control returns to this middleware.
        if ($response->getStatusCode() >= 400) {
            $body = json_decode($response->getContent(), true);
            if (! is_array($body) || ! isset($body['code'])) {
                $status = $response->getStatusCode();
                $response = $this->error($status, match ($status) {
                    403 => 'FNB_PERMISSION_DENIED', 404 => 'FNB_RESOURCE_NOT_FOUND',
                    409 => 'FNB_STATE_CONFLICT', 422 => 'FNB_VALIDATION_FAILED',
                    429 => 'FNB_RATE_LIMITED', default => 'FNB_REQUEST_FAILED',
                }, $status >= 500 ? 'Không thể xử lý yêu cầu. Vui lòng cung cấp mã yêu cầu để kiểm tra.' : ($body['message'] ?? 'Yêu cầu không hợp lệ.'),
                    $requestId, isset($body['errors']) ? ['errors' => $body['errors']] : []);
            } elseif (isset($body['details']) && $response instanceof JsonResponse) {
                $body['details'] = FnbPublicProjection::operational($body['details']);
                $response->setData($body);
            }
        }
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function error(int $status, string $code, string $message, string $requestId, array $details = []): Response
    {
        return response()->json(['code' => $code, 'message' => $message, 'details' => FnbPublicProjection::operational($details), 'request_id' => $requestId], $status);
    }
}
