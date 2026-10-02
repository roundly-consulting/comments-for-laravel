<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Exceptions\MaxReplyDepthExceededException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
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

it('threads only public roots and public replies', function (): void {
    $post = PostTestModel::create();
    $root = Comment::factory()->for($post, 'commentable')->create(['comment' => 'root']);
    $reply = Comment::factory()->reply($root)->create(['comment' => 'public reply']);
    Comment::factory()->reply($reply)->pending()->create(['comment' => 'pending grandchild']);
    Comment::factory()->reply($root)->hidden()->create(['comment' => 'SPAM reply']);
    Comment::factory()->reply($root)->create(['comment' => 'internal note', 'visible' => false]);
    Comment::factory()->for($post, 'commentable')->hidden()->create(['comment' => 'hidden root']);
    Comment::factory()->for($post, 'commentable')->pending()->create(['comment' => 'pending root']);
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'invisible root', 'visible' => false]);

    $roots = $post->threadedComments()->get();

    expect($roots->pluck('comment')->all())->toBe(['root'])
        ->and($roots->first()->replies->pluck('comment')->all())->toBe(['public reply'])
        ->and($roots->first()->replies->first()->replies)->toBeEmpty();
});

it('counts a soft-deleted ancestor towards the reply depth', function (): void {
    config()->set('comments.max_depth', 3);
    $post = PostTestModel::create();

    $d1 = Comments::on($post)->body('d1')->post();
    $d2 = Comments::on($post)->reply($d1)->body('d2')->post();
    $d3 = Comments::on($post)->reply($d2)->body('d3')->post();

    Comments::delete($d2);
    $d3 = Comment::query()->findOrFail($d3->getKey());

    Comments::on($post)->reply($d3)->body('d4')->post();
})->throws(MaxReplyDepthExceededException::class);
