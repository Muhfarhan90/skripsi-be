<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreForumPostRequest;
use App\Http\Requests\Forum\UpdateForumPostRequest;
use App\Http\Requests\Forum\StoreForumReplyRequest;
use App\Http\Resources\ForumPostResource;
use App\Http\Resources\ForumReplyResource;
use App\Services\ForumService;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    protected ForumService $service;

    public function __construct(ForumService $forumService)
    {
        $this->service = $forumService;
    }

    public function index(Request $request, string $courseId)
    {
        $posts = $this->service->getPostsByCourse((int) $courseId, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Forum posts retrieved successfully',
            'data' => ForumPostResource::collection($posts),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(Request $request, string $courseId, string $postId)
    {
        $post = $this->service->getPostDetail((int) $courseId, (int) $postId, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Forum post retrieved successfully',
            'data' => new ForumPostResource($post),
        ]);
    }

    public function store(StoreForumPostRequest $request, string $courseId)
    {
        $post = $this->service->createPost(
            (int) $courseId,
            (int) $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Forum post created successfully',
            'data' => new ForumPostResource($post),
        ], 201);
    }

    public function update(UpdateForumPostRequest $request, string $courseId, string $postId)
    {
        $post = $this->service->updatePost(
            (int) $courseId,
            (int) $postId,
            (int) $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Forum post updated successfully',
            'data' => new ForumPostResource($post),
        ]);
    }

    public function destroy(Request $request, string $courseId, string $postId)
    {
        $this->service->deletePost((int) $courseId, (int) $postId, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Forum post deleted successfully',
        ]);
    }

    public function storeReply(StoreForumReplyRequest $request, string $courseId, string $postId)
    {
        $reply = $this->service->createReply(
            (int) $courseId,
            (int) $postId,
            (int) $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply created successfully',
            'data' => new ForumReplyResource($reply),
        ], 201);
    }

    public function updateReply(Request $request, string $replyId)
    {
        $request->validate(['content' => 'required|string']);

        $reply = $this->service->updateReply(
            (int) $replyId,
            (int) $request->user()->id,
            $request->only('content')
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply updated successfully',
            'data' => new ForumReplyResource($reply),
        ]);
    }

    public function destroyReply(Request $request, string $replyId)
    {
        $this->service->deleteReply((int) $replyId, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Reply deleted successfully',
        ]);
    }
}
