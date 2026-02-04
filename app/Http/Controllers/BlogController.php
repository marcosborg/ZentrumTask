<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('website.blog.index', [
            'posts' => $posts,
        ]);
    }

    public function show(BlogPost $blogPost, ?string $slug = null): View|RedirectResponse
    {
        if (! $blogPost->is_published || ! $blogPost->published_at || $blogPost->published_at->isFuture()) {
            abort(404);
        }

        $expectedSlug = $blogPost->slug;

        if ($slug !== $expectedSlug) {
            return redirect()->route('blog.show', [
                'blogPost' => $blogPost->getKey(),
                'slug' => $expectedSlug,
            ]);
        }

        return view('website.blog.show', [
            'post' => $blogPost,
        ]);
    }
}
