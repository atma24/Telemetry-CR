<?php

namespace App\Http\Controllers\Api\Feedback;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Http\Requests\Feedback\UpdateFeedbackStatusRequest;
use App\Models\Feedback\UmpanBalik;
use App\Services\Feedback\FeedbackService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request, FeedbackService $feedbackService): JsonResponse
    {
        return response()->json($feedbackService->submit(
            $request->string('subject')->toString(),
            $request->string('message')->toString(),
            $request->filled('facility_id') ? $request->string('facility_id')->toString() : null,
            $request->filled('project_id') ? $request->string('project_id')->toString() : null,
            $request->filled('region_id') ? $request->string('region_id')->toString() : null,
            $request->filled('dataset_id') ? $request->string('dataset_id')->toString() : null,
            $request->user()->id,
        ), 201);
    }

    /** @return LengthAwarePaginator<int, UmpanBalik> */
    public function index(Request $request, FeedbackService $feedbackService): LengthAwarePaginator
    {
        return $feedbackService->adminIndex($request->integer('per_page', 15));
    }

    public function updateStatus(
        UpdateFeedbackStatusRequest $request,
        string $feedback,
        FeedbackService $feedbackService,
    ): JsonResponse {
        return response()->json(
            $feedbackService->updateStatus(
                $feedback,
                $request->string('status')->toString(),
                $request->filled('response_message') ? $request->string('response_message')->toString() : null,
                $request->filled('rejection_reason') ? $request->string('rejection_reason')->toString() : null,
                $request->user()->id,
            ),
        );
    }
}
