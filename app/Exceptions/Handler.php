<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        // Handle PHP/symfony "post too large" exception and return friendly message
        if ($this->isPostTooLarge($e)) {
            $max = $this->getUploadMaxReadable();

            $message = "Tập tin quá lớn. Vui lòng chọn file nhỏ hơn {$max}.";

            if ($request->expectsJson() || $request->is('admin/api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'avatar' => [$message],
                    ],
                ], 413);
            }

            // For web requests redirect back with an error message
            if ($request->method() === 'POST') {
                return redirect()->back()->withInput()->withErrors(['avatar' => $message]);
            }

            return response($message, 413);
        }

        return parent::render($request, $e);
    }

    /**
     * Determine if exception represents a post-too-large error.
     */
    protected function isPostTooLarge(Throwable $e): bool
    {
        if ($e instanceof PostTooLargeException) {
            return true;
        }

        if ($e instanceof HttpExceptionInterface && method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode() === 413;
        }

        // Some environments will throw a generic exception with message mentioning "post".
        $msg = strtolower($e->getMessage());
        if (strpos($msg, 'post_max_size') !== false || strpos($msg, 'post size') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get a human readable upload_max_filesize value (like "8M").
     */
    protected function getUploadMaxReadable(): string
    {
        $val = ini_get('upload_max_filesize') ?: ini_get('post_max_size');
        return $val ?: '5M';
    }
}
