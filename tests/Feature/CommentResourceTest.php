<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Comments\Http\Resources\CommentCollection;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('serialises a comment to the expected shape', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create(['comment' => 'hello']);

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array)
        ->toHaveKeys(['id', 'body', 'status', 'visible', 'parent_id', 'locked', 'created_at', 'updated_at'])
        ->and($array['body'])->toBe('hello')
        ->and($array['status'])->toBe('approved')
        ->and($array['locked'])->toBeFalse();
});

it('includes the author when loaded', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->by($user)->create();
    $comment->load('actor');

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array['author'])->toBe(['type' => $user->getMorphClass(), 'id' => $user->getKey()]);
});

it('includes reaction counts when reactions are loaded', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    $comment->react('👍');
    $comment->load('reactions');

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array['reaction_counts'])->toBe(['👍' => 1]);
});

it('nests replies when loaded', function (): void {
    $post = PostTestModel::create();
    $root = Comment::factory()->for($post, 'commentable')->create();
    Comment::factory()->for($post, 'commentable')->reply($root)->create();
    $root->load('replies');

    $array = (new CommentResource($root))->toArray(Request::create('/'));

    expect($array['replies'])->toHaveCount(1);
});

it('builds a collection', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->count(2)->create();

    $collection = new CommentCollection($post->comments);

    expect($collection->toArray(Request::create('/')))->toHaveCount(2);
});
