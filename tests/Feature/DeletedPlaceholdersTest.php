<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Placeholder mode (`comments.deleted_comments = placeholder`, default `remove`). A deleted
 * comment used to drop out of every read — and the replies under it with it. In placeholder
 * mode it keeps its place in the public thread, rendered without anything it said.
 */

/**
 * A root with a reply and a grand-reply, the root deleted.
 *
 * @return array{post: PostTestModel, root: Comment, reply: Comment, grandReply: Comment}
 */
function threadWithDeletedRoot(): array
{
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $root = Comments::on($post)->as($actor)->body('Secret root')->post();
    $reply = Comments::on($post)->as($actor)->body('Reply')->reply($root)->post();
    $grandReply = Comments::on($post)->as($actor)->body('Grand reply')->reply($reply)->post();

    Comments::delete($root);

    return ['post' => $post, 'root' => $root, 'reply' => $reply, 'grandReply' => $grandReply];
}

/**
 * @return array<string, mixed>
 */
function placeholderPayload(Comment $comment): array
{
    $json = (string) (new CommentResource($comment))->response()->getContent();

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR)['data'];
}

it('drops a deleted comment and its replies from the thread by default, as before', function (): void {
    ['post' => $post] = threadWithDeletedRoot();

    expect($post->threadedComments)->toBeEmpty()
        ->and($post->approvedComments)->toBeEmpty()
        ->and(CommentsConfig::deletedComments())->toBe('remove');
});

it('keeps a deleted comment in the thread with its replies in placeholder mode', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    ['post' => $post, 'root' => $root, 'reply' => $reply, 'grandReply' => $grandReply] = threadWithDeletedRoot();

    $thread = $post->threadedComments;

    expect($thread)->toHaveCount(1)
        ->and($thread[0]->is($root))->toBeTrue()
        ->and($thread[0]->isPlaceholder())->toBeTrue()
        ->and($thread[0]->replies)->toHaveCount(1)
        ->and($thread[0]->replies[0]->is($reply))->toBeTrue()
        ->and($thread[0]->replies[0]->replies[0]->is($grandReply))->toBeTrue()
        ->and($post->approvedComments)->toHaveCount(1);
});

it('keeps a deleted reply in place too, with the replies below it', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    $post = PostTestModel::create();
    $root = Comments::on($post)->body('Root')->post();
    $reply = Comments::on($post)->body('Deleted reply')->reply($root)->post();
    Comments::on($post)->body('Under the deleted reply')->reply($reply)->post();
    Comments::delete($reply);

    $loaded = $post->threadedComments[0];

    expect($loaded->replies)->toHaveCount(1)
        ->and($loaded->replies[0]->isPlaceholder())->toBeTrue()
        ->and($loaded->replies[0]->replies[0]->comment)->toBe('Under the deleted reply');
});

it('still keeps held deleted comments out of the public thread', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    $post = PostTestModel::create();
    $hidden = Comments::on($post)->body('Hidden')->post();
    Comments::hide($hidden);
    Comments::delete($hidden);

    expect($post->threadedComments)->toBeEmpty();
});

it('does not count a placeholder', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    ['post' => $post] = threadWithDeletedRoot();

    expect($post->loadCommentCount()->comments_count)->toBe(2);
});

it('renders a placeholder without its body, author, attachments, likes or history', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    ['post' => $post] = threadWithDeletedRoot();

    $root = $post->threadedComments()->with(['actor', 'revisions', 'likes', 'media'])->firstOrFail();
    $payload = placeholderPayload($root);
    $json = (string) (new CommentResource($root))->response()->getContent();

    expect($payload['deleted'])->toBeTrue()
        ->and($payload['body'])->toBeNull()
        ->and($payload['author'])->toBeNull()
        ->and($payload)->not->toHaveKeys(['likes', 'attachments', 'history', 'meta'])
        ->and($payload['replies'][0]['body'])->toBe('Reply')
        ->and($payload['replies'][0]['deleted'])->toBeFalse()
        ->and($json)->not->toContain('Secret root');
});

it('renders a live comment in placeholder mode as usual, flagged not deleted', function (): void {
    config()->set('comments.deleted_comments', 'placeholder');
    $comment = Comments::on(PostTestModel::create())->as(ActorTestModel::create())->body('Live')->post();

    $payload = placeholderPayload($comment->load('actor'));

    expect($payload['deleted'])->toBeFalse()
        ->and($payload['body'])->toBe('Live')
        ->and($payload['author']['type'])->toBe('ActorTestModel');
});

it('renders a deleted comment with its body and no deleted flag while placeholder mode is off', function (): void {
    ['root' => $root] = threadWithDeletedRoot();

    $payload = placeholderPayload(Comment::withTrashed()->findOrFail($root->getKey()));

    expect($payload['body'])->toBe('Secret root')
        ->and($payload)->not->toHaveKey('deleted')
        ->and($root->isPlaceholder())->toBeFalse();
});

it('keeps deleted comments in a query on request, roots and replies alike', function (): void {
    ['post' => $post, 'root' => $root] = threadWithDeletedRoot();

    expect(Comments::for($post)->rootsOnly()->get())->toBeEmpty();

    $thread = Comments::for($post)->rootsOnly()->visible()->withReplies()->withDeleted()->get();

    expect($thread)->toHaveCount(1)
        ->and($thread[0]->is($root))->toBeTrue()
        ->and($thread[0]->replies[0]->replies)->toHaveCount(1);
});

it('applies withDeleted() to the replies whichever order it is called in', function (): void {
    $post = PostTestModel::create();
    $root = Comments::on($post)->body('Root')->post();
    $reply = Comments::on($post)->body('Deleted reply')->reply($root)->post();
    Comments::delete($reply);

    $before = Comments::for($post)->rootsOnly()->withDeleted()->withReplies()->get();
    $after = Comments::for($post)->rootsOnly()->withReplies()->withDeleted()->get();
    $without = Comments::for($post)->rootsOnly()->withReplies()->get();

    expect($before[0]->replies)->toHaveCount(1)
        ->and($after[0]->replies)->toHaveCount(1)
        ->and($without[0]->replies)->toBeEmpty();
});

it('moderates only live comments in bulk, even with withDeleted()', function (): void {
    ['post' => $post] = threadWithDeletedRoot();
    Comments::fake();

    $affected = Comments::for($post)->withDeleted()->hideAll();

    expect($affected)->toBe(2)
        ->and(Comment::withTrashed()->where('status', CommentStatus::Hidden)->count())->toBe(2)
        ->and(Comment::onlyTrashed()->firstOrFail()->status)->toBe(CommentStatus::Approved);

    Comments::assertHidden();
});

it('rejects an unknown deleted_comments value, naming the key', function (): void {
    config()->set('comments.deleted_comments', 'tombstone');

    expect(fn () => PostTestModel::create()->threadedComments)
        ->toThrow(InvalidConfigurationException::class, 'comments.deleted_comments');
});

it('reads a blank deleted_comments value as the default', function (): void {
    config()->set('comments.deleted_comments', '');

    expect(CommentsConfig::deletedComments())->toBe('remove')
        ->and(CommentsConfig::deletedCommentsAsPlaceholders())->toBeFalse();
});
