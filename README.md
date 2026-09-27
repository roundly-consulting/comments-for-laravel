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
**authorization** via a policy, ready-made **API Resources**, and **testing helpers** for host
apps.

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
| `max_depth`        | `int`               | `5`              | `COMMENTS_MAX_DEPTH`         | Maximum nesting depth for replies (a top-level comment is depth 1). Replying deeper throws `MaxReplyDepthExceededException`, and threaded eager-loading is bounded to this depth. |
| `order`            | `string`            | `latest`         | `COMMENTS_ORDER`             | Default ordering for reading helpers — `latest` (newest first) or `oldest`. |
| `blocklist`        | `list<string>`      | `[]`             | —                            | Banned words or regexes. Plain strings match case-insensitively as whole words; delimited entries (e.g. `/badword/i`) are treated as patterns. |
| `blocklist_action` | `string`            | `reject`         | `COMMENTS_BLOCKLIST_ACTION`  | What to do on a match: `reject` (throw `CommentRejectedException`), `pending`, or `hidden`. |
| `mention_resolver` | `callable\|null`    | `null`           | —                            | Resolves a parsed `@handle` to an Eloquent model (or `null`). Handles are always stored; resolved ones link to the model and fire `CommentMentioned` (on an edit, only for newly mentioned people). |
| `authorization`    | `bool`              | `false`          | `COMMENTS_AUTHORIZATION`     | When `true`, every mutation (create, update, delete, restore, moderate, lock, unlock) consults the `Comment` policy. Off by default so existing behaviour is unchanged. |
| `moderation.on_resolved` | `string\|null` | `hide`         | —                            | Auto-hide a comment when a report against it is upheld (`ReportResolved`). `hide` or `null` to disable. |
| `moderation.auto_hide` | `bool`            | `true`          | —                            | Auto-hide a comment when it crosses the global `reports.threshold` (`ReportThresholdReached`). |
| `media`            | `array`             | see above        | `COMMENTS_MEDIA_*`           | The comment's single `attachments` bucket (disk, private disk, visibility, accepted types, size, responsive widths, signed-URL lifetime) plus inline `[media:UUID]` body rendering (`enabled`, `default_variant`, `on_missing`). With `disk` unset, private attachments (and their variants) go to `private_disk` (`local`), public ones to media-library's default disk. |

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

### Writing a comment

`GivesComments` adds a `writeComment()` method. Pass the model being commented on, the body,
and an optional visibility flag (defaults to `true`):

```php
$post = Post::find(1);
$user = auth()->user();

$comment = $user->writeComment(
    commentable: $post,
    comment: 'This is a very good blog post, thanks for sharing!',
    visible: true, // optional, defaults to true
);
```

`writeComment()` returns the created `Comment` instance.

### The `Comments` facade and fluent builder

For a discoverable, IDE-friendly entry point, use the `Comments` facade. The fluent builder
reads like a sentence:

```php
use RoundlyConsulting\Comments\Facades\Comments;

$comment = Comments::on($post)
    ->as($user)
    ->body('Nice write-up!')
    ->post();

// A reply, fluently:
Comments::on($post)->as($user)->reply($comment)->body('Thanks!')->post();

// Hidden comment:
Comments::on($post)->as($user)->visible(false)->body('Internal note')->post();
```

The author is optional — omit `->as(...)` to record an anonymous/guest comment.

### Typed DTO escape hatch

For queued or service code, drive the action directly with a DTO:

```php
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;

$comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
    commentable: $post,
    body: 'From a job',
    author: $user,       // optional
    parent: $rootComment, // optional reply
));
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

Query scopes make moderation queues easy:

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
`parent_id` link to the comment they answer. Depth is bounded by `comments.max_depth`.

```php
// Direct children of a comment:
$comment->replies;

// Top-level comments with their nested replies eager-loaded (bounded by max_depth):
$post->threadedComments()->get();

// Only top-level comments:
$post->comments()->whereNull('parent_id')->get();
```

> The legacy `commentsWithReplies` relation (replies modelled by commenting on a comment) is
> still available for backward compatibility, but `threadedComments()` / the `replies`
> relation are preferred.

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
- `CommentMentioned` (`$event->mention`)

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
you get **multi-moderator sign-off** for free:

```php
Reports::moderate($report)->requiring([$alice, $bob])->rule(ApprovalRule::Quorum)->quorum(2)->open();
Reports::resolve($report, by: $alice);           // 1/2 — comment stays visible
Reports::resolve($report, by: $bob);             // quorum reached → comment hidden
```

### Media attachments & inline media

Comments own a single `attachments` bucket via [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel)
(`Comment implements HasMedia`): images get responsive variants, other files are stored as
passthrough originals and served through signed streaming.

```php
$comment->addMedia($request->file('file'))->toBucket($comment->attachmentsBucket());

$comment->attachments();                // Collection<Media>
$comment->attachmentUrls('thumb');      // list<string>, each resolved by its visibility
$comment->resolveAttachmentUrl($media); // public URL if public, signed short-lived URL if private
$comment->attachmentUrl($media);        // always a signed, short-lived URL
```

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
throwing on a missing UUID:

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

Comment bodies are scanned for `@handle` tokens on write and edit. Handles are always stored;
set a resolver to link them to models and fire `CommentMentioned`. An edit only fires it for
people the comment did not already mention — handles still in the body keep their rows, removed
ones are deleted, and re-mentioning the same person (even under another handle) is not a new
mention.

```php
// config/comments.php
'mention_resolver' => fn (string $handle) => \App\Models\User::where('username', $handle)->first(),

$comment = Comments::on($post)->body('thanks @alice!')->post();
$comment->mentions; // CommentMention rows (handle + optional mentionable)
```

### Spam / blocklist filter

Configure banned words or regexes and how a match is handled:

```php
// config/comments.php
'blocklist' => ['spam', '/casino|viagra/i'],
'blocklist_action' => 'reject', // 'reject' | 'pending' | 'hidden'
```

With `reject`, a matching comment throws `CommentRejectedException`; with `pending`/`hidden`
it is stored in that status instead.

### Locking threads

Lock a subject to stop new or edited comments, or lock a single reply chain:

```php
Comments::lock($post);            // block all new/edited comments on the post
Comments::unlock($post);
$post->commentsLocked();          // bool
Comments::isLocked($post);        // bool

$comment->lockReplies();          // freeze just this comment's reply chain + edits
$comment->unlockReplies();
$comment->isLocked();             // bool
```

Writing or editing against a lock throws `CommentsLockedException`.

### Reading & bulk moderation

A fluent read side mirrors the write builder, with moderation, ordering, and pagination:

```php
use RoundlyConsulting\Comments\Facades\Comments;

Comments::for($post)->approved()->newest()->paginate(20);
Comments::for($post)->visible()->rootsOnly()->withReplies()->get();
Comments::for($post)->pending()->count();
Comments::byAuthor($user)->get();

// Bulk moderation — fires the lifecycle event per affected comment:
Comments::for($post)->pending()->approveAll();
Comments::byAuthor($user)->hideAll();
Comments::for($post)->deleteAll();
```

### Comment counts

```php
$post->loadCommentCount();        // sets $post->comments_count (approved comments)

Post::withCommentCounts()->get(); // adds comments_count without N+1
```

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
`moderate` (approve / hide), and `lock` / `unlock` (subject locks as well as `lockReplies()` /
`unlockReplies()`). A policy registered for `Comment` also covers a subclass configured in
`comments.model`; with nothing registered, Laravel's policy auto-discovery resolves the shipped,
permissive `CommentPolicy`.

`create` also receives the subject being commented on — for a reply, the root subject the reply
joins — so you can decide per subject; `lock` / `unlock` receive what is being locked (the
subject, or the comment for a reply-chain lock). `$user` is `null` for a guest. A custom policy
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

The `likes` block (`count` / `viewer_state` / `breakdown`) is rendered only when the `likes`
relation is loaded, and `attachments` only when `media` is loaded.

### Testing helpers

The factory ships states (`pending()`, `hidden()`, `locked()`, `reply($parent)`,
`by($user)`), and the `AssertsComments` trait adds expectations for host-app tests:

```php
use RoundlyConsulting\Comments\Testing\AssertsComments;

Comment::factory()->pending()->create();
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
If it saves you time, please consider supporting our open-source work — every donation helps fund
maintenance, new features and new packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
