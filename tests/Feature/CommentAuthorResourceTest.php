<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Comments\Http\Resources\CommentAuthorResource;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\Fixtures\AbstractAuthorResource;
use RoundlyConsulting\Comments\Tests\Fixtures\HostAuthorResource;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * No class names over the wire. `author.type` used to be the stored `actor_type` — on a host
 * without a morph map that is the author's PHP class name (`App\Models\User`), handed to every
 * API client. The author now renders through its own resource (`comments.resources.author`,
 * default CommentAuthorResource): `id` + the class basename, swappable by the host.
 */
afterEach(function (): void {
    Relation::morphMap([], false);
});

function commentWrittenBy(ActorTestModel $actor): Comment
{
    return Comment::factory()->for(PostTestModel::create(), 'commentable')->by($actor)->create();
}

/**
 * @return array<string, mixed>
 */
function commentPayload(Comment $comment): array
{
    $json = (string) (new CommentResource($comment))->response()->getContent();

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR)['data'];
}

it('renders the author as id + class basename, with no class name anywhere in the JSON', function (): void {
    $actor = ActorTestModel::create();
    $comment = commentWrittenBy($actor);

    // The leak's precondition, asserted so the test cannot pass vacuously: with no morph map
    // the stored morph type IS the author's fully-qualified class name.
    expect($comment->actor_type)->toBe(ActorTestModel::class);

    $json = (string) (new CommentResource($comment->load('actor')))->response()->getContent();

    expect(json_decode($json, true)['data']['author'])->toBe(['id' => $actor->getKey(), 'type' => 'ActorTestModel'])
        // A backslash inside any JSON string is encoded as `\\` — no namespaced class anywhere.
        ->and($json)->not->toContain('\\\\')
        ->and($json)->not->toContain(class_basename(PostTestModel::class));
});

it('renders the basename even when the host maps the author to a morph alias', function (): void {
    Relation::morphMap(['member' => ActorTestModel::class]);
    $actor = ActorTestModel::create();
    $comment = commentWrittenBy($actor);

    expect($comment->actor_type)->toBe('member')
        ->and(commentPayload($comment->load('actor'))['author'])->toBe(['id' => $actor->getKey(), 'type' => 'ActorTestModel']);
});

it('renders a deleted author as null', function (): void {
    $actor = ActorTestModel::create();
    $comment = commentWrittenBy($actor);
    $actor->delete();

    $payload = commentPayload($comment->load('actor'));

    expect($payload)->toHaveKey('author')
        ->and($payload['author'])->toBeNull();
});

it('omits the author when the actor relation is not loaded', function (): void {
    $comment = commentWrittenBy(ActorTestModel::create());

    expect(commentPayload($comment))->not->toHaveKey('author');
});

it('renders the author through the resource the host configured', function (): void {
    config()->set('comments.resources.author', HostAuthorResource::class);
    $actor = ActorTestModel::create();
    $comment = commentWrittenBy($actor);
    $reply = Comment::factory()->reply($comment)->by($actor)->create();

    $payload = commentPayload($comment->load(['actor', 'replies.actor']));

    expect($payload['author'])->toBe(['id' => $actor->getKey(), 'display_name' => 'Actor #'.$actor->getKey()])
        ->and($payload['replies'][0]['id'])->toBe($reply->getKey())
        ->and($payload['replies'][0]['author'])->toBe(['id' => $actor->getKey(), 'display_name' => 'Actor #'.$actor->getKey()]);
});

it('hands the author resource the actor model itself', function (): void {
    $actor = ActorTestModel::create();
    $resource = new CommentAuthorResource($actor);

    expect($resource->resource)->toBe($actor)
        ->and($resource->resolve(Request::create('/')))->toBe(['id' => $actor->getKey(), 'type' => 'ActorTestModel']);
});

it('defaults the author resource when the key is unset or blank', function (mixed $value): void {
    config()->set('comments.resources.author', $value);

    expect(CommentsConfig::authorResource())->toBe(CommentAuthorResource::class);
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['  '],
]);

it('ships CommentAuthorResource as the configured default', function (): void {
    expect(config('comments.resources.author'))->toBe(CommentAuthorResource::class)
        ->and(CommentsConfig::authorResource())->toBe(CommentAuthorResource::class);
});

it('refuses an author resource that is not a JsonResource subclass, naming the key', function (mixed $value, string $given): void {
    config()->set('comments.resources.author', $value);

    expect(fn () => CommentsConfig::authorResource())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.resources.author] must be a class-string of ['.JsonResource::class."], [{$given}] given.",
    );
})->with([
    'a plain class' => [stdClass::class, 'stdClass'],
    'a missing class' => ['App\Http\Resources\MissingAuthorResource', 'App\Http\Resources\MissingAuthorResource'],
    'the base class itself' => [JsonResource::class, JsonResource::class],
    'an abstract subclass' => [AbstractAuthorResource::class, AbstractAuthorResource::class],
    'an integer' => [42, '42'],
    'an array' => [[HostAuthorResource::class], 'array'],
]);

it('throws on a misconfigured author resource when it renders an author', function (): void {
    config()->set('comments.resources.author', stdClass::class);
    $comment = commentWrittenBy(ActorTestModel::create());

    expect(fn () => commentPayload($comment->load('actor')))
        ->toThrow(InvalidConfigurationException::class, 'comments.resources.author');
});
