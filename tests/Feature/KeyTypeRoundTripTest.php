<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Tests\UlidKeyedTestModel;
use RoundlyConsulting\Comments\Tests\UuidKeyedTestModel;

/**
 * `comments.key_type` = uuid/ulid, end to end. MorphKeyTypeTest proves the columns follow
 * the config; this proves the write flow stores what those columns expect — the subject's
 * own key, never an integer squash of it (`(int) '0199…'` is `199`, and a locked uuid
 * subject was then looked up by the wrong id and never read as locked).
 */
dataset('non-integer key types', [
    'uuid' => ['uuid', UuidKeyedTestModel::class],
    'ulid' => ['ulid', UlidKeyedTestModel::class],
]);

it('stores and reads back a comment on a non-integer keyed subject', function (string $keyType, string $model): void {
    migrateCommentsKeyedBy($keyType, $model);

    $subject = $model::query()->create();
    $author = $model::query()->create();

    $comment = Comments::on($subject)->as($author)->body('hello')->post();

    expect($comment->fresh()?->commentable_id)->toBe($subject->getKey())
        ->and($comment->fresh()?->actor_id)->toBe($author->getKey())
        ->and($comment->fresh()?->commentable?->is($subject))->toBeTrue()
        ->and($subject->comments()->count())->toBe(1)
        ->and(Comments::for($subject)->count())->toBe(1)
        ->and($author->writtenComments()->count())->toBe(1);
})->with('non-integer key types');

it('keeps a reply on the non-integer keyed root subject', function (string $keyType, string $model): void {
    migrateCommentsKeyedBy($keyType, $model);

    $subject = $model::query()->create();
    $root = Comments::on($subject)->body('root')->post();

    $reply = Comments::on($subject)->reply($root)->body('reply')->post();

    expect($reply->fresh()?->commentable_id)->toBe($subject->getKey())
        ->and($subject->comments()->count())->toBe(2);
})->with('non-integer key types');

it('refuses a comment on a locked non-integer keyed subject', function (string $keyType, string $model): void {
    migrateCommentsKeyedBy($keyType, $model);

    $subject = $model::query()->create();
    Comments::lock($subject);

    expect(Comments::isLocked($subject))->toBeTrue()
        ->and(fn () => Comments::on($subject)->body('nope')->post())
        ->toThrow(CommentsLockedException::class);
})->with('non-integer key types');

it('transfers comments between non-integer keyed subjects', function (string $keyType, string $model): void {
    migrateCommentsKeyedBy($keyType, $model);

    $from = $model::query()->create();
    $to = $model::query()->create();
    $root = Comments::on($from)->body('root')->post();
    Comments::on($from)->reply($root)->body('reply')->post();

    expect(Comments::transfer($from, $to))->toBe(2)
        ->and($root->fresh()?->commentable_id)->toBe($to->getKey())
        ->and($to->comments()->count())->toBe(2)
        ->and($from->comments()->count())->toBe(0);

    // A locked uuid/ulid target is read by its own key, never an integer squash of it.
    Comments::lock($from);

    expect(fn () => Comments::transfer($to, $from))->toThrow(CommentsLockedException::class);
})->with('non-integer key types');
