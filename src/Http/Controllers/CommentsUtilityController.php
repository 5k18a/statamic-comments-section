<?php

namespace Skalisty\Comments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Skalisty\Comments\Services\CommentModerationRepository;
use Statamic\Http\Controllers\CP\CpController;

class CommentsUtilityController extends CpController
{
    public function __construct(private readonly CommentModerationRepository $comments) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return $this->comments->indexData();
    }

    public function rows(Request $request, string $blogEntryId): JsonResponse
    {
        return response()->json($this->comments->rows($blogEntryId, $request));
    }

    public function publish(string $commentId): JsonResponse
    {
        return response()->json($this->comments->setPublished($commentId, true));
    }

    public function unpublish(string $commentId): JsonResponse
    {
        return response()->json($this->comments->setPublished($commentId, false));
    }

    public function destroy(string $commentId): JsonResponse
    {
        return response()->json($this->comments->destroy($commentId));
    }
}
