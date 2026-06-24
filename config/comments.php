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

];
