<?php

namespace Modules\FnbPos\Http;

use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FnbPos\Services\FnbChainReportService;

final class FnbChainReportController
{
    public function __invoke(Request $request, SiteContext $site, FnbChainReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->summary($request->user('admin'), $site->websiteKey(), $request->query())]);
    }
}
