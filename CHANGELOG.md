# Changelog

All notable changes to `comments-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Polymorphic comments on any model: `HasComments` for commentable models, `GivesComments` for
  authors, plus anonymous guest comments.
- Fluent `Comments::on($post)->as($user)->body(...)->post()` builder, `WriteCommentAction` with a
  typed DTO, and edit, soft-delete and restore.
- Threaded replies (`reply()`, `threadedComments()`).
- Moderation workflow — pending, approved, hidden — with scopes and bulk moderation
  (`Comments::for($post)->pending()->approveAll()`).
- An event for every step of the comment lifecycle.
- Likes, typed reactions and ranking (`orderByLikesDesc()`, `orderByTrending()`), built on
  likes-for-laravel.
- Report-a-comment with auto-hide and approval-based moderation, built on reports-for-laravel.
- Media attachments with inline `[media:UUID]` rendering, built on media-library-for-laravel.
- `@mention` parsing with a `CommentMentioned` event, and a configurable blocklist filter.
- Thread and reply locking (`Comments::lock()`, `lockReplies()`).
- N+1-free comment counts (`withCommentCounts()`), an opt-in `CommentPolicy` and a
  `CommentResource` API resource.
- Factory states and `AssertsComments` test assertions.
