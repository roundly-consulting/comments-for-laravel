<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Testing\AssertsComments;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

uses(AssertsComments::class);

/**
 * The host-test assertion helpers read through the `comments.model` seam.
 *
 * They queried `Comment::query()` — the packaged class — so a host whose own comment model
 * narrows what it sees (a tenant scope, its own table or connection) got assertions that
 * disagreed with `$post->comments`: comments the host model hides counted, and `assertCommented`
 * passed on a row the host could never read. The global scope below stands in for that host
 * rule; Laravel clears booted models (and their scopes) when the next test boots its app.
 */
beforeEach(function (): void {
    CustomCommentTestModel::addGlobalScope(
        'host-tenant',
        fn (Builder $query): Builder => $query->where('comment', '!=', 'another tenant'),
    );
});

it('counts only the comments the configured model can see', function (): void {
    $post = PostTestModel::create();

    Comments::on($post)->body('mine')->post();
    Comments::on($post)->body('another tenant')->post();

    expect($post->comments()->count())->toBe(1);

    $this->assertCommented($post);
    $this->assertCommented($post, 'mine');
    $this->assertCommentCount($post, 1);
});

it('does not see a comment the configured model hides', function (): void {
    $post = PostTestModel::create();

    Comments::on($post)->body('another tenant')->post();

    $this->assertNotCommented($post);
    $this->assertCommentCount($post, 0);

    expect(fn () => $this->assertCommented($post, 'another tenant'))
        ->toThrow(ExpectationFailedException::class);
});
