<?php

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only published posts on the blog index', function () {
    $published = BlogPost::factory()->create([
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    $draft = BlogPost::factory()->create([
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->get(route('blog.index'))
        ->assertSuccessful()
        ->assertSee($published->title)
        ->assertDontSee($draft->title);
});

it('returns not found for unpublished or scheduled posts', function () {
    $draft = BlogPost::factory()->create([
        'is_published' => false,
        'published_at' => null,
    ]);

    $scheduled = BlogPost::factory()->create([
        'is_published' => true,
        'published_at' => now()->addDay(),
    ]);

    $this->get(route('blog.show', ['blogPost' => $draft->getKey(), 'slug' => $draft->slug]))
        ->assertNotFound();

    $this->get(route('blog.show', ['blogPost' => $scheduled->getKey(), 'slug' => $scheduled->slug]))
        ->assertNotFound();
});

it('shows a published post', function () {
    $post = BlogPost::factory()->create([
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    $this->get(route('blog.show', ['blogPost' => $post->getKey(), 'slug' => $post->slug]))
        ->assertSuccessful()
        ->assertSee($post->title);
});
