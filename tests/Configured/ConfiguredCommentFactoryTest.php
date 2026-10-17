<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;

/**
 * The shipped factory builds the model the host configured. Without a `modelName()` override
 * it built its packaged `$model`, so a host seeding through `Comment::factory()` got packaged
 * rows — without its own casts, events or observers. Exact classes, not `instanceof`: the
 * custom model extends the packaged one, so `toBeInstanceOf` passes either way.
 */
it('builds the configured comment model', function (): void {
    $post = PostTestModel::create();

    expect(Comment::factory()->on($post)->create()::class)->toBe(CustomCommentTestModel::class)
        ->and(CustomCommentTestModel::factory()->on($post)->create()::class)->toBe(CustomCommentTestModel::class);
});

it('fires the host model events for the comment a mention factory creates', function (): void {
    // Reading `$mention->comment` back resolves through the seam either way, so the oracle is
    // the host class's own `created` event: it fires only for a row created as that class.
    $post = PostTestModel::create();
    CustomCommentTestModel::resetCreationCount();

    CommentMention::factory()->create(['comment_id' => Comment::factory()->on($post)]);

    expect(CustomCommentTestModel::creationCount())->toBe(1);
});

it('builds the configured comment model for a bare comment and mention', function (): void {
    config()->set('auth.providers.users.model', UserTestModel::class);
    CustomCommentTestModel::resetCreationCount();

    $comment = Comment::factory()->create();
    CommentMention::factory()->create();

    expect($comment::class)->toBe(CustomCommentTestModel::class)
        ->and(CustomCommentTestModel::creationCount())->toBe(2);
});
