<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\CommentMention;
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

it('notifies only newly mentioned people when a comment is edited', function (): void {
    // Regression: an edit deleted and re-created every mention row and dispatched
    // CommentMentioned for each one, so everyone already mentioned was notified again.
    $alice = ActorTestModel::create();
    $bob = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => match (strtolower($handle)) {
        'alice' => $alice,
        'bob' => $bob,
        default => null,
    });

    $comment = Comments::on(PostTestModel::create())->body('hi @alice')->post();
    $aliceRow = $comment->mentions()->sole();

    Event::fake([CommentMentioned::class]);

    Comments::update($comment, 'hi @alice and @bob');

    Event::assertDispatchedTimes(CommentMentioned::class, 1);
    Event::assertDispatched(CommentMentioned::class, fn (CommentMentioned $event): bool => $event->mention->mentionable?->is($bob) === true);
    expect($comment->mentions()->whereKey($aliceRow->getKey())->exists())->toBeTrue();
});

it('does not re-notify on an edit that keeps the same mentions', function (): void {
    $alice = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => strtolower($handle) === 'alice' ? $alice : null);

    $comment = Comments::on(PostTestModel::create())->body('hi @alice')->post();

    Event::fake([CommentMentioned::class]);

    Comments::update($comment, 'hello again @alice');
    // Same person under a different handle is not a new mention either.
    Comments::update($comment, 'hello again @Alice');

    Event::assertNotDispatched(CommentMentioned::class);
    expect($comment->mentions()->pluck('handle')->all())->toBe(['Alice']);
});

it('resolves a mention at the end of a sentence', function (): void {
    Event::fake([CommentMentioned::class]);
    $alice = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'alice' ? $alice : null);

    $comment = Comments::on(PostTestModel::create())->body('Great point, thanks @alice.')->post();

    expect($comment->mentions()->pluck('handle')->all())->toBe(['alice']);
    Event::assertDispatchedTimes(CommentMentioned::class, 1);
});

it('keeps dots inside a handle but trims trailing punctuation', function (): void {
    $comment = Comments::on(PostTestModel::create())->body('ping @john.doe, @jane- and @bob_99...')->post();

    expect($comment->mentions()->pluck('handle')->all())->toEqualCanonicalizing(['john.doe', 'jane', 'bob_99']);
});

it('does not notify for a comment the blocklist hid', function (): void {
    Event::fake([CommentMentioned::class]);
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'hidden');
    $alice = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'alice' ? $alice : null);

    $comment = Comments::on(PostTestModel::create())->body('@alice casino bonus')->post();

    expect($comment->status)->toBe(CommentStatus::Hidden)
        ->and($comment->mentions()->sole()->mentionable?->is($alice))->toBeTrue();
    Event::assertNotDispatched(CommentMentioned::class);
});

it('notifies a held comment\'s mentions once, when it is approved', function (): void {
    Event::fake([CommentMentioned::class]);
    config()->set('comments.require_approval', true);
    $alice = ActorTestModel::create();
    $bob = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => match ($handle) {
        'alice' => $alice,
        'bob' => $bob,
        default => null,
    });

    $comment = Comments::on(PostTestModel::create())->body('hi @alice')->post();
    Comments::update($comment, 'hi @alice and @bob');

    Event::assertNotDispatched(CommentMentioned::class);

    Comments::approve($comment);

    Event::assertDispatchedTimes(CommentMentioned::class, 2);

    Comments::hide($comment);
    Comments::approve($comment);
    Comments::update($comment, 'hi @alice and @bob again');

    Event::assertDispatchedTimes(CommentMentioned::class, 2);
});

it('does not re-notify someone re-mentioned under another handle after approval', function (): void {
    $alice = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => strtolower($handle) === 'alice' ? $alice : null);

    $comment = Comments::on(PostTestModel::create())->body('hi @alice')->post();
    Comments::update($comment, 'hi @Alice');

    Event::fake([CommentMentioned::class]);

    Comments::hide($comment);
    Comments::approve($comment);

    Event::assertNotDispatched(CommentMentioned::class);
});

it('notifies nobody when a concurrent approval already claimed the mention', function (): void {
    config()->set('comments.require_approval', true);
    $alice = ActorTestModel::create();
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'alice' ? $alice : null);
    $comment = Comments::on(PostTestModel::create())->body('hi @alice')->post();

    Event::fake([CommentMentioned::class]);

    // Another worker stamps the row right after this approval read it.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced): void {
        if (! $raced && str_contains($query->sql, 'comment_mentions') && str_contains($query->sql, 'is not null')) {
            $raced = true;
            CommentMention::query()->update(['notified_at' => now()]);
        }
    });

    Comments::approve($comment);

    expect($raced)->toBeTrue();
    Event::assertNotDispatched(CommentMentioned::class);
});
