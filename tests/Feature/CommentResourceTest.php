<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Comments\Http\Resources\CommentCollection;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Likes\Facades\Likes;

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

it('includes a like payload when likes are loaded', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    Likes::actor($user)->like($comment);
    $comment->load('likes');

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array['likes']['count'])->toBe(1)
        ->and($array['likes']['viewer_state']['liked'])->toBeFalse()
        ->and($array['likes']['breakdown'])->toBe(['like' => 1]);
});

it('omits the like payload when likes are not loaded', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    // resolve() applies the MissingValue filtering that a real response performs.
    $array = (new CommentResource($comment))->resolve(Request::create('/'));

    expect($array)->not->toHaveKey('likes')
        ->and($array)->not->toHaveKey('attachments');
});

it('includes attachments when media is loaded', function (): void {
    Storage::fake('public');
    config()->set('comments.media.visibility', 'public');

    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    $media = $comment->addMedia(UploadedFile::fake()->image('shot.jpg', 400, 300))
        ->toBucket($comment->attachmentsBucket());
    $comment->load('media');

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array['attachments'])->toHaveCount(1)
        ->and($array['attachments'][0]['id'])->toBe($media->uuid)
        ->and($array['attachments'][0]['url'])->toContain($media->uuid);
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
