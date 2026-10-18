# Changelog

All notable changes to `comments-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- `CommentAuthorResource`, the JSON resource `CommentResource` renders a comment's author
  through: `{"id": …, "type": "User"}`.
- The `comments.resources.author` config key (default `CommentAuthorResource::class`). Point it at
  your own `JsonResource` to add a name, an avatar or anything else your API needs; it receives the
  author model. A value that is not an instantiable `JsonResource` subclass throws an
  `InvalidConfigurationException` naming the key.

### Changed

- `CommentResource`'s `author.type` is now the author's class basename (`User`), never its class
  name or morph alias. It used to be the stored `actor_type`, which on an app without a morph map
  is the full class name (`App\Models\User`) — handed to every API client. To render a different
  shape, swap the resource through `comments.resources.author`.

## 1.1.3 - 2026-10-17

### Changed

- Maintenance: dev: testing `^1.6`.
- Maintenance: CI runs the PostgreSQL and MySQL test legs on tmpfs.

### Fixed

- A bare `Comment::factory()->create()` or `CommentMention::factory()->create()` no longer fails
  with `NOT NULL constraint failed: comments.commentable_type`. A comment given no subject
  (`on($subject)`, `reply($parent)` or `for($subject, 'commentable')`) is written on a new user of
  your auth model, built through that model's own factory. If the auth model has no factory, or
  is missing from an enforced morph map, the factory throws a `LogicException` that tells you to
  use `on($subject)`.
- `CommentLock::factory()` now locks a real subject: the one you pass to the new `on($subject)`
  state or `for($subject, 'lockable')`, or else a new user of your auth model, as a bare comment
  gets. It used to lock a fixed `post` #1: PostgreSQL rejects that id in a uuid column, it means
  nothing under a ulid key, and a second lock clashed on the unique key. Without a usable auth
  model factory it throws the same kind of `LogicException`.

## 1.1.2 - 2026-10-17

### Fixed

- Factories build the configured model when a host swaps it.

## 1.1.1 - 2026-10-17

### Fixed

- `$mention->comment` now returns your configured `comments.model` class instead of the packaged
  `Comment`, so a swapped model's casts, accessors and relations apply when you read a comment
  from its mention.
- The `AssertsComments` helpers (`assertCommented()`, `assertNotCommented()`,
  `assertCommentCount()`) now query through the configured `comments.model`, so they agree with
  what your model sees — its table, connection and global scopes.

## 1.1.0 - 2026-10-17

### Added

- A nullable `meta` json column on comments (`jsonb` on PostgreSQL), cast to an array, for your own
  per-comment metadata. It ships as a new migration: run
  `php artisan vendor:publish --tag="comments-migrations"` again, then `php artisan migrate`. Comments
  keeps working without it until you use `meta`.
- Write metadata with `Comments::on($post)->meta([...])->post()` (repeated calls merge top-level
  keys) or `new WriteCommentData(..., meta: [...])`, and read it back with `$comment->meta('a.b',
  $default)` and `$comment->hasMeta('a.b')`. The factory gains a `meta([...])` state.
- Filter comments by metadata: `Comments::for($post)->whereMeta('tickets.role', 'agent')` and
  `->whereMetaMissing('tickets.internal')`, portable across SQLite, PostgreSQL and MySQL. Paths are
  lowercase dot notation; anything else throws `InvalidCommentMetaPathException`.
- `Comments::transfer($from, $to)` moves every comment of one subject onto another — replies,
  held, invisible and soft-deleted comments included — and returns how many moved, for merging
  duplicate records. Threads, mentions, likes, reports, attachments and thread locks travel with
  their comments. It refuses the same subject (`InvalidCommentTransferException`) and a locked
  target (`CommentsLockedException`), checks the policy's existing `create` ability on the target
  when `comments.authorization` is on, and fires `CommentsTransferred`. `Comments::fake()` records
  it: `assertTransferred()` / `assertNothingTransferred()`.
- `Comments::blocklisted($body)` tells you whether a body matches the configured blocklist, checked
  exactly as posting checks it. It never rejects, so it can tell a comment the blocklist held as
  `pending` apart from one waiting for `require_approval`.

### Changed

- Maintenance: CI gains a MySQL real-engine test leg.

## 1.0.2 - 2026-10-11

### Changed

- Maintenance: requires the latest roundly packages — package-toolkit `^1.3.0`, approvals `^1.2.3`,
  enums `^1.1.0`, likes `^1.1.0`, media-library `^1.1.2`, reports `^1.1.0`; dev: testing `^1.2.1`.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and other
  sites.

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Polymorphic comments on any model: `HasComments` for commentable models, `GivesComments` for
  authors, plus anonymous guest comments.
- Fluent `Comments::on($post)->as($user)->body(...)->post()` builder, `WriteCommentAction` with a
  typed DTO, and edit, soft-delete and restore.
- Threaded replies (`reply()`, `threadedComments()`); a reply to another subject's comment throws
  `InvalidCommentParentException`.
- Moderation workflow — pending, approved, hidden — with scopes and bulk moderation
  (`Comments::for($post)->pending()->approveAll()`).
- An event for every step of the comment lifecycle.
- Likes, typed reactions and ranking (`orderByLikesDesc()`, `orderByTrending()`), built on
  likes-for-laravel.
- Report-a-comment with auto-hide and approval-based moderation, built on reports-for-laravel.
- Media attachments with inline `[media:UUID]` rendering, built on media-library-for-laravel.
- `@mention` parsing with a `CommentMentioned` event, and a configurable blocklist filter.
- Subject and thread locking (`Comments::lock()`, `Comments::lockThread()`, `isThreadLocked()`),
  with `CommentThreadLocked` / `CommentThreadUnlocked` events; a thread lock covers the whole
  subtree under the comment.
- Site-wide moderation queries (`Comments::query()->pending()->mostReported()->paginate()`).
- An injectable `CommentsManager` behind the `Comments` facade, one action per operation.
- N+1-free comment counts (`withCommentCounts()`), an opt-in `CommentPolicy` and a
  `CommentResource` API resource.
- `Comments::fake()` — a recording `CommentsFake` with `assert*()` / `assertNothing*()` for every
  mutation, including those made through the builder, bulk moderation and the model traits.
- Factory states and `AssertsComments` test assertions.
