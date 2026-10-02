<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/comments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel">
    <img src="art/hero.png" alt="Comments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/comments-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/comments-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/comments-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/comments-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/comments-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/comments-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Comments for Laravel

Attach polymorphic comments to any Laravel model. Any model can *give* comments and any
model can *receive* them, with first-class threaded replies, a built-in moderation workflow,
and a discoverable `Comments` facade. The package ships morph-based tables, opt-in traits,
typed actions/DTOs, and an event for every part of the comment lifecycle.

On top of the basics it adds **likes / upvotes** and typed reactions, **report-a-comment**
moderation with an auto-hide listener, **media attachments** with inline `[media:UUID]` body
rendering, `@mention` parsing, a configurable **blocklist** filter, thread/subject **locking**,
a fluent **reading & bulk-moderation** query, N+1-free **comment counts**, opt-in
**authorization** via a policy, ready-made **API Resources**, and a recording
**`Comments::fake()`** plus testing helpers for host apps.

The social + moderation + media features build on four sibling roundly packages — see
[Integrates with](#integrates-with).

## Requirements

- PHP 8.4 or higher
- Laravel 12 or 13
- The `likes`, `reports` (+ `approvals`), `media-library`, `enums`, and `package-toolkit` roundly
  packages — pulled in automatically as hard dependencies

## Installation

```bash
composer require roundly-consulting/comments-for-laravel
```

The package does **not** auto-load its migrations — you own them. Publish the migration into
your app, then run it:

```bash
php artisan vendor:publish --tag="comments-migrations"
php artisan migrate
```

The publish copies `create_comments_table` into `database/migrations` with a fresh timestamp;
republishing with `--force` overwrites that same file instead of adding a second copy. Without
the publish step, `php artisan migrate` will not create the `comments` table.

The likes, reports, approvals and media-library providers ship their own migrations (the
`likes`, `reports`, approval-engine, and `media` tables). Publish/run them the same way, per
each package's README, so comment likes, reports and attachments have somewhere to live.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="comments-config"
```

The package reports its active configuration (model, limits, moderation and media switches) to
Laravel's `about` command — the blocklist is reported as a count, never as terms:

```bash
php artisan about --only=comments
```

And, if you want to translate the package's messages, publish the language files:

```bash
php artisan vendor:publish --tag="comments-translations"
```

## Configuration

The published config file lives at `config/comments.php`:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;

return [
    'model' => Comment::class,
    'key_type' => env('COMMENTS_KEY_TYPE', 'bigint'),
    'require_approval' => env('COMMENTS_REQUIRE_APPROVAL', false),
    'max_length' => env('COMMENTS_MAX_LENGTH', 5000),
    'max_depth' => env('COMMENTS_MAX_DEPTH', 5),
    'order' => env('COMMENTS_ORDER', 'latest'),
    'blocklist' => [],
    'blocklist_action' => env('COMMENTS_BLOCKLIST_ACTION', 'reject'),
    'mention_resolver' => null,
    'authorization' => env('COMMENTS_AUTHORIZATION', false),

    // Auto-hide a comment on an upheld report / threshold crossing (reports-for-laravel).
    'moderation' => [
        'on_resolved' => 'hide', // 'hide' | null
        'auto_hide' => true,     // react to the global reports threshold
    ],

    // Attachments + inline [media:UUID] rendering (media-library-for-laravel).
    'media' => [
        'attachments_bucket' => 'attachments',
        'visibility' => env('COMMENTS_MEDIA_VISIBILITY', 'private'),
        'disk' => env('COMMENTS_MEDIA_DISK'),
        'private_disk' => env('COMMENTS_MEDIA_PRIVATE_DISK', 'local'),
        'accepted_mime_types' => [],
        'max_file_size' => null,
        'responsive_widths' => null,
        'temporary_url_lifetime' => null,
        'inline' => [
            'enabled' => true,
            'default_variant' => '',
            'on_missing' => 'strip', // 'strip' | 'keep'
        ],
    ],
];
```

| Key                | Type                | Default          | Env                          | Description |
|--------------------|---------------------|------------------|------------------------------|-------------|
| `model`            | `class-string`      | `Comment::class` | —                            | The Eloquent model used to store comments. Point this at your own model (extending `RoundlyConsulting\Comments\Models\Comment`) if you need extra columns, casts, or behaviour. |
| `key_type`         | `string`            | `bigint`         | `COMMENTS_KEY_TYPE`          | Key type of every polymorphic id column (commentable, actor, mentionable, lockable) — `bigint`, `uuid` or `ulid`. Set it to match your models' primary keys before migrating; keys are stored and read back as-is. |
| `require_approval` | `bool`              | `false`          | `COMMENTS_REQUIRE_APPROVAL`  | When `true`, new comments start as `pending` and must be approved before they count as visible. When `false`, comments are approved immediately. |
| `max_length`       | `int`               | `5000`           | `COMMENTS_MAX_LENGTH`        | Maximum characters allowed in a comment body. A longer body throws `InvalidCommentBodyException`. |
| `max_depth`        | `int`               | `5`              | `COMMENTS_MAX_DEPTH`         | Maximum nesting depth for replies (a top-level comment is depth 1). Replying deeper throws `MaxReplyDepthExceededException` — soft-deleted ancestors still count — and threaded eager-loading is bounded to this depth. |
| `order`            | `string`            | `latest`         | `COMMENTS_ORDER`             | Default ordering for reading helpers — `latest` (newest first) or `oldest`. |
| `blocklist`        | `list<string>`      | `[]`             | —                            | Banned words or regexes. Plain strings match case-insensitively as whole words; delimited entries (e.g. `/badword/i`) are treated as patterns. |
| `blocklist_action` | `string`            | `reject`         | `COMMENTS_BLOCKLIST_ACTION`  | What to do on a match, on write **and** edit: `reject` (throw `CommentRejectedException`), `pending`, or `hidden`. |
| `mention_resolver` | `class-string\|array\|null` | `null`  | —                            | Resolves a parsed `@handle` to an Eloquent model (or `null`): an invokable class-string or a `[Class::class, 'method']` pair, built through the container — never a closure, which `php artisan config:cache` cannot store. Handles are always stored; resolved ones link to the model and fire `CommentMentioned` once the comment is approved (see [@mentions](#mentions)). |
| `authorization`    | `bool`              | `false`          | `COMMENTS_AUTHORIZATION`     | When `true`, every mutation (create, update, delete, restore, moderate, lock, unlock) consults the `Comment` policy. Off by default so existing behaviour is unchanged. |
| `moderation.on_resolved` | `string\|null` | `hide`         | —                            | Auto-hide a comment when a report against it is upheld (`ReportResolved`). `hide` or `null` to disable. |
| `moderation.auto_hide` | `bool`            | `true`          | —                            | Auto-hide a comment when it crosses the global `reports.threshold` (`ReportThresholdReached`). |
| `media`            | `array`             | see above        | `COMMENTS_MEDIA_*`           | The comment's single `attachments` bucket (disk, private disk, visibility, accepted types, max size in **bytes** — enforced on upload, responsive widths, signed-URL lifetime) plus inline `[media:UUID]` body rendering (`enabled`, `default_variant`, `on_missing`). With `disk` unset, private attachments (and their variants) go to `private_disk` (`local`), public ones to media-library's default disk. |

The package works with zero configuration — every key has a sensible default.

## Usage

### Preparing your models

Add `HasComments` to anything that can be commented on, and `GivesComments` to anything that
can author a comment (typically your `User`):

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Traits\HasComments;
use RoundlyConsulting\Comments\Traits\GivesComments;

class Post extends Model
{
    use HasComments;
}

class User extends Model
{
    use GivesComments;
}
```

### The `Comments` facade

`Comments` is the whole API in one place. The fluent builder reads like a sentence:

```php
use RoundlyConsulting\Comments\Facades\Comments;

$comment = Comments::on($post)
    ->as($user)
    ->body('Nice write-up!')
    ->post();

// A reply, fluently (the parent must belong to the same subject):
Comments::on($post)->as($user)->reply($comment)->body('Thanks!')->post();

// Hidden comment:
Comments::on($post)->as($user)->visible(false)->body('Internal note')->post();
```

The author is optional — omit `->as(...)` to record an anonymous/guest comment.

| Method | Returns | Does |
|---|---|---|
| `on($subject)` | `CommentBuilder` | Start a comment: `->as()`, `->body()`, `->visible()`, `->reply()`, `->post()` |
| `write(WriteCommentData $data)` | `Comment` | Write from a typed DTO |
| `update($comment, $body)` / `delete($comment)` / `restore($comment)` | `Comment` / `void` / `Comment` | Edit, soft-delete, restore |
| `approve($comment)` / `hide($comment)` | `Comment` | Moderate |
| `query()` | `CommentQuery` | Site-wide read/moderation query |
| `for($subject)` / `byAuthor($author)` | `CommentQuery` | The same query, scoped to a subject or an author |
| `lock($subject)` / `unlock($subject)` / `isLocked($subject)` | `CommentLock` / `void` / `bool` | Lock a whole subject |
| `lockThread($comment)` / `unlockThread($comment)` / `isThreadLocked($comment)` | `Comment` / `Comment` / `bool` | Lock the thread under a comment |
| `fake()` | `CommentsFake` | Record every mutation in tests (see [Testing](#testing-with-commentsfake)) |

### Without the facade

Every facade method lives on `CommentsManager`, so you can inject it instead — same API,
same behaviour. Each method runs one action class, which you can also call directly (from a
queued job, say):

```php
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;

final class PublishFeedback
{
    public function __construct(private CommentsManager $comments) {}

    public function __invoke(Post $post, User $user, string $body): Comment
    {
        return $this->comments->on($post)->as($user)->body($body)->post();
    }
}

// The raw action:
$comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
    commentable: $post,
    body: 'From a job',
    author: $user,        // optional
    parent: $rootComment, // optional reply — must belong to $post
));
```

The actions are `WriteCommentAction`, `UpdateCommentAction`, `DeleteCommentAction`,
`RestoreCommentAction`, `ApproveCommentAction`, `HideCommentAction`, `LockSubjectAction`,
`UnlockSubjectAction`, `LockThreadAction` and `UnlockThreadAction`.

### Writing as the author model

`GivesComments` adds a `writeComment()` shortcut. Pass the model being commented on, the body,
and an optional visibility flag (defaults to `true`). It goes through the same manager as the
facade:

```php
$comment = $user->writeComment(
    commentable: $post,
    comment: 'This is a very good blog post, thanks for sharing!',
    visible: true, // optional, defaults to true
);
```

### Editing, deleting, restoring

```php
Comments::update($comment, 'Edited body'); // dispatches CommentUpdated
Comments::delete($comment);                // soft delete, dispatches CommentDeleted
Comments::restore($comment);               // restore a soft-deleted comment
```

### Moderation

Each comment carries a `status` (`pending`, `approved`, or `hidden`, the
`RoundlyConsulting\Comments\Enums\CommentStatus` enum). Set `require_approval` to `true` in
config to hold new comments for review:

```php
Comments::approve($comment); // status → approved, dispatches CommentApproved
Comments::hide($comment);    // status → hidden,   dispatches CommentHidden
```

A site-wide moderation queue — every subject at once — starts from `Comments::query()`:

```php
Comments::query()->pending()->mostReported()->paginate();
Comments::query()->pending()->approveAll();
```

Plain query scopes work too:

```php
use RoundlyConsulting\Comments\Models\Comment;

Comment::pending()->get();  // awaiting review
Comment::approved()->get(); // approved
Comment::hidden()->get();   // hidden
Comment::visible()->get();  // visible === true AND status === approved
Comment::roots()->get();    // top-level comments only
```

### Threaded replies

Replies are stored with both their root subject (so flat listings still work) and a
`parent_id` link to the comment they answer. Depth is bounded by `comments.max_depth`, and a
soft-deleted ancestor still counts towards it. A reply is written on the same subject as its
parent: `Comments::on($otherPost)->reply($comment)` throws `InvalidCommentParentException`
rather than moving the reply to the parent's subject.

```php
// Direct children of a comment (every status — filter before showing them publicly):
$comment->replies;

// The public thread: visible + approved top-level comments, with only visible + approved
// replies eager-loaded (bounded by max_depth). A hidden, pending or visible(false) comment
// never loads, and neither does anything below it:
$post->threadedComments()->get();

// Every root with every reply, for a moderation view:
Comments::for($post)->rootsOnly()->withReplies()->get();

// Only top-level comments:
$post->comments()->whereNull('parent_id')->get();
```

### Reading comments

```php
$post->comments;          // every comment written on the post
$post->approvedComments;  // visible + approved top-level comments, ordered per config
$user->writtenComments;   // every comment authored by the user

$comment->actor;          // the model that wrote the comment (null for anonymous)
$comment->commentable;    // the model the comment was written on
```

### Reacting to comment lifecycle events

Each lifecycle step dispatches an event under `RoundlyConsulting\Comments\Events`, each
carrying the comment on its `$comment` property:

- `CommentCreated`
- `CommentUpdated`
- `CommentDeleted`
- `CommentApproved`
- `CommentHidden` (also dispatched by the auto-hide moderation listener)
- `CommentThreadLocked` / `CommentThreadUnlocked`
- `CommentMentioned` (`$event->mention`) — only for approved comments, once per person

```php
use RoundlyConsulting\Comments\Events\CommentCreated;

Event::listen(function (CommentCreated $event): void {
    // $event->comment is the freshly created Comment
    Notification::send(
        $event->comment->commentable,
        new NewCommentNotification($event->comment),
    );
});
```

### Likes / upvotes & ranking

Comment reactions are powered by [`likes-for-laravel`](https://github.com/roundly-consulting/likes-for-laravel):
the `Comment` model implements `Likeable`, so any model can like/upvote it. Actors are always
passed **explicitly** — the package never resolves the acting user from the auth guard.

```php
use RoundlyConsulting\Likes\Facades\Likes;

Likes::actor($user)->like($comment);
Likes::actor($user)->unlike($comment);
Likes::actor($user)->toggle($comment);   // bool: now liked?

$comment->likesCount();        // live or eager count
$comment->isLikedBy($user);    // bool
$comment->likeState($viewer);  // ['count' => 2, 'viewer_state' => ['liked' => true, 'reaction' => 'like'], 'breakdown' => ['like' => 2]]
```

Rank and hydrate a whole thread through the `CommentQuery` builder — most-liked, trending, and
single-query per-viewer like-state (no N+1):

```php
Comments::for($post)->orderByLikesDesc()->get();   // "top comments"
Comments::for($post)->orderByTrending()->get();    // recency-weighted "hot"
Comments::for($post)->withLikedState($viewer)->get(); // each row: ->is_liked, ->liked_reaction
```

### Reporting & auto-moderation

Comments are a report subject via [`reports-for-laravel`](https://github.com/roundly-consulting/reports-for-laravel)
(`Comment implements Reportable`): flag abusive/spam comments with dedup, typed reasons and
guest reports, and surface a moderation queue. Reporters are always **explicit**.

```php
use RoundlyConsulting\Reports\Facades\Reports;

Reports::report($comment)->by($user)->for('spam')->create();
Reports::report($comment)->asGuest($fingerprint)->for('abuse')->create();

$comment->hasBeenReported();
$comment->isReportedBy($user);

// Moderation queue on the CommentQuery builder:
Comments::for($post)->mostReported()->get();
Comments::for($post)->reportedMoreThan(3)->get();
Comments::for($post)->withReportCounts()->get(); // each row: ->reports_count
```

When a report is **upheld** (`ReportResolved`) or a comment crosses the global
`reports.threshold` (`ReportThresholdReached`), a `SyncCommentVisibilityFromReports` listener
auto-hides the comment (status → `Hidden`, re-emitting `CommentHidden`) — config-gated by
`comments.moderation`, guarded against non-comment subjects, and idempotent. Because reports
routes resolution through [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel),
you get **multi-moderator sign-off** for free.

Moderators are saved Eloquent models — typically your `User` with the approvals
`GivesApprovals` trait (`implements GivesApprovalsInterface`), which adds its
`givenApprovals()` relation:

```php
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;

Reports::moderate($report)->requiring([$alice, $bob])->rule(ApprovalRule::Quorum)->quorum(2)->open();
Reports::resolve($report, by: $alice);           // 1/2 — comment stays visible
Reports::resolve($report, by: $mallory);         // not named → ModeratorRequiredException, nothing recorded
Reports::resolve($report, by: $bob);             // quorum reached → comment hidden
```

Only the moderators named in `requiring([...])` (or an approvals delegate of one) can decide
while the request is open; anyone else — and a call without `by:` — is refused with reports'
`ModeratorRequiredException`. See the reports README for the rules and refusals.

### Media attachments & inline media

Comments own a single `attachments` bucket via [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel)
(`Comment implements HasMedia`): images get responsive variants, other files are stored as
passthrough originals and served through signed streaming.

```php
$comment->addMedia($request->file('file'))->toBucket($comment->attachmentsBucket());

$comment->attachments();                // Collection<Media>
$comment->attachmentUrls();             // list<string>, each resolved by its visibility
$comment->attachmentUrls('responsive-320'); // that variant where generated, else the original
$comment->resolveAttachmentUrl($media); // public URL if public, signed short-lived URL if private
$comment->attachmentUrl($media);        // always a signed, short-lived URL
```

Image attachments get one generated variant per responsive width, named `responsive-<width>`
(`comments.media.responsive_widths`, else media-library's `media.responsive.widths` ladder —
`320, 640, 960, 1280, 1920` out of the box; only widths an image can fill are generated). A
variant an attachment does not have — every non-image, or a name the bucket never generates —
falls back to the original file, so `attachmentUrls($variant)` over mixed attachments never
throws.

Uploads are checked against the bucket on the way in: `comments.media.accepted_mime_types` and
`comments.media.max_file_size` (bytes) make media-library refuse a file with
`FileUnacceptableForBucket`.

Attachments are **private by default** (`comments.media.visibility`). A private attachment is
only ever linked through a short-lived signed URL — `attachmentUrls()`, inline media in
`renderBody()` and `CommentResource` all resolve it that way, never to a public URL.

Signed URLs protect the link, so the bytes must not be reachable any other way: with
`comments.media.disk` unset, private attachments (and their variants) are stored on
`comments.media.private_disk` — Laravel's `local` disk (`storage/app/private`) by default — never
on media-library's default `public` disk, which `php artisan storage:link` exposes under
`/storage`. Point `COMMENTS_MEDIA_PRIVATE_DISK` at another non-public disk (e.g. a private S3
disk) if you like; if you set `COMMENTS_MEDIA_DISK` yourself, that disk is used for every
attachment, so keep it non-public while attachments are private.

Embed inline images GitHub/Reddit-style with `[media:UUID]` / `[media:UUID|variant]` tokens in
the body — resolved only against the comment's own bucket, in a single batched query, never
throwing on a missing UUID or on a `|variant` the image lacks (that renders the responsive
`<img>` instead):

```php
$comment->update(['comment' => "see this [media:{$media->uuid}] 👀"]);

echo $comment->renderBody(); // HtmlString: responsive <img> for images, <a> for other files
```

> [!WARNING]
> **Security: `renderBody()` does not escape the comment text (XSS risk).** Only the generated
> `<img>` / `<a>` tags are escaped; everything around the tokens is returned exactly as stored.
> Because it returns an `HtmlString`, Blade prints it **raw even inside `{{ }}`** —
> `{{ $comment->renderBody() }}` is as unsafe as `{!! $comment->renderBody() !!}`. Never output
> it for user-supplied comments unless the text is known to be safe:
>
> - **Plain text:** echo the attribute — `{{ $comment->comment }}` — and let Blade escape it.
> - **Text + inline media:** escape the text when you *write* it, e.g.
>   `Comments::on($post)->body(e($request->input('body')))->post()` (tokens contain no
>   HTML-special characters, so they survive `e()`; the stored body — and `CommentResource`'s
>   `body` — then holds the escaped text), or run the output through an HTML sanitizer that
>   only allows the generated `<img>` / `<a>` tags.

### @mentions

Comment bodies are scanned for `@handle` tokens on write and edit. A handle may contain `.` and
`-` (`@john.doe`) but ends on a letter, digit or underscore, so "thanks @alice." mentions
`alice`. Handles are always stored; set a resolver to link them to models and fire
`CommentMentioned`.

`CommentMentioned` fires only for an **approved** comment, once per person: a comment the
moderation layer holds back (`require_approval`, a blocklist `pending`/`hidden` match, a
moderator's hide) notifies nobody until `Comments::approve()` — then each resolved mention fires
once. An edit only fires it for people the comment has not notified yet — handles still in the
body keep their rows, removed ones are deleted, and re-mentioning the same person (even under
another handle) is not a new mention.

The resolver is a class, not a closure — `php artisan config:cache` cannot store a closure:

```php
// app/Mentions/ResolveMention.php
namespace App\Mentions;

use App\Models\User;

final class ResolveMention
{
    public function __invoke(string $handle): ?User
    {
        return User::where('username', $handle)->first();
    }
}

// config/comments.php — an invokable class, or [ResolveMention::class, 'someMethod']
'mention_resolver' => \App\Mentions\ResolveMention::class,

$comment = Comments::on($post)->body('thanks @alice!')->post();
$comment->mentions; // CommentMention rows (handle + optional mentionable)
```

The resolver is built through the container, so it may take constructor dependencies.

### Spam / blocklist filter

Configure banned words or regexes and how a match is handled:

```php
// config/comments.php
'blocklist' => ['spam', '/casino|viagra/i'],
'blocklist_action' => 'reject', // 'reject' | 'pending' | 'hidden'
```

With `reject`, a matching comment throws `CommentRejectedException`; with `pending`/`hidden`
it is stored in that status instead. Edits run the same filter, so a comment posted clean
cannot be edited into spam and stay public: a blocklisted edit is rejected, or moves the comment
to `pending`/`hidden` (an edit only ever tightens the status — it never lifts a hidden comment
to pending, and a clean edit leaves a held comment for a moderator to approve).

### Locking threads

Lock a whole subject, or just the thread under one comment:

```php
Comments::lock($post);              // block all new/edited comments on the post
Comments::unlock($post);
Comments::isLocked($post);          // bool
$post->commentsLocked();            // bool, same answer

Comments::lockThread($comment);     // no new replies anywhere below it, no edits in it
Comments::unlockThread($comment);
Comments::isThreadLocked($reply);   // bool — true when it or any ancestor is locked

$comment->lockReplies();            // same as Comments::lockThread($comment)
$comment->unlockReplies();          // same as Comments::unlockThread($comment)
$comment->isLocked();               // bool — this comment's own thread lock only
```

A thread lock covers the whole subtree: a reply to a reply is still in the thread, and
soft-deleting a comment inside a locked thread keeps everything below it locked. Locking
fires `CommentThreadLocked`, unlocking `CommentThreadUnlocked` — only when the state actually
changes. Writing or editing against a lock throws `CommentsLockedException`.

### Reading & bulk moderation

A fluent read side mirrors the write builder, with moderation, ordering, and pagination:

```php
use RoundlyConsulting\Comments\Facades\Comments;

Comments::for($post)->approved()->newest()->paginate(20);
Comments::for($post)->visible()->rootsOnly()->withReplies()->get(); // public replies only
Comments::for($post)->pending()->count();
Comments::byAuthor($user)->get();
Comments::query()->hidden()->newest()->get();   // site-wide, every subject

// Bulk moderation — runs each comment through the manager, so the policy, the lifecycle
// event and Comments::fake() all see every row:
Comments::for($post)->pending()->approveAll();
Comments::byAuthor($user)->hideAll();
Comments::for($post)->deleteAll();
```

`withReplies()` follows the query's visibility: on a `visible()` query (in either order) only
visible + approved replies load, at every level; without `visible()` every reply loads, for a
moderation view.

### Comment counts

```php
$post->loadCommentCount();        // sets $post->comments_count

Post::withCommentCounts()->get(); // adds comments_count without N+1
```

`comments_count` is what the public sees: every visible + approved comment on the subject,
replies included — the same rows as `Comment::visible()`. Pending, hidden and `visible(false)`
comments are never counted.

### Authorization

Authorization is **off by default**, so existing callers are unaffected. Opt in by setting
`comments.authorization` to `true` and registering a policy for the `Comment` model. The
package ships a permissive starting point you can extend:

```php
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Policies\CommentPolicy;

Gate::policy(Comment::class, CommentPolicy::class);
```

When enabled, every mutation checks the matching ability of the `Comment` policy and throws
`UnauthorizedCommentActionException` on denial: `create`, `update`, `delete`, `restore`,
`moderate` (approve / hide), and `lock` / `unlock` (subject locks as well as `lockThread()` /
`unlockThread()`). A policy registered for `Comment` also covers a subclass configured in
`comments.model`; with nothing registered, Laravel's policy auto-discovery resolves the shipped,
permissive `CommentPolicy`.

`create` also receives the subject being commented on — for a reply, the root subject the reply
joins — so you can decide per subject; `lock` / `unlock` receive what is being locked (the
subject, or the comment for a thread lock). `$user` is `null` for a guest. A custom policy
that lacks one of these methods denies that action, so extend the shipped `CommentPolicy`:

```php
class ProjectCommentPolicy extends CommentPolicy
{
    public function create(?Model $user, ?Model $commentable = null): bool
    {
        return $user !== null && $commentable instanceof Project && $commentable->hasMember($user);
    }

    public function lock(?Model $user, Model $lockable): bool
    {
        return $user?->isModerator() ?? false;
    }
}
```

The package ships no routes, so the exception is not an HTTP exception. To answer `403` from
your own controllers, map it onto Laravel's `AuthorizationException` in `bootstrap/app.php`:

```php
use Illuminate\Auth\Access\AuthorizationException;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->map(fn (UnauthorizedCommentActionException $e) => new AuthorizationException($e->getMessage()));
})
```

### API Resources

Drop-in JSON for SPA/mobile backends, with nested replies, author, status, timestamps, and —
when the relations are loaded — a compact `likes` payload and an `attachments` array:

```php
use RoundlyConsulting\Comments\Http\Resources\CommentResource;

return CommentResource::collection(
    $post->threadedComments()->with(['actor', 'likes', 'media'])->get(),
);
```

`threadedComments()` is the public thread, so the JSON holds only visible + approved roots and
replies. The resource renders whatever it is given — the `replies` key is any loaded `replies`
relation — so feed it a public query (`threadedComments()`, or a `visible()` `CommentQuery`
with `withReplies()`) on public endpoints.

The `likes` block (`count` / `viewer_state` / `breakdown`) is rendered only when the `likes`
relation is loaded, and `attachments` only when `media` is loaded.

### Testing with `Comments::fake()`

`Comments::fake()` swaps in a recording `CommentsFake`. It still performs every operation —
rows are written, policies and locks apply, events fire — and records each successful mutation,
however it was made: the facade, an injected `CommentsManager`, the builder, a query's bulk
moderation, `$user->writeComment()` or `$comment->lockReplies()`.

```php
use RoundlyConsulting\Comments\Facades\Comments;

$fake = Comments::fake();

// ... run the code under test ...

$fake->assertPosted(fn (Comment $comment) => $comment->comment === 'Hi');
$fake->assertApproved($comment);
$fake->assertLocked($post);
$fake->assertThreadLocked();
$fake->assertNothingDeleted();
```

Each assertion takes an optional model (matched with `is()`) or a callback that returns `true`
on a match:

| Recorded by | Assert | Assert none |
|---|---|---|
| `on()->post()`, `write()`, `writeComment()` | `assertPosted()` | `assertNothingPosted()` |
| `update()` | `assertUpdated()` | `assertNothingUpdated()` |
| `delete()`, `deleteAll()` | `assertDeleted()` | `assertNothingDeleted()` |
| `restore()` | `assertRestored()` | `assertNothingRestored()` |
| `approve()`, `approveAll()` | `assertApproved()` | `assertNothingApproved()` |
| `hide()`, `hideAll()` | `assertHidden()` | `assertNothingHidden()` |
| `lock($subject)` | `assertLocked()` | `assertNothingLocked()` |
| `unlock($subject)` | `assertUnlocked()` | `assertNothingUnlocked()` |
| `lockThread()`, `lockReplies()` | `assertThreadLocked()` | `assertNothingThreadLocked()` |
| `unlockThread()`, `unlockReplies()` | `assertThreadUnlocked()` | `assertNothingThreadUnlocked()` |

A call that throws records nothing. The auto-hide listener moderates on the system's behalf
without a user, so it bypasses the manager and is not recorded — assert `CommentHidden` with
`Event::fake()` instead.

### Testing helpers

The factory ships states (`on($subject)`, `pending()`, `hidden()`, `locked()`,
`reply($parent)`, `by($user)`), and the `AssertsComments` trait adds database expectations for
host-app tests. A comment needs a subject, so every factory comment takes `on($subject)` or
`reply($parent)` (or `for($subject, 'commentable')`):

```php
use RoundlyConsulting\Comments\Testing\AssertsComments;

Comment::factory()->on($post)->pending()->create();
Comment::factory()->reply($root)->by($user)->create();

// In a test case using AssertsComments:
$this->assertCommented($post);
$this->assertCommented($post, 'expected body');
$this->assertCommentCount($post, 3);
$this->assertNotCommented($post);
```

## Integrates with

Comments builds on sibling roundly packages, wired as hard dependencies (resolved by path
locally and VCS on CI until they land on Packagist):

| Package | What it powers here |
|---------|---------------------|
| [`package-toolkit-for-laravel`](https://github.com/roundly-consulting/package-toolkit-for-laravel) | The service-provider builder (config/migrations/translations wiring + the `php artisan about` section) and the validated `comments.model` resolver. |
| [`likes-for-laravel`](https://github.com/roundly-consulting/likes-for-laravel) | Like/upvote a comment, typed reactions, most-liked / trending ranking, single-query per-viewer like-state, `likeState()` payload. |
| [`reports-for-laravel`](https://github.com/roundly-consulting/reports-for-laravel) | Report-a-comment (dedup, typed reasons, guest reports), moderation-queue scopes, and the `SyncCommentVisibilityFromReports` auto-hide listener. |
| [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel) | Multi-moderator sign-off on report resolution (inherited transitively through reports). |
| [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel) | The `attachments` bucket (image variants + signed streaming) and inline `[media:UUID]` body rendering. |
| [`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) | `CommentStatus` helper surface (`values()`/`labels()`/`options()`/`validationRule()`/`readable()`). |

Actors, reporters and viewers are always passed **explicitly** across every integration — the
package never resolves the acting user from the auth guard.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
