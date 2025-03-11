<?php

namespace App\Http\Controllers;

use App\Models\Posts;
use App\Models\Likes;
use App\Models\Comments;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ResponseTrait;

class BlogController extends Controller
{
    use ResponseTrait;

    protected $postType = 'blog';

    // Fetch all published blogs
    public function index(Request $request)
    {
        try {
            $query = Posts::where('status', 'published');

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $posts = $query->get()->map(fn($post) => $this->attachPostDetails($post));

            return $this->successResponse($posts, 'Blog posts fetched successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Fetch a single blog post
    public function show($id)
    {
        try {
            $post = Posts::where('id', $id)->where('status', 'published')->firstOrFail();
            return $this->successResponse($this->attachPostDetails($post), 'Blog post fetched successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Blog not found', 404);
        }
    }

    // Create a new blog post
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'post_title' => 'required|string|max:255',
                'post_description' => 'required|string|max:255',
                'post_content' => 'required|string',
                'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'author_id' => 'required',
                'category' => 'nullable|string',
                'status' => 'required|in:published,archived,draft',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $validatedData = $validator->validated();

            if ($request->hasFile('featured_image')) {
                $imagePath = $request->file('featured_image')->store('featured_images', 'public');
                $validatedData['featured_image'] = 'storage/' . $imagePath;
            }

            $validatedData['post_type'] = $this->postType;
            $post = Posts::create($validatedData);

            return $this->successResponse($post, 'Blog post created successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Update a blog post
    public function update(Request $request, $id)
    {
        try {
            // Ensure the blog post exists
            $post = Posts::findOrFail($id);

            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'post_title' => 'required|string|max:255',
                'post_description' => 'required|string|max:255',
                'post_content' => 'required|string',
                'category' => 'nullable|string',
                'status' => 'required|in:published,archived,draft',
                'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Extract validated data
            $validatedData = $validator->validated();

            // Handle image upload if a new image is provided
            if ($request->hasFile('featured_image')) {
                $imagePath = $request->file('featured_image')->store('featured_images', 'public');
                $validatedData['featured_image'] = 'storage/' . $imagePath;
            }

            // Update the blog post
            $post->update($validatedData);

            return $this->successResponse($post, 'Blog post updated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Error updating blog: ' . $e->getMessage(), 500);
        }
    }


    // Delete a blog post (soft delete)
    public function destroy($id)
    {
        try {
            $post = Posts::findOrFail($id);

            // Delete related likes, comments, and ratings
            Likes::where('post_id', $id)->delete();
            Comments::where('post_id', $id)->delete();
            Rating::where('post_id', $id)->delete();

            // Archive the post
            $post->update(['status' => 'archived']);

            return $this->successResponse($post, 'Blog post archived successfully with related data deleted.');
        } catch (\Exception $e) {
            return $this->errorResponse('Blog not found', 404);
        }
    }

    // Restore an archived blog post
    public function restore($id)
    {
        try {
            $post = Posts::where('id', $id)->where('status', 'archived')->firstOrFail();
            $post->update(['status' => 'published']);
            return $this->successResponse($post, 'Archived post restored successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Archived post not found.', 404);
        }
    }

    // Like or Unlike a blog post
    public function like(Request $request, $id)
    {
        try {
            $like = Likes::where('user_id', $request->user()->id)->where('post_id', $id)->first();

            if ($like) {
                $like->delete();
                return $this->successResponse([], 'Blog unliked successfully.');
            }

            Likes::create(['user_id' => $request->user()->id, 'post_id' => $id]);
            return $this->successResponse([], 'Blog liked successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Rate a blog post
    public function rate(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), ['rating' => 'required|integer|between:1,5']);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            Rating::updateOrCreate(
                ['user_id' => $request->user()->id, 'post_id' => $id],
                ['rating' => $request->rating]
            );

            return $this->successResponse([], 'Blog rated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Search for blogs
    public function search(Request $request)
    {
        try {
            $query = Posts::where('status', 'published')->where(function ($q) use ($request) {
                $q->where('post_title', 'like', "%{$request->search}%")
                    ->orWhere('post_description', 'like', "%{$request->search}%")
                    ->orWhere('category', 'like', "%{$request->search}%");
            });

            return $this->successResponse($query->get(), 'Search results.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Fetch archived blogs
    public function archivedBlogs()
    {
        return $this->successResponse(Posts::where('status', 'archived')->get(), 'Archived blogs fetched.');
    }

    private function attachPostDetails($post)
    {
        $post->likes = Likes::where('post_id', $post->id)->count();
        $post->comments = Comments::where('post_id', $post->id)->get();
        $post->ratings = Rating::where('post_id', $post->id)->get();
        return $post;
    }

    public function comment(Request $request, $id)
    {
        try {
            // Validate the comment input
            $validator = Validator::make($request->all(), [
                'comment' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Ensure the blog post exists
            $post = Posts::where('id', $id)->where('status', 'published')->first();
            if (!$post) {
                return $this->errorResponse('Blog post not found', 404);
            }

            // Create the comment
            $comment = Comments::create([
                'user_id' => $request->user()->id,
                'post_id' => $id,
                'post_comment' => $request->comment,
            ]);

            return $this->successResponse($comment, 'Comment added successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Error adding comment: ' . $e->getMessage(), 500);
        }
    }

    public function deleteComment($id)
    {
        try {
            // Find the comment by ID
            $comment = Comments::find($id);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            // Delete the comment
            $comment->delete();

            return $this->successResponse([], 'Comment deleted successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting comment: ' . $e->getMessage(), 500);
        }
    }
}