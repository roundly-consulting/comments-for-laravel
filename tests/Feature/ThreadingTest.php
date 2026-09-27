<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Exceptions\MaxReplyDepthExceededException;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('stores parent id and inherits the parent subject morph on a reply', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $root = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Root',
        author: $actor,
    ));

    $reply = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Reply',
        author: $actor,
        parent: $root,
    ));

    expect($reply->parent_id)->toBe($root->getKey())
        ->and($reply->commentable_id)->toBe($post->getKey())
        ->and($reply->commentable_type)->toBe($post->getMorphClass());
});

it('returns only direct children from the replies relation', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $root = $actor->writeComment(commentable: $post, comment: 'Root');

    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post, body: 'Reply A', author: $actor, parent: $root,
    ));
    $childB = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post, body: 'Reply B', author: $actor, parent: $root,
    ));
    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post, body: 'Grandchild', author: $actor, parent: $childB,
    ));

    expect($root->replies()->count())->toBe(2);
});

it('throws when a reply exceeds the max depth', function (): void {
    config()->set('comments.max_depth', 2);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $root = $actor->writeComment(commentable: $post, comment: 'Root'); // depth 1

    $reply = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post, body: 'Reply', author: $actor, parent: $root, // depth 2
    ));

    // depth 3 would exceed max_depth of 2
    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post, body: 'Too deep', author: $actor, parent: $reply,
    ));
})->throws(MaxReplyDepthExceededException::class);

it('bounds the threaded eager-load to max depth', function (): void {
    config()->set('comments.max_depth', 7);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    // Build a chain 7 deep.
    $current = $actor->writeComment(commentable: $post, comment: 'd1');
    for ($i = 2; $i <= 7; $i++) {
        $current = app(WriteCommentAction::class)->execute(new WriteCommentData(
            commentable: $post, body: "d{$i}", author: $actor, parent: $current,
        ));
    }

    // Now request a bounded load of depth 5.
    config()->set('comments.max_depth', 5);
    $roots = $post->threadedComments()->get();

    $node = $roots->first();
    $loadedDepth = 1;
    while ($node !== null && $node->relationLoaded('replies') && $node->replies->isNotEmpty()) {
        $loadedDepth++;
        $node = $node->replies->first();
    }

    expect($loadedDepth)->toBe(5);
});
