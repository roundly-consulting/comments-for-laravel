<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('fetches approved comments for a subject newest first', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'old', 'created_at' => now()->subDay()]);
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'new', 'created_at' => now()]);
    Comment::factory()->for($post, 'commentable')->pending()->create(['comment' => 'pending']);

    $results = Comments::for($post)->approved()->newest()->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->comment)->toBe('new');
});

it('orders oldest first', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'old', 'created_at' => now()->subDay()]);
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'new', 'created_at' => now()]);

    expect(Comments::for($post)->oldest()->get()->first()->comment)->toBe('old');
});

it('filters by pending, hidden and visible', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->pending()->create();
    Comment::factory()->for($post, 'commentable')->hidden()->create();
    Comment::factory()->for($post, 'commentable')->create(['visible' => false]);
    Comment::factory()->for($post, 'commentable')->create();

    expect(Comments::for($post)->pending()->count())->toBe(1)
        ->and(Comments::for($post)->hidden()->count())->toBe(1)
        ->and(Comments::for($post)->visible()->count())->toBe(1);
});

it('returns roots only', function (): void {
    $post = PostTestModel::create();
    $root = Comment::factory()->for($post, 'commentable')->create();
    Comment::factory()->for($post, 'commentable')->reply($root)->create();

    expect(Comments::for($post)->rootsOnly()->count())->toBe(1);
});

it('eager loads replies', function (): void {
    $post = PostTestModel::create();
    $root = Comment::factory()->for($post, 'commentable')->create();
    Comment::factory()->for($post, 'commentable')->reply($root)->create();

    $roots = Comments::for($post)->rootsOnly()->withReplies()->get();

    expect($roots->first()->relationLoaded('replies'))->toBeTrue();
});

it('paginates', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->count(3)->create();

    $page = Comments::for($post)->paginate(2);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(3)
        ->and($page->perPage())->toBe(2);
});

it('scopes by author', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $other = ActorTestModel::create();
    Comment::factory()->for($post, 'commentable')->by($user)->count(2)->create();
    Comment::factory()->for($post, 'commentable')->by($other)->create();

    expect(Comments::byAuthor($user)->count())->toBe(2);
});

it('exposes the underlying query for refinement', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'keep']);
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'drop']);

    $results = Comments::for($post)
        ->tap(fn ($query) => $query->where('comment', 'keep'))
        ->get();

    expect($results)->toHaveCount(1);
});

it('exposes the underlying eloquent builder', function (): void {
    $post = PostTestModel::create();

    expect(Comments::for($post)->query())
        ->toBeInstanceOf(Builder::class);
});

it('honours a custom comment model', function (): void {
    config()->set('comments.model', CustomCommentTestModel::class);
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->create();

    expect(Comments::for($post)->get()->first())
        ->toBeInstanceOf(CustomCommentTestModel::class);
});
