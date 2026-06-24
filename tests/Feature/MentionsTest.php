<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('parses and stores @handles even without a resolver', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('hey @alice and @bob_99')->post();

    expect($comment->mentions()->pluck('handle')->all())->toBe(['alice', 'bob_99']);
});

it('does not mistake an email for a mention', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('mail me at user@example.com')->post();

    expect($comment->mentions()->count())->toBe(0);
});

it('deduplicates repeated handles', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('@alice @alice again')->post();

    expect($comment->mentions()->count())->toBe(1);
});

it('resolves handles to models and links them', function (): void {
    $alice = ActorTestModel::create();

    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'alice' ? $alice : null);

    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('hi @alice and @ghost')->post();

    $resolved = $comment->mentions()->where('handle', 'alice')->first();
    $unresolved = $comment->mentions()->where('handle', 'ghost')->first();

    expect($resolved->mentionable->is($alice))->toBeTrue()
        ->and($unresolved->mentionable_id)->toBeNull();
});

it('dispatches CommentMentioned for resolved mentions only', function (): void {
    Event::fake([CommentMentioned::class]);
    $alice = ActorTestModel::create();

    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'alice' ? $alice : null);

    $post = PostTestModel::create();
    Comments::on($post)->body('@alice @ghost')->post();

    Event::assertDispatchedTimes(CommentMentioned::class, 1);
});

it('re-syncs mentions when the comment is edited', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('@alice')->post();

    Comments::update($comment, 'now mentioning @bob instead');

    expect($comment->mentions()->pluck('handle')->all())->toBe(['bob']);
});
