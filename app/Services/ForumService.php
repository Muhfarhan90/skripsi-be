<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ForumService
{
    /*
    |--------------------------------------------------------------------------
    | STUDENT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Student: Mendapatkan semua post berdasarkan course_id.
     */
    public function getPostsByCourse(int $courseId, int $userId)
    {
        $this->ensureEnrolled($courseId, $userId);

        return ForumPost::where('course_id', $courseId)
            ->with(['user', 'replies'])
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(15);
    }

    /**
     * Student: Mendapatkan detail post beserta semua reply-nya.
     */
    public function getPostDetail(int $courseId, int $postId, int $userId): ForumPost
    {
        $this->ensureEnrolled($courseId, $userId);

        return ForumPost::where('course_id', $courseId)
            ->with(['user', 'replies.user'])
            ->findOrFail($postId);
    }

    /**
     * Student: Membuat post baru di forum kursus.
     */
    public function createPost(int $courseId, int $userId, array $data): ForumPost
    {
        $this->ensureEnrolled($courseId, $userId);

        $post = ForumPost::create([
            'course_id' => $courseId,
            'user_id' => $userId,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        return $post->load('user');
    }

    /**
     * Student: Mengupdate post milik sendiri.
     */
    public function updatePost(int $courseId, int $postId, int $userId, array $data): ForumPost
    {
        $post = ForumPost::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->findOrFail($postId);

        $post->update($data);

        return $post->fresh(['user', 'replies.user']);
    }

    /**
     * Student: Menghapus post milik sendiri (soft delete).
     */
    public function deletePost(int $courseId, int $postId, int $userId): void
    {
        $post = ForumPost::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->findOrFail($postId);

        $post->delete();
    }

    /**
     * Student: Membuat reply pada sebuah post.
     */
    public function createReply(int $courseId, int $postId, int $userId, array $data): ForumReply
    {
        $this->ensureEnrolled($courseId, $userId);

        ForumPost::where('course_id', $courseId)->findOrFail($postId);

        $reply = ForumReply::create([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $data['content'],
        ]);

        return $reply->load('user');
    }

    /**
     * Student: Mengupdate reply milik sendiri.
     */
    public function updateReply(int $replyId, int $userId, array $data): ForumReply
    {
        $reply = ForumReply::where('user_id', $userId)->findOrFail($replyId);
        $reply->update($data);

        return $reply->fresh('user');
    }

    /**
     * Student: Menghapus reply milik sendiri (soft delete).
     */
    public function deleteReply(int $replyId, int $userId): void
    {
        $reply = ForumReply::where('user_id', $userId)->findOrFail($replyId);
        $reply->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & INSTRUCTOR FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Admin/Instructor: Mendapatkan semua post berdasarkan course_id.
     * - Admin: bisa akses semua course.
     * - Instructor: hanya course yang dia ajar.
     */
    public function getPostsByCourseForAdmin(int $courseId, User $user)
    {
        $this->ensureAdminAccess($courseId, $user);

        return ForumPost::where('course_id', $courseId)
            ->with(['user', 'replies'])
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(15);
    }

    /**
     * Admin/Instructor: Mendapatkan detail post.
     */
    public function getPostDetailForAdmin(int $courseId, int $postId, User $user): ForumPost
    {
        $this->ensureAdminAccess($courseId, $user);

        return ForumPost::where('course_id', $courseId)
            ->with(['user', 'replies.user'])
            ->findOrFail($postId);
    }

    /**
     * Admin/Instructor: Membuat post baru.
     */
    public function createPostForAdmin(int $courseId, User $user, array $data): ForumPost
    {
        $this->ensureAdminAccess($courseId, $user);

        $post = ForumPost::create([
            'course_id' => $courseId,
            'user_id' => $user->id,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        return $post->load('user');
    }

    /**
     * Admin/Instructor: Mengupdate post milik sendiri.
     */
    public function updatePostForAdmin(int $courseId, int $postId, User $user, array $data): ForumPost
    {
        $this->ensureAdminAccess($courseId, $user);

        $post = ForumPost::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->findOrFail($postId);

        $post->update($data);

        return $post->fresh(['user', 'replies.user']);
    }

    /**
     * Admin/Instructor: Membuat reply.
     */
    public function createReplyForAdmin(int $courseId, int $postId, User $user, array $data): ForumReply
    {
        $this->ensureAdminAccess($courseId, $user);

        ForumPost::where('course_id', $courseId)->findOrFail($postId);

        $reply = ForumReply::create([
            'post_id' => $postId,
            'user_id' => $user->id,
            'content' => $data['content'],
        ]);

        return $reply->load('user');
    }

    /**
     * Admin/Instructor: Mengupdate reply milik sendiri.
     */
    public function updateReplyForAdmin(int $replyId, User $user, array $data): ForumReply
    {
        $reply = ForumReply::where('user_id', $user->id)->findOrFail($replyId);

        $reply->update($data);

        return $reply->fresh('user');
    }

    /**
     * Admin/Instructor: Pin/Unpin sebuah post.
     */
    public function togglePin(int $courseId, int $postId, User $user): ForumPost
    {
        $this->ensureAdminAccess($courseId, $user);

        $post = ForumPost::where('course_id', $courseId)->findOrFail($postId);
        $post->update(['is_pinned' => !$post->is_pinned]);

        return $post->fresh(['user', 'replies.user']);
    }

    /**
     * Admin/Instructor: Menghapus post siapapun (soft delete).
     */
    public function deletePostForAdmin(int $courseId, int $postId, User $user): void
    {
        $this->ensureAdminAccess($courseId, $user);

        $post = ForumPost::where('course_id', $courseId)->findOrFail($postId);
        $post->delete();
    }

    /**
     * Admin/Instructor: Menghapus reply siapapun (soft delete).
     */
    public function deleteReplyForAdmin(int $replyId, User $user): void
    {
        $reply = ForumReply::with('post')->findOrFail($replyId);

        // Instructor: pastikan reply ada di course yang dia ajar
        if ($this->isInstructor($user)) {
            $courseId = $reply->post->course_id;
            $this->ensureAdminAccess($courseId, $user);
        }

        $reply->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Memastikan student memiliki enrollment aktif/completed.
     */
    private function ensureEnrolled(int $courseId, int $userId): void
    {
        $isEnrolled = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        if (!$isEnrolled) {
            throw ValidationException::withMessages([
                'course_id' => ['You must be enrolled in this course to access the forum.'],
            ]);
        }
    }

    /**
     * Memastikan admin/instructor memiliki akses ke course.
     * - Admin: selalu punya akses.
     * - Instructor: hanya course yang dia ajar (instructor_id = user.id).
     */
    private function ensureAdminAccess(int $courseId, User $user): void
    {
        $user->loadMissing('role');

        if ($this->isAdmin($user)) {
            Course::findOrFail($courseId);
            return;
        }

        if ($this->isInstructor($user)) {
            $isTeaching = Course::where('id', $courseId)
                ->where('instructor_id', $user->id)
                ->exists();

            if (!$isTeaching) {
                throw ValidationException::withMessages([
                    'course_id' => ['You can only access forums in courses you teach.'],
                ]);
            }
            return;
        }

        throw ValidationException::withMessages([
            'role' => ['You do not have permission to access this resource.'],
        ]);
    }

    private function isAdmin(User $user): bool
    {
        $user->loadMissing('role');
        return $user->role && $user->role->name === 'admin';
    }

    private function isInstructor(User $user): bool
    {
        $user->loadMissing('role');
        return $user->role && $user->role->name === 'instructor';
    }
}
