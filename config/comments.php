<?php

declare(strict_types=1);

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
    | longer body throws an InvalidCommentBodyException.
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
    | comment is depth 1, its direct reply is depth 2, and so on.
    |
    */

    'max_depth' => env('COMMENTS_MAX_DEPTH', 5),

    /*
    |--------------------------------------------------------------------------
    | Default Order
    |--------------------------------------------------------------------------
    |
    | The default ordering applied by the reading helpers. Either "latest"
    | (newest first) or "oldest" (oldest first).
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
    |
    */

    'blocklist_action' => env('COMMENTS_BLOCKLIST_ACTION', 'reject'),

    /*
    |--------------------------------------------------------------------------
    | Mention Resolver
    |--------------------------------------------------------------------------
    |
    | A callable that resolves a parsed "@handle" token to an Eloquent model
    | (or null). Set it to a callable or an [class, method] array. Handles are
    | always stored; when the resolver returns a model the mention is linked to
    | it and a CommentMentioned event is dispatched. Leave null to only store
    | the raw handles.
    |
    | @var callable|array{0: class-string, 1: string}|null
    |
    */

    'mention_resolver' => null,

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
        // 'hide' | null (disable the resolved path).
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

        // Attachment visibility: 'private' (signed streaming) or 'public'.
        'visibility' => env('COMMENTS_MEDIA_VISIBILITY', 'private'),

        // Disk for the comment's media. null => the media-library default disk.
        'disk' => env('COMMENTS_MEDIA_DISK'),

        // Whitelist of accepted mime types. [] => accept any type.
        'accepted_mime_types' => [],

        // Max attachment size in KB. null => the media-library default.
        'max_file_size' => null,

        // Responsive width ladder for image attachments.
        // null => the media-library default ladder (config('media.responsive.widths')).
        'responsive_widths' => null,

        // Lifetime (minutes) of a signed attachment URL. null => the media default.
        'temporary_url_lifetime' => null,

        // Inline [media:UUID] / [media:UUID|variant] rendering in the comment body.
        'inline' => [
            'enabled' => true,
            'default_variant' => '',
            'on_missing' => 'strip', // 'strip' | 'keep'
        ],
    ],

];
