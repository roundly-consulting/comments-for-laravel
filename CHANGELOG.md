# Changelog

All notable changes to `comments-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- With `comments.authorization` on, writing a comment now consults the `Comment` policy's
  `create` ability. The write flow asked the Gate for a bare `create` ability with no model, so
  the policy was never reached: every write was denied (even under the shipped permissive
  policy) unless the host happened to define a global `create` gate, which then decided instead.
  `create` now also receives the subject being commented on (the root subject for a reply).
- With `comments.key_type` set to `uuid` or `ulid`, writing a comment now stores the subject's
  real key. The write flow cast it to an integer, so a uuid/ulid subject was stored as `0` or a
  digit prefix (`'0199…'` → `199`): its comments never showed up on it, a Postgres uuid column
  rejected the write outright, and a locked subject was looked up by the wrong id and never read
  as locked.
- Private attachments (the default `comments.media.visibility`) are now served through
  short-lived signed URLs everywhere. `attachmentUrls()`, inline `[media:UUID]` tokens in
  `renderBody()` and `CommentResource`'s `attachments[].url` asked media-library for the
  attachment's public URL, which it refuses for private media — so on the default config each of
  them threw `MediaCannotBeStreamed`. They now go through the new `resolveAttachmentUrl()`
  (public URL for public media, signed URL for private media). The config and README also warn
  that the default media disk (`public`) is web-served, so private attachments belong on a
  non-public disk.
