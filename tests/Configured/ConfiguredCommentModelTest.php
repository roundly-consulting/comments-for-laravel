<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

/**
 * S — the model-swap proof, driven the way a host actually drives it: `comments.model`
 * points at CustomCommentTestModel BEFORE the providers boot (see SwappedModelsTestCase).
 *
 * This replaces `Feature/ConfigSwapTest`, which set the key in the test body and asserted
 * `instanceof`. Both halves of that were weak:
 *
 *  - body-time config is not what a host does, and cannot see a boot-time bug — the
 *    provider has already hung its observers on the packaged class by then;
 *  - `instanceof` passes even when the row was CREATED as the packaged class (permissions
 *    #31), because re-querying through the host class re-hydrates the row whatever it was
 *    created as. The host's model events never fire, and the test never notices.
 *
 * `toHonourModelSwap` closes both: it fails fast if the before-boot swap is missing,
 * asserts every returned model's CONCRETE class, and — via the required `CountsCreations`
 * trait — asserts a `created` event really landed on the host subclass itself. That last
 * half is the only proof the row was created *as* the host class.
 */
it('honours the configured comment model through the real write flow', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    expect('comments.model')->toHonourModelSwap(
        CustomCommentTestModel::class,
        function () use ($actor, $post): array {
            // The real documented flow, not a resolver string check.
            $comment = $actor->writeComment(commentable: $post, comment: 'Custom model');

            return [$comment, $post->comments()->first(), $actor->writtenComments()->first()];
        },
    );
});

/**
 * The relations resolve through the seam too. This is the half that catches an implicit
 * `hasMany` FK derived from the parent class name — the retrofit's single biggest bug class
 * (12+ entries), and the exact bug appointments shipped.
 */
it('reads written and commentable relations through the configured model', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $actor->writeComment(commentable: $post, comment: 'Custom');

    expect($actor->writtenComments->first())->toBeInstanceOf(CustomCommentTestModel::class)
        ->and($post->comments->first())->toBeInstanceOf(CustomCommentTestModel::class)
        // The concrete class, not merely `instanceof` — a subclass passes `instanceof` its
        // own parent, so this is what pins that the seam, not inheritance, did the work.
        ->and($post->comments->first()::class)->toBe(CustomCommentTestModel::class);
});

/**
 * The self-referencing threading relations must resolve through the seam too.
 *
 * This is the seam-bypass class — a hard-coded call site sitting beside an honoured config
 * (media #28, shops #3). `Comment::parent()` / `Comment::replies()` declared
 * `belongsTo(self::class)` / `hasMany(self::class)`, and `self::class` binds to the class
 * that DEFINED the method, not the configured one — so a host that swapped `comments.model`
 * got its own class from the write flow but the PACKAGED Comment back from every reply and
 * parent. Its casts, accessors and model events silently never applied to a threaded reply.
 *
 * The whole suite stayed green because nothing had ever asserted the concrete class of a
 * relation result under a real before-boot swap: `instanceof Comment` passes for both.
 */
it('resolves the threading relations through the configured model', function (): void {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $root = $actor->writeComment(commentable: $post, comment: 'Root');

    $reply = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Reply',
        author: $actor,
        parent: $root,
    ));

    // The concrete class, never `instanceof`: the packaged Comment is the host subclass's
    // own parent, so `instanceof` passes on exactly the broken result this pins.
    expect($root->replies()->first()::class)->toBe(CustomCommentTestModel::class)
        ->and($reply->parent()->first()::class)->toBe(CustomCommentTestModel::class)
        ->and($root->replies->first()::class)->toBe(CustomCommentTestModel::class)
        ->and($reply->parent->first()?->id ?? $reply->parent->id)->toBe($root->getKey());
});
