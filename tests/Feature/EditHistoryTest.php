<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Comments\Actions\UpdateCommentAction;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Http\Resources\CommentEditorResource;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentRevision;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\Fixtures\AbstractAuthorResource;
use RoundlyConsulting\Comments\Tests\Fixtures\HostAuthorResource;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\Support\Racer;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * Edit history (`comments.edit_history`, off by default). An edit used to overwrite the body
 * with nothing left of what it replaced; with the switch on each edit keeps the replaced body
 * as a numbered revision, with the editor and the time.
 */
afterEach(function (): void {
    Relation::morphMap([], false);
    Carbon::setTestNow();
});

function historyComment(string $body = 'Original'): Comment
{
    return Comments::on(PostTestModel::create())->as(ActorTestModel::create())->body($body)->post();
}

/**
 * @return array<string, mixed>
 */
function historyPayload(Comment $comment): array
{
    $json = (string) (new CommentResource($comment))->response()->getContent();

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR)['data'];
}

it('keeps no history while the switch is off — an edit overwrites the body, as before', function (): void {
    $comment = historyComment();

    Comments::update($comment, 'Edited', ActorTestModel::create());

    expect($comment->fresh()->comment)->toBe('Edited')
        ->and(CommentRevision::query()->count())->toBe(0)
        ->and(Comments::history($comment))->toBeEmpty();
});

it('keeps each replaced body with its editor and time, numbered in order', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    $editor = ActorTestModel::create();

    Carbon::setTestNow('2026-11-09 10:00:00');
    Comments::update($comment, 'Second', $editor);
    Carbon::setTestNow('2026-11-09 11:00:00');
    Comments::update($comment, 'Third');

    $history = Comments::history($comment);

    expect($comment->fresh()->comment)->toBe('Third')
        ->and($history->pluck('revision')->all())->toBe([1, 2])
        ->and($history->pluck('body')->all())->toBe(['Original', 'Second'])
        ->and($history[0]->editor->is($editor))->toBeTrue()
        ->and($history[0]->editedAt()?->toIso8601String())->toBe('2026-11-09T10:00:00+00:00')
        ->and($history[1]->editor)->toBeNull()
        ->and($history[1]->editedAt()?->toIso8601String())->toBe('2026-11-09T11:00:00+00:00')
        ->and($history[0]->comment->is($comment))->toBeTrue();
});

it('records no revision for an edit that leaves the body as it is', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();

    Comments::update($comment, '  Original  ');

    expect(Comments::history($comment))->toBeEmpty();
});

it('records nothing when the edit is refused', function (): void {
    config()->set('comments.edit_history', true);
    config()->set('comments.blocklist', ['spam']);
    $comment = historyComment();

    expect(fn () => Comments::update($comment, 'buy spam'))->toThrow(Exception::class)
        ->and(CommentRevision::query()->count())->toBe(0)
        ->and($comment->fresh()->comment)->toBe('Original');
});

it('still applies the blocklist hold to a recorded edit', function (): void {
    config()->set('comments.edit_history', true);
    config()->set('comments.blocklist', ['spam']);
    config()->set('comments.blocklist_action', 'pending');
    $comment = historyComment();

    Comments::update($comment, 'now with spam');

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending)
        ->and(Comments::history($comment)->pluck('body')->all())->toBe(['Original']);
});

it('passes the editor through the action, the injected manager and the facade alike', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    $editor = ActorTestModel::create();

    app(UpdateCommentAction::class)->execute(new UpdateCommentData($comment, 'By action', $editor));
    app(CommentsManager::class)->update($comment, 'By manager', $editor);

    expect(app(CommentsManager::class)->history($comment)->pluck('editor_id')->all())
        ->toBe([$editor->getKey(), $editor->getKey()]);
});

/*
 * The race. Two edits of one comment, each from a model loaded before the other landed. Without
 * the row lock + committed read, the second edit records the body ITS model was loaded with
 * ('Original') — the first edit's body is lost from history, and both claim revision 1.
 */
it('lands two interleaved edits in the history, in order, from stale models', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();

    $first = Comment::query()->findOrFail($comment->getKey());
    $second = Comment::query()->findOrFail($comment->getKey());

    Comments::update($first, 'Edit A');
    Comments::update($second, 'Edit B');

    expect($comment->fresh()->comment)->toBe('Edit B')
        ->and(Comments::history($comment)->map(fn (CommentRevision $r): array => [$r->revision, $r->body])->all())
        ->toBe([[1, 'Original'], [2, 'Edit A']]);
});

it('writes an edit back over a concurrent one even when the stale model already held that body', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment('Same');

    $stale = Comment::query()->findOrFail($comment->getKey());
    Comments::update($comment, 'Changed');

    // `$stale` still holds 'Same'; a dirty check against it would skip the write.
    Comments::update($stale, 'Same');

    expect($comment->fresh()->comment)->toBe('Same')
        ->and(Comments::history($comment)->pluck('body')->all())->toBe(['Same', 'Changed']);
});

it('reads the comment under a row lock inside the edit transaction', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();

    $connection = DB::connection();
    $grammar = $connection->getQueryGrammar();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::flush();
    LockRecorder::listenForMarkers();

    try {
        Comments::update($comment, 'Locked edit');
    } finally {
        $connection->setQueryGrammar($grammar);
    }

    $locks = LockRecorder::recorded();

    expect($locks)->toHaveCount(1)
        ->and($locks[0]['marker'])->toBe('lock-for-update')
        ->and($locks[0]['sql'])->toContain('"comments"')
        ->and($locks[0]['transactionDepth'])->toBe(1);
})->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'the lock recorder is sqlite-only; real engines take the real lock');

it('takes no lock while the switch is off', function (): void {
    $comment = historyComment();

    $connection = DB::connection();
    $grammar = $connection->getQueryGrammar();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::flush();
    LockRecorder::listenForMarkers();

    try {
        Comments::update($comment, 'Plain edit');
    } finally {
        $connection->setQueryGrammar($grammar);
    }

    expect(LockRecorder::recorded())->toBe([]);
})->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'the lock recorder is sqlite-only');

it('refuses a second revision with the same number', function (): void {
    $comment = historyComment();
    CommentRevision::factory()->for($comment, 'comment')->create(['revision' => 1]);

    expect(fn () => CommentRevision::factory()->for($comment, 'comment')->create(['revision' => 1]))
        ->toThrow(QueryException::class);
});

it('still edits a comment whose row is gone, recording nothing', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    Comment::query()->whereKey($comment->getKey())->forceDelete();

    Comments::update($comment, 'Into the void');

    expect(CommentRevision::query()->count())->toBe(0);
});

it('renders the history with the editor as id + class basename, never a class name', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    $editor = ActorTestModel::create();
    Comments::update($comment, 'Edited', $editor);

    $revision = CommentRevision::query()->firstOrFail();
    expect($revision->editor_type)->toBe(ActorTestModel::class);

    $json = (string) (new CommentResource($comment->fresh()->load('revisions.editor')))->response()->getContent();
    $history = json_decode($json, true)['data']['history'];

    expect($history)->toHaveCount(1)
        ->and($history[0])->toMatchArray([
            'revision' => 1,
            'body' => 'Original',
            'editor' => ['id' => $editor->getKey(), 'type' => 'ActorTestModel'],
        ])
        ->and($history[0]['edited_at'])->toBeString()
        ->and($json)->not->toContain('\\\\');
});

it('omits the editor when it is not loaded, renders a deleted editor as null', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    $editor = ActorTestModel::create();
    Comments::update($comment, 'Edited', $editor);

    expect(historyPayload($comment->fresh()->load('revisions'))['history'][0])->not->toHaveKey('editor');

    $editor->delete();

    expect(historyPayload($comment->fresh()->load('revisions.editor'))['history'][0]['editor'])->toBeNull();
});

it('omits the history when revisions are not loaded', function (): void {
    expect(historyPayload(historyComment()))->not->toHaveKey('history');
});

it('renders the editor through the host resource set in comments.resources.editor', function (): void {
    config()->set('comments.edit_history', true);
    config()->set('comments.resources.editor', HostAuthorResource::class);
    $comment = historyComment();
    $editor = ActorTestModel::create();
    Comments::update($comment, 'Edited', $editor);

    expect(historyPayload($comment->fresh()->load('revisions.editor'))['history'][0]['editor'])
        ->toBe(['id' => $editor->getKey(), 'display_name' => 'Actor #'.$editor->getKey()]);
});

it('falls back to the packaged editor resource on a blank value', function (mixed $blank): void {
    config()->set('comments.resources.editor', $blank);

    expect(CommentsConfig::editorResource())->toBe(CommentEditorResource::class);
})->with([null, '', '   ']);

it('refuses an editor resource that is not an instantiable JsonResource, naming the key', function (mixed $value): void {
    config()->set('comments.resources.editor', $value);

    expect(fn () => CommentsConfig::editorResource())
        ->toThrow(InvalidConfigurationException::class, 'comments.resources.editor');
})->with([
    'a non-resource class' => [stdClass::class],
    'the bare base' => [JsonResource::class],
    'an abstract resource' => [AbstractAuthorResource::class],
    'not a class' => ['NoSuchResource'],
    'an array' => [['x']],
]);

it('renders the packaged editor resource on its own', function (): void {
    $editor = ActorTestModel::create();

    expect((new CommentEditorResource($editor))->resolve(Request::create('/')))
        ->toBe(['id' => $editor->getKey(), 'type' => 'ActorTestModel']);
});

it('reads a non-boolean edit_history switch as a config error', function (): void {
    config()->set('comments.edit_history', 'sometimes');

    expect(fn () => Comments::update(historyComment(), 'x'))->toThrow(InvalidConfigurationException::class, 'comments.edit_history');
});

/*
 * The same race on a real engine, with two sessions: the parent holds its edit's transaction
 * open while a forked racer edits the same comment. The racer must wait on the row lock, then
 * read the parent's committed body as the one it replaces.
 */
it('serializes two concurrent edits on a real engine, both landing in order', function (): void {
    config()->set('comments.edit_history', true);
    $comment = historyComment();
    $racer = new Racer;

    DB::transaction(function () use ($comment, $racer): void {
        Comments::update($comment, 'Parent edit');

        $racer->run(static function () use ($comment): string {
            Comments::update(Comment::on('racer')->findOrFail($comment->getKey()), 'Racer edit');

            return 'edited';
        })->waitUntilBlockedOrDone();
    });

    expect($racer->outcome())->toBe('edited')
        ->and($comment->fresh()->comment)->toBe('Racer edit')
        ->and(Comments::history($comment)->map(fn (CommentRevision $r): array => [$r->revision, $r->body])->all())
        ->toBe([[1, 'Original'], [2, 'Parent edit']]);
})->skip(fn (): bool => ! Racer::available(), 'needs a real engine and pcntl')->group('real-engine');
