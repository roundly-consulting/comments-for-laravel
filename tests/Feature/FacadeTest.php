<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\CommentBuilder;
use RoundlyConsulting\Comments\CommentManager;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Events\CommentDeleted;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('resolves the manager as a singleton', function (): void {
    expect(app(CommentManager::class))->toBe(app(CommentManager::class));
});

it('starts a fluent builder from the facade', function (): void {
    $post = PostTestModel::create();

    expect(Comments::on($post))->toBeInstanceOf(CommentBuilder::class);
});

it('posts a comment through the fluent builder', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = Comments::on($post)
        ->as($actor)
        ->body('Fluent comment')
        ->post();

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->comment)->toBe('Fluent comment')
        ->and($comment->actor_id)->toBe($actor->getKey());
});

it('posts a hidden reply through the fluent builder', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $root = Comments::on($post)->as($actor)->body('Root')->post();

    $reply = Comments::on($post)
        ->as($actor)
        ->reply($root)
        ->visible(false)
        ->body('Reply')
        ->post();

    expect($reply->parent_id)->toBe($root->getKey())
        ->and($reply->visible)->toBeFalse();
});

it('writes via the DTO escape hatch on the facade', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::write(new WriteCommentData(
        commentable: $post,
        body: 'From DTO',
    ));

    expect($comment->comment)->toBe('From DTO');
});

it('delegates moderation methods to the actions', function (): void {
    Event::fake();
    $comment = Comment::factory()
        ->for(ActorTestModel::create(), 'actor')
        ->for(PostTestModel::create(), 'commentable')
        ->create();

    Comments::update($comment, 'Edited');
    Comments::hide($comment);
    Comments::approve($comment);

    expect($comment->refresh()->comment)->toBe('Edited')
        ->and($comment->status)->toBe(CommentStatus::Approved);

    Event::assertDispatched(CommentUpdated::class);
    Event::assertDispatched(CommentHidden::class);
    Event::assertDispatched(CommentApproved::class);
});

it('deletes and restores through the facade', function (): void {
    Event::fake();
    $comment = Comment::factory()
        ->for(ActorTestModel::create(), 'actor')
        ->for(PostTestModel::create(), 'commentable')
        ->create();

    Comments::delete($comment);
    expect(Comment::query()->count())->toBe(0);

    Comments::restore($comment);
    expect(Comment::query()->count())->toBe(1);

    Event::assertDispatched(CommentDeleted::class);
});
