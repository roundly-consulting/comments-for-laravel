<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Tests\FactorylessUserTestModel;
use RoundlyConsulting\Comments\Tests\MisfactoredUserTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;

/**
 * A host's own tests call the shipped factories bare. The comments table needs a subject, and
 * the package ships no commentable model, so a bare comment is written on a fresh user of the
 * configured auth model, built through that model's own factory. Without one, the factory says
 * how to give the comment a subject instead of failing on the NOT NULL column.
 */
beforeEach(function (): void {
    config()->set('auth.providers.users.model', UserTestModel::class);
});

it('writes a bare comment on a fresh user of the auth model', function (): void {
    $comment = Comment::factory()->create();

    expect($comment->commentable)->toBeInstanceOf(UserTestModel::class)
        ->and($comment->commentable_type)->toBe((new UserTestModel)->getMorphClass())
        ->and(UserTestModel::query()->count())->toBe(1);
});

it('creates a bare mention on a bare comment', function (): void {
    $mention = CommentMention::factory()->create();

    expect($mention->comment)->toBeInstanceOf(Comment::class)
        ->and($mention->comment?->commentable)->toBeInstanceOf(UserTestModel::class);
});

it('gives each bare comment its own subject', function (): void {
    Comment::factory()->count(3)->create();

    expect(UserTestModel::query()->count())->toBe(3);
});

it('recycles a given user as the default subject', function (): void {
    $user = UserTestModel::query()->create();

    $comments = Comment::factory()->recycle($user)->count(2)->create();

    expect($comments->every(fn (Comment $comment): bool => $comment->commentable?->is($user) === true))->toBeTrue()
        ->and(UserTestModel::query()->count())->toBe(1);
});

it('creates no default subject when the comment is given one', function (Closure $build): void {
    $post = PostTestModel::query()->create();

    $comment = $build($post);

    expect($comment->commentable)->toBeInstanceOf(PostTestModel::class)
        ->and(UserTestModel::query()->count())->toBe(0);
})->with([
    'on()' => fn (PostTestModel $post): Comment => Comment::factory()->on($post)->create(),
    'for()' => fn (PostTestModel $post): Comment => Comment::factory()->for($post, 'commentable')->create(),
    'reply()' => fn (PostTestModel $post): Comment => Comment::factory()
        ->reply(Comment::factory()->on($post)->create())
        ->create(),
    'attributes' => fn (PostTestModel $post): Comment => Comment::factory()->create([
        'commentable_type' => $post->getMorphClass(),
        'commentable_id' => $post->getKey(),
    ]),
]);

it('creates no comment for a mention given its comment', function (): void {
    $comment = Comment::factory()->on(PostTestModel::query()->create())->create();

    CommentMention::factory()->create(['comment_id' => $comment->getKey()]);

    expect(Comment::query()->count())->toBe(1)
        ->and(UserTestModel::query()->count())->toBe(0);
});

it('asks for a subject when the auth model has no factory', function (string $model, string $reason): void {
    config()->set('auth.providers.users.model', $model);

    expect(fn (): Comment => Comment::factory()->create())
        ->toThrow(LogicException::class, 'Comment::factory()->on($subject)')
        ->and(fn (): Comment => Comment::factory()->create())
        ->toThrow(LogicException::class, $reason);

    expect(Comment::query()->count())->toBe(0);
})->with([
    'no HasFactory' => [User::class, 'has no factory (it does not use HasFactory)'],
    'no factory class' => [FactorylessUserTestModel::class, '::factory() failed: Class'],
    'factory() returns no factory' => [MisfactoredUserTestModel::class, 'returned no Eloquent factory'],
]);

it('asks for a subject when no auth model is configured', function (mixed $model): void {
    config()->set('auth.providers.users.model', $model);

    expect(fn (): CommentMention => CommentMention::factory()->create())
        ->toThrow(LogicException::class, 'no Eloquent auth user model');
})->with([
    'unset' => null,
    'not a class' => 'App\Models\Nobody',
    'not a model' => stdClass::class,
]);

it('reads the auth model of the default guard', function (): void {
    config()->set('auth.providers.users.model', User::class);
    config()->set('auth.providers.members', ['driver' => 'eloquent', 'model' => UserTestModel::class]);
    config()->set('auth.guards.members', ['driver' => 'session', 'provider' => 'members']);
    config()->set('auth.defaults.guard', 'members');

    expect(Comment::factory()->create()->commentable)->toBeInstanceOf(UserTestModel::class);
});

it('asks for a subject when the auth model is outside an enforced morph map', function (): void {
    Relation::enforceMorphMap(['post' => PostTestModel::class]);

    try {
        expect(fn (): Comment => Comment::factory()->create())
            ->toThrow(LogicException::class, 'enforced morph map');
    } finally {
        Relation::requireMorphMap(false);
        Relation::morphMap([], false);
    }
});
