<?php

namespace App\Http\Controllers\Api\Admin;

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

    /**
     * Mendapatkan semua post di forum kursus.
     */
    public function index(Request $request, string $courseId)
    {
        $posts = $this->service->getPostsByCourseForAdmin((int) $courseId, $request->user());

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

    /**
     * Mendapatkan detail post beserta reply-nya.
     */
    public function show(Request $request, string $courseId, string $postId)
    {
        $post = $this->service->getPostDetailForAdmin((int) $courseId, (int) $postId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Forum post retrieved successfully',
            'data' => new ForumPostResource($post),
        ]);
    }

    /**
     * Membuat post baru di forum (Instructor ikut diskusi).
     */
    public function store(StoreForumPostRequest $request, string $courseId)
    {
        $post = $this->service->createPostForAdmin(
            (int) $courseId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Forum post created successfully',
            'data' => new ForumPostResource($post),
        ], 201);
    }

    /**
     * Mengupdate post milik sendiri.
     */
    public function update(UpdateForumPostRequest $request, string $courseId, string $postId)
    {
        $post = $this->service->updatePostForAdmin(
            (int) $courseId,
            (int) $postId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Forum post updated successfully',
            'data' => new ForumPostResource($post),
        ]);
    }

    /**
     * Membuat reply pada sebuah post (Instructor ikut diskusi).
     */
    public function storeReply(StoreForumReplyRequest $request, string $courseId, string $postId)
    {
        $reply = $this->service->createReplyForAdmin(
            (int) $courseId,
            (int) $postId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply created successfully',
            'data' => new ForumReplyResource($reply),
        ], 201);
    }

    /**
     * Mengupdate reply milik sendiri.
     */
    public function updateReply(Request $request, string $replyId)
    {
        $request->validate(['content' => 'required|string']);

        $reply = $this->service->updateReplyForAdmin(
            (int) $replyId,
            $request->user(),
            $request->only('content')
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply updated successfully',
            'data' => new ForumReplyResource($reply),
        ]);
    }

    /**
     * Pin/Unpin sebuah post.
     */
    public function togglePin(Request $request, string $courseId, string $postId)
    {
        $post = $this->service->togglePin((int) $courseId, (int) $postId, $request->user());

        return response()->json([
            'success' => true,
            'message' => $post->is_pinned ? 'Post pinned successfully' : 'Post unpinned successfully',
            'data' => new ForumPostResource($post),
        ]);
    }

    /**
     * Menghapus post siapapun (moderasi).
     */
    public function destroyPost(Request $request, string $courseId, string $postId)
    {
        $this->service->deletePostForAdmin((int) $courseId, (int) $postId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Forum post deleted successfully',
        ]);
    }

    /**
     * Menghapus reply siapapun (moderasi).
     */
    public function destroyReply(Request $request, string $replyId)
    {
        $this->service->deleteReplyForAdmin((int) $replyId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reply deleted successfully',
        ]);
    }
}
