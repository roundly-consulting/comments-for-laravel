<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/comments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel">
    <img src="art/hero.png" alt="Comments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Comments for Laravel

Attach polymorphic comments to any Laravel model. Any model can *give* comments and any
model can *receive* them, with first-class threaded replies, a built-in moderation workflow,
and a discoverable `Comments` facade. The package ships morph-based tables, opt-in traits,
typed actions/DTOs, and an event for every part of the comment lifecycle.

On top of the basics it adds emoji **reactions**, `@mention` parsing, a configurable
**blocklist** filter, thread/subject **locking**, a fluent **reading & bulk-moderation**
query, N+1-free **comment counts**, opt-in **authorization** via a policy, ready-made **API
Resources**, and **testing helpers** for host apps.

## Requirements

- PHP 8.4 or higher
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/comments-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="comments-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="comments-config"
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
    'require_approval' => env('COMMENTS_REQUIRE_APPROVAL', false),
    'max_length' => env('COMMENTS_MAX_LENGTH', 5000),
    'max_depth' => env('COMMENTS_MAX_DEPTH', 5),
    'order' => env('COMMENTS_ORDER', 'latest'),
    'blocklist' => [],
    'blocklist_action' => env('COMMENTS_BLOCKLIST_ACTION', 'reject'),
    'mention_resolver' => null,
    'authorization' => env('COMMENTS_AUTHORIZATION', false),
];
```

| Key                | Type                | Default          | Env                          | Description |
|--------------------|---------------------|------------------|------------------------------|-------------|
| `model`            | `class-string`      | `Comment::class` | —                            | The Eloquent model used to store comments. Point this at your own model (extending `RoundlyConsulting\Comments\Models\Comment`) if you need extra columns, casts, or behaviour. |
| `require_approval` | `bool`              | `false`          | `COMMENTS_REQUIRE_APPROVAL`  | When `true`, new comments start as `pending` and must be approved before they count as visible. When `false`, comments are approved immediately. |
| `max_length`       | `int`               | `5000`           | `COMMENTS_MAX_LENGTH`        | Maximum characters allowed in a comment body. A longer body throws `InvalidCommentBodyException`. |
| `max_depth`        | `int`               | `5`              | `COMMENTS_MAX_DEPTH`         | Maximum nesting depth for replies (a top-level comment is depth 1). Replying deeper throws `MaxReplyDepthExceededException`, and threaded eager-loading is bounded to this depth. |
| `order`            | `string`            | `latest`         | `COMMENTS_ORDER`             | Default ordering for reading helpers — `latest` (newest first) or `oldest`. |
| `blocklist`        | `list<string>`      | `[]`             | —                            | Banned words or regexes. Plain strings match case-insensitively as whole words; delimited entries (e.g. `/badword/i`) are treated as patterns. |
| `blocklist_action` | `string`            | `reject`         | `COMMENTS_BLOCKLIST_ACTION`  | What to do on a match: `reject` (throw `CommentRejectedException`), `pending`, or `hidden`. |
| `mention_resolver` | `callable\|null`    | `null`           | —                            | Resolves a parsed `@handle` to an Eloquent model (or `null`). Handles are always stored; resolved ones link to the model and fire `CommentMentioned`. |
| `authorization`    | `bool`              | `false`          | `COMMENTS_AUTHORIZATION`     | When `true`, write/update/moderation actions consult Laravel's `Gate`. Off by default so existing behaviour is unchanged. |

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
- `CommentHidden`
- `CommentReacted` (`$event->reaction`)
- `CommentUnreacted` (`$event->comment`, `$event->reaction`, `$event->reactor`)
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

### Reactions

Any model can react to a comment, and reactions are deduplicated per reactor + emoji.

```php
$comment->react('👍', as: $user);   // dispatches CommentReacted (first time only)
$comment->react('❤️');             // anonymous reaction (no reactor)
$comment->unreact('👍', as: $user); // returns bool, dispatches CommentUnreacted

$comment->reactionCounts(); // ['👍' => 3, '❤️' => 1]
$comment->reactions;        // the CommentReaction models
```

### @mentions

Comment bodies are scanned for `@handle` tokens on write and edit. Handles are always stored;
set a resolver to link them to models and fire `CommentMentioned`.

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

When enabled, the actions check the `create`, `update`, `delete`, and `moderate` abilities and
throw `UnauthorizedCommentActionException` on denial.

### API Resources

Drop-in JSON for SPA/mobile backends, with nested replies, author, status, reaction counts,
and timestamps:

```php
use RoundlyConsulting\Comments\Http\Resources\CommentResource;

return CommentResource::collection(
    $post->threadedComments()->with(['actor', 'reactions'])->get(),
);
```

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

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
