<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Contracts\MentionableGroup;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\Fixtures\MentionGroupFixture;
use RoundlyConsulting\Comments\Tests\PostTestModel;

/**
 * Group mentions. The resolver could only answer one model per handle; returning a
 * MentionableGroup now expands the handle to its members — each once, never the comment's own
 * author, and every member notified once however many handles reach them.
 */

/**
 * @param  array<string, MentionGroupFixture|ActorTestModel>  $map
 */
function resolveMentionsFrom(array $map): void
{
    config()->set('comments.mention_resolver', fn (string $handle): MentionGroupFixture|ActorTestModel|null => $map[$handle] ?? null);
}

/**
 * @return list<int>
 */
function mentionedActorIds(): array
{
    return CommentMention::query()->whereNotNull('mentionable_id')->orderBy('mentionable_id')
        ->pluck('mentionable_id')->map(fn (mixed $id): int => (int) $id)->all();
}

it('expands a group handle to its members, each stored once', function (): void {
    [$ann, $bob] = [ActorTestModel::create(), ActorTestModel::create()];
    resolveMentionsFrom(['team' => new MentionGroupFixture([$ann, $bob, $ann])]);

    $comment = Comments::on(PostTestModel::create())->body('ping @team')->post();

    expect($comment->mentions()->pluck('handle')->unique()->all())->toBe(['team'])
        ->and(mentionedActorIds())->toBe([$ann->getKey(), $bob->getKey()]);
});

it('leaves the comment author out of the group', function (): void {
    [$author, $bob] = [ActorTestModel::create(), ActorTestModel::create()];
    resolveMentionsFrom(['team' => new MentionGroupFixture([$author, $bob])]);
    Event::fake([CommentMentioned::class]);

    Comments::on(PostTestModel::create())->as($author)->body('ping @team')->post();

    expect(mentionedActorIds())->toBe([$bob->getKey()]);
    Event::assertDispatchedTimes(CommentMentioned::class, 1);
    Event::assertDispatched(CommentMentioned::class, fn (CommentMentioned $e): bool => $e->mention->mentionable->is($bob));
});

it('notifies a member reached through the group and by name only once', function (): void {
    [$ann, $bob] = [ActorTestModel::create(), ActorTestModel::create()];
    resolveMentionsFrom(['ann' => $ann, 'team' => new MentionGroupFixture([$ann, $bob])]);
    Event::fake([CommentMentioned::class]);

    Comments::on(PostTestModel::create())->body('@ann and @team')->post();

    Event::assertDispatchedTimes(CommentMentioned::class, 2);
    Event::assertDispatched(CommentMentioned::class, fn (CommentMentioned $e): bool => $e->mention->mentionable->is($ann));
    Event::assertDispatched(CommentMentioned::class, fn (CommentMentioned $e): bool => $e->mention->mentionable->is($bob));
});

it('stores the raw handle when the group has no one left to mention', function (bool $withAuthor): void {
    $author = ActorTestModel::create();
    resolveMentionsFrom(['team' => new MentionGroupFixture($withAuthor ? [$author] : [])]);

    $comment = Comments::on(PostTestModel::create())->as($author)->body('ping @team')->post();

    expect($comment->mentions()->count())->toBe(1)
        ->and($comment->mentions()->first()->mentionable_id)->toBeNull();
})->with([
    'an empty group' => [false],
    'only the author' => [true],
]);

it('keeps the group rows on an edit and drops them with the handle', function (): void {
    [$ann, $bob] = [ActorTestModel::create(), ActorTestModel::create()];
    resolveMentionsFrom(['team' => new MentionGroupFixture([$ann, $bob])]);
    Event::fake([CommentMentioned::class]);

    $comment = Comments::on(PostTestModel::create())->body('ping @team')->post();
    Comments::update($comment, 'ping @team again');

    expect($comment->mentions()->count())->toBe(2);
    Event::assertDispatchedTimes(CommentMentioned::class, 2);

    Comments::update($comment, 'never mind');

    expect($comment->mentions()->count())->toBe(0);
});

it('waits to notify a held comment, then notifies each member once on approval', function (): void {
    config()->set('comments.require_approval', true);
    [$ann, $bob] = [ActorTestModel::create(), ActorTestModel::create()];
    resolveMentionsFrom(['team' => new MentionGroupFixture([$ann, $bob])]);
    Event::fake([CommentMentioned::class]);

    $comment = Comments::on(PostTestModel::create())->body('ping @team')->post();
    Event::assertNotDispatched(CommentMentioned::class);

    Comments::approve($comment);
    Comments::approve($comment);

    Event::assertDispatchedTimes(CommentMentioned::class, 2);
});

it('treats a group model like any group', function (): void {
    $bob = ActorTestModel::create();
    $team = new class extends ActorTestModel implements MentionableGroup
    {
        /** @var list<ActorTestModel> */
        public array $members = [];

        public function mentionableMembers(): iterable
        {
            return $this->members;
        }
    };
    $team->members = [$bob];
    config()->set('comments.mention_resolver', fn (string $handle): ?ActorTestModel => $handle === 'team' ? $team : null);

    Comments::on(PostTestModel::create())->body('ping @team')->post();

    expect(mentionedActorIds())->toBe([$bob->getKey()]);
});

it('skips a member that has no key yet', function (): void {
    $bob = ActorTestModel::create();
    resolveMentionsFrom(['team' => new MentionGroupFixture([new ActorTestModel, $bob])]);

    Comments::on(PostTestModel::create())->body('ping @team')->post();

    expect(mentionedActorIds())->toBe([$bob->getKey()]);
});
