<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\DenyingCommentPolicy;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Facades\Reports;

/**
 * Every switch is read as a boolean. env() only converts 'true'/'false', so
 * `COMMENTS_REQUIRE_APPROVAL=1` arrives as the STRING '1' and `=off` as 'off': the `about`
 * rows (`=== true`) read '1' as OFF while the behaviour (`(bool)`) read 'off' as ON.
 */
function commentsAbout(): string
{
    Artisan::call('about', ['--only' => 'comments']);

    return Artisan::output();
}

dataset('truthy strings', ['1', 'on', 'yes']);
dataset('falsy strings', ['0', 'off', 'no']);

it('holds new comments for approval when the switch is a truthy string', function (string $value): void {
    config()->set('comments.require_approval', $value);

    $comment = Comments::on(PostTestModel::create())->body('Hello')->post();

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and(commentsAbout())->toMatch('/Require approval\s*\.*\s*ON/');
})->with('truthy strings');

it('approves new comments when the switch is a falsy string', function (string $value): void {
    config()->set('comments.require_approval', $value);

    $comment = Comments::on(PostTestModel::create())->body('Hello')->post();

    expect($comment->status)->toBe(CommentStatus::Approved)
        ->and(commentsAbout())->toMatch('/Require approval\s*\.*\s*OFF/');
})->with('falsy strings');

it('consults the gate when authorization is a truthy string', function (string $value): void {
    config()->set('comments.authorization', $value);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    expect(fn () => Comments::on(PostTestModel::create())->body('Hello')->post())
        ->toThrow(UnauthorizedCommentActionException::class)
        ->and(commentsAbout())->toMatch('/Authorization\s*\.*\s*ON/');
})->with('truthy strings');

it('skips the gate when authorization is a falsy string', function (string $value): void {
    config()->set('comments.authorization', $value);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    $comment = Comments::on(PostTestModel::create())->body('Hello')->post();

    expect($comment->exists)->toBeTrue()
        ->and(commentsAbout())->toMatch('/Authorization\s*\.*\s*OFF/');
})->with('falsy strings');

it('leaves the comment visible on threshold when auto-hide is a falsy string', function (string $value): void {
    config()->set('reports.threshold', 2);
    config()->set('comments.moderation.auto_hide', $value);

    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();

    expect($comment->fresh()?->status)->toBe(CommentStatus::Approved)
        ->and(commentsAbout())->toMatch('/Auto-moderation\s*\.*\s*OFF/');
})->with('falsy strings');

it('reports auto-moderation and inline media on for truthy strings', function (string $value): void {
    config()->set('comments.moderation.auto_hide', $value);
    config()->set('comments.media.inline.enabled', $value);

    expect(commentsAbout())->toMatch('/Auto-moderation\s*\.*\s*ON/')
        ->toMatch('/Inline media\s*\.*\s*ON/');
})->with('truthy strings');

it('leaves inline tokens untouched when inline media is a falsy string', function (string $value): void {
    config()->set('comments.media.inline.enabled', $value);
    $body = 'see [media:00000000-0000-4000-8000-000000000000] here';

    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create(['comment' => $body]);

    expect((string) $comment->renderBody())->toBe($body)
        ->and(commentsAbout())->toMatch('/Inline media\s*\.*\s*OFF/');
})->with('falsy strings');

it('throws on a switch typo instead of reading it as the default (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.",
    );
})->with([
    'require_approval' => ['comments.require_approval', fn () => Comments::on(PostTestModel::create())->body('Hello')->post()],
    'authorization' => ['comments.authorization', fn () => Comments::on(PostTestModel::create())->body('Hello')->post()],
    'media.inline.enabled' => ['comments.media.inline.enabled', fn () => Comment::factory()->for(PostTestModel::create(), 'commentable')->create()->renderBody()],
    'moderation.auto_hide (about)' => ['comments.moderation.auto_hide', fn (): string => commentsAbout()],
]);
