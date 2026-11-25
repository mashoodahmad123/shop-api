<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\TopProductsRequest;
use App\Http\Resources\TopProductsReportResource;
use App\Services\ReportService;
use App\Traits\ApiResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(protected ReportService $reportService)
    {
    }

    public function topProducts(TopProductsRequest $request)
    {
        $filters = $request->validated();

        $report = $this->reportService->topProductsReport(
            $filters['from'],
            $filters['to'],
            (int) ($filters['limit'] ?? 5)
        );

        return $this->success(new TopProductsReportResource($report));
    }
}
