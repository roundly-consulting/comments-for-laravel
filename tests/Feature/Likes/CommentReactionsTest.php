<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Likes\Contracts\Likeable;
use RoundlyConsulting\Likes\Facades\Likes;

function commentFor(): Comment
{
    return Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
}

it('is a likeable subject', function (): void {
    expect(commentFor())->toBeInstanceOf(Likeable::class);
});

it('likes, unlikes and toggles a comment with an explicit actor', function (): void {
    $comment = commentFor();
    $user = ActorTestModel::create();

    Likes::actor($user)->like($comment);

    expect($comment->likesCount())->toBe(1)
        ->and($comment->isLikedBy($user))->toBeTrue();

    Likes::actor($user)->unlike($comment);

    expect($comment->likesCount())->toBe(0)
        ->and($comment->isLikedBy($user))->toBeFalse();

    expect(Likes::actor($user)->toggle($comment))->toBeTrue()
        ->and($comment->fresh()->isLikedBy($user))->toBeTrue()
        ->and(Likes::actor($user)->toggle($comment))->toBeFalse();
});

it('does not double-count a duplicate like', function (): void {
    $comment = commentFor();
    $user = ActorTestModel::create();

    Likes::actor($user)->like($comment);
    Likes::actor($user)->like($comment);

    expect($comment->likesCount())->toBe(1);
});

it('ranks a thread by most-liked and trending', function (): void {
    $post = PostTestModel::create();
    $popular = Comment::factory()->for($post, 'commentable')->create();
    $quiet = Comment::factory()->for($post, 'commentable')->create();

    Likes::actor(ActorTestModel::create())->like($popular);
    Likes::actor(ActorTestModel::create())->like($popular);
    Likes::actor(ActorTestModel::create())->like($quiet);

    $byLikes = Comments::for($post)->orderByLikesDesc()->get();
    expect($byLikes->first()->is($popular))->toBeTrue()
        ->and($byLikes->last()->is($quiet))->toBeTrue();

    $trending = Comments::for($post)->orderByTrending()->get();
    expect($trending->first()->is($popular))->toBeTrue();
});

it('hydrates per-viewer like-state in a single query', function (): void {
    $post = PostTestModel::create();
    $liked = Comment::factory()->for($post, 'commentable')->create();
    $unliked = Comment::factory()->for($post, 'commentable')->create();
    $viewer = ActorTestModel::create();

    Likes::actor($viewer)->like($liked);

    $feed = Comments::for($post)
        ->withLikedState($viewer)
        ->oldest()
        ->get()
        ->keyBy(fn (Comment $c): int => $c->getKey());

    expect((int) $feed[$liked->getKey()]->is_liked)->toBe(1)
        ->and($feed[$liked->getKey()]->liked_reaction)->toBe('like')
        ->and((int) $feed[$unliked->getKey()]->is_liked)->toBe(0)
        ->and($feed[$unliked->getKey()]->liked_reaction)->toBeNull();
});

it('renders a guest liked state without a viewer', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $row = Comments::for($post)->withLikedState()->get()->first();

    expect((int) $row->is_liked)->toBe(0)
        ->and($row->liked_reaction)->toBeNull();
});

it('builds a compact like payload for an explicit viewer', function (): void {
    $comment = commentFor();
    $viewer = ActorTestModel::create();

    Likes::actor($viewer)->like($comment);
    Likes::actor(ActorTestModel::create())->like($comment);

    $state = $comment->likeState($viewer);

    expect($state['count'])->toBe(2)
        ->and($state['viewer_state']['liked'])->toBeTrue()
        ->and($state['viewer_state']['reaction'])->toBe('like')
        ->and($state['breakdown'])->toBe(['like' => 2]);
});

it('renders a guest like payload without a viewer', function (): void {
    $comment = commentFor();
    Likes::actor(ActorTestModel::create())->like($comment);

    $state = $comment->likeState();

    expect($state['count'])->toBe(1)
        ->and($state['viewer_state']['liked'])->toBeFalse()
        ->and($state['viewer_state']['reaction'])->toBeNull();
});
