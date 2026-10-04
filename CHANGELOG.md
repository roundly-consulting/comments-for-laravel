# Changelog

All notable changes to `comments-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
