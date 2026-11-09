<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Http\Resources\CommentAuthorResource;
use RoundlyConsulting\Comments\Http\Resources\CommentEditorResource;
use RoundlyConsulting\Comments\Models\Comment;

return [

    /*
    |--------------------------------------------------------------------------
    | Comment Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store comments. Override this with your own
    | model (extending the package model) if you need to customize behaviour.
    |
    */

    'model' => Comment::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic actor / commentable / mentionable /
    | lockable columns. Use "uuid" or "ulid" when the models these point at use
    | UUID/ULID primary keys, otherwise leave it as "bigint". Any other value
    | throws an InvalidConfigurationException. It is fixed when the migration
    | first runs, so choose it before publishing the migrations.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('COMMENTS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Require Approval
    |--------------------------------------------------------------------------
    |
    | When enabled, newly written comments start in the "pending" state and
    | must be approved before they count as visible. When disabled (default),
    | comments are approved immediately, preserving the simplest behaviour.
    |
    */

    'require_approval' => env('COMMENTS_REQUIRE_APPROVAL', false),

    /*
    |--------------------------------------------------------------------------
    | Maximum Body Length
    |--------------------------------------------------------------------------
    |
    | The maximum number of characters allowed in a comment body. Writing a
    | longer body throws an InvalidCommentBodyException. Must be an integer of
    | at least 1; anything else ("five", "5.5") throws an
    | InvalidConfigurationException. A blank value is not set: the default applies.
    |
    */

    'max_length' => env('COMMENTS_MAX_LENGTH', 5000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Reply Depth
    |--------------------------------------------------------------------------
    |
    | How many levels of nested replies are allowed and eager-loaded. A reply
    | deeper than this throws a MaxReplyDepthExceededException. A top-level
    | comment is depth 1, its direct reply is depth 2, and so on. Must be an
    | integer of at least 1; anything else throws.
    |
    */

    'max_depth' => env('COMMENTS_MAX_DEPTH', 5),

    /*
    |--------------------------------------------------------------------------
    | Default Order
    |--------------------------------------------------------------------------
    |
    | The default ordering applied by the reading helpers. Either "latest"
    | (newest first) or "oldest" (oldest first). Any other value throws an
    | InvalidConfigurationException.
    |
    */

    'order' => env('COMMENTS_ORDER', 'latest'),

    /*
    |--------------------------------------------------------------------------
    | Blocklist
    |--------------------------------------------------------------------------
    |
    | A list of banned words or regular expressions. A comment body matching
    | any entry is handled per "blocklist_action". Plain strings match
    | case-insensitively as whole words; entries wrapped in delimiters (e.g.
    | "/badword/i") are treated as regular expressions.
    |
    | @var list<string>
    |
    */

    'blocklist' => [],

    /*
    |--------------------------------------------------------------------------
    | Blocklist Action
    |--------------------------------------------------------------------------
    |
    | What to do when a comment matches the blocklist. One of:
    |   "reject"  — throw a CommentRejectedException (the comment is not stored)
    |   "pending" — store the comment with the "pending" status for review
    |   "hidden"  — store the comment with the "hidden" status
    | Any other value throws an InvalidConfigurationException.
    |
    */

    'blocklist_action' => env('COMMENTS_BLOCKLIST_ACTION', 'reject'),

    /*
    |--------------------------------------------------------------------------
    | Mention Resolver
    |--------------------------------------------------------------------------
    |
    | Resolves a parsed "@handle" token to an Eloquent model (or null): an
    | invokable class-string, or a [Class::class, 'method'] pair — both built
    | through the container. Never a closure: `php artisan config:cache` cannot
    | store one. Handles are always stored; when the resolver returns a model
    | the mention is linked to it, and CommentMentioned fires once the comment
    | is approved. Leave null to only store the raw handles. A value that does
    | not resolve to a callable throws an InvalidConfigurationException.
    |
    | Group mentions: return an object implementing
    | RoundlyConsulting\Comments\Contracts\MentionableGroup ("@design-team")
    | and the handle expands to the group's members — each stored once, the
    | comment's own author left out, and every member notified once.
    |
    | @var class-string|array{0: class-string, 1: string}|null
    |
    */

    'mention_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Edit History
    |--------------------------------------------------------------------------
    |
    | When enabled, every edit keeps the body it replaced as a CommentRevision
    | (comment_revisions table), with the editor passed to
    | Comments::update($comment, $body, editor: $user) and the time of the
    | edit. Concurrent edits are serialized by a row lock, so each one lands in
    | the history, in order. Disabled by default: edits overwrite the body.
    |
    */

    'edit_history' => env('COMMENTS_EDIT_HISTORY', false),

    /*
    |--------------------------------------------------------------------------
    | Deleted Comments
    |--------------------------------------------------------------------------
    |
    | What a soft-deleted comment does to the public thread. One of:
    |   "remove"      — it drops out, and its replies with it (default)
    |   "placeholder" — it keeps its place: threadedComments() and
    |                   approvedComments() still return it with its replies,
    |                   and CommentResource renders it as `deleted: true`
    |                   without its body, author, attachments, likes or history
    | Any other value throws an InvalidConfigurationException.
    |
    */

    'deleted_comments' => env('COMMENTS_DELETED_COMMENTS', 'remove'),

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | When enabled, the write/update/moderation actions consult Laravel's Gate
    | before mutating a comment ("create"/"update"/"moderate" abilities on the
    | configured policy). Disabled by default so existing behaviour is
    | unchanged — opt in by setting this to true and registering CommentPolicy
    | (or your own) for the Comment model.
    |
    */

    'authorization' => env('COMMENTS_AUTHORIZATION', false),

    /*
    |--------------------------------------------------------------------------
    | API Resources
    |--------------------------------------------------------------------------
    |
    | The JSON resources CommentResource renders related models through.
    | "author" receives the comment's actor model (when the "actor" relation is
    | loaded) and "editor" the model that made an edit (when "revisions.editor"
    | is loaded); both render the key and class basename — never the class
    | name. Swap either for your own JsonResource subclass to add a name, an
    | avatar or anything else your API needs. Anything that is not an
    | instantiable JsonResource subclass throws an
    | InvalidConfigurationException; a blank value is not set: the default
    | applies.
    |
    | @var array{author: class-string<JsonResource>, editor: class-string<JsonResource>}
    |
    */

    'resources' => [
        'author' => CommentAuthorResource::class,
        'editor' => CommentEditorResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | Integration with roundly-consulting/reports-for-laravel. When a report
    | against a comment is upheld, or the comment crosses the global reports
    | threshold (config('reports.threshold')), the comment can be auto-hidden
    | (status => hidden, re-emitting CommentHidden). Because reports routes
    | resolution through approvals, this yields multi-moderator moderation with
    | no extra code. Set both keys to disable auto-moderation entirely.
    |
    */

    'moderation' => [

        // Auto-hide a comment when a report against it is upheld (ReportResolved).
        // 'hide' | null (disable the resolved path). Anything else throws.
        'on_resolved' => 'hide',

        // Auto-hide a comment when its open-report count crosses the global
        // config('reports.threshold') — reacts to ReportThresholdReached.
        'auto_hide' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Integration with roundly-consulting/media-library-for-laravel. A comment
    | owns a single "attachments" bucket (images get responsive variants, other
    | files are stored as passthrough originals and served via signed streaming),
    | and the comment body can embed inline media with [media:UUID] tokens that
    | resolve ONLY against the comment's own bucket, never arbitrary global media.
    |
    */

    'media' => [

        // Bucket name the comment registers on the media-library model.
        'attachments_bucket' => 'attachments',

        // Attachment visibility: 'private' (only ever linked via short-lived signed URLs)
        // or 'public'. Anything else throws — a typo never picks a visibility for you.
        'visibility' => env('COMMENTS_MEDIA_VISIBILITY', 'private'),

        // Disk for the comment's media, whatever its visibility. null => chosen by visibility:
        // private attachments go to 'private_disk' below, public ones to the media-library
        // default disk ('public' out of the box).
        'disk' => env('COMMENTS_MEDIA_DISK'),

        // Disk for PRIVATE attachments (originals and variants) when 'disk' is null. It must
        // not be web-served — the media-library default 'public' disk is (under /storage once
        // `storage:link` runs), which would make a private file reachable without a signature.
        // Laravel's 'local' disk (storage/app/private) is not; a private S3 disk works too.
        'private_disk' => env('COMMENTS_MEDIA_PRIVATE_DISK', 'local'),

        // Whitelist of accepted mime types. [] => accept any type.
        'accepted_mime_types' => [],

        // Max attachment size in BYTES, enforced on upload (media-library throws
        // FileUnacceptableForBucket), at least 1. null => media-library's media.max_file_size.
        'max_file_size' => null,

        // Responsive width ladder for image attachments.
        // null => the media-library default ladder (config('media.responsive.widths')).
        'responsive_widths' => null,

        // Lifetime (minutes, at least 1) of a signed attachment URL. null => the media default.
        'temporary_url_lifetime' => null,

        // Inline [media:UUID] / [media:UUID|variant] rendering in the comment body.
        'inline' => [
            'enabled' => true,
            'default_variant' => '',
            'on_missing' => 'strip', // 'strip' | 'keep' (anything else throws)
        ],
    ],

];
