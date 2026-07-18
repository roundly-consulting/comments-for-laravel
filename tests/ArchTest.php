<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Exceptions\CommentsException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Policies\CommentPolicy;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Comments shipped **no arch test at all** — this whole file is new coverage, which is the
 * jwt row's shape (its bug #4, `final` on a swappable model, existed precisely because
 * nothing was looking).
 */
ArchPresets::strictTypes('RoundlyConsulting\Comments');

/**
 * The exemptions, each a real extension point rather than an oversight:
 *
 *  - Comment, the model `config/comments.php` invites a host to swap — pinned instead by
 *    the preset below, which is the deliberate tension the two presets exist to hold;
 *  - CommentMention / CommentLock, non-final so a host swapping Comment can subclass the
 *    satellites its own relations return;
 *  - CommentsException, the exception base every typed comments failure extends and hosts
 *    catch;
 *  - CommentPolicy, which hosts extend to override single abilities (the shipped
 *    DenyingCommentPolicy fixture does exactly this).
 */
ArchPresets::finalByDefault('RoundlyConsulting\Comments', [
    Comment::class,
    CommentMention::class,
    CommentLock::class,
    CommentsException::class,
    CommentPolicy::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable model
 * is a PHP fatal the moment a host uses the seam the config documents (shops #19, teams #21,
 * advertisements #23, alerts #25, reports #33, posts #35, passkeys #37 — and jwt #4, found
 * once something finally looked).
 *
 * Comment is correctly non-final today; verified, not assumed. The preset also pins that
 * `comments.model` really defaults to Comment, so the seam cannot rot in the other
 * direction.
 */
ArchPresets::swappableModelsAreNotFinal([
    Comment::class => 'comments.model',
]);

/**
 * Comments does no cryptography. The ban is a standing guard against a mention token or a
 * moderation signature being hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Comments');

/**
 * Adopted, not rejected: comments has exactly the shape the preset targets — a real Eloquent
 * model behind a `*_model`-shaped key (`comments.model`), read through a Support seam
 * (`Support/CommentModel::class()` wrapping `ModelResolver::for('comments.model', …)`).
 *
 * The key is conventionally shaped (`model`), so the preset's inference finds it and the
 * stray-literal half is live without declaring `$modelKeys` — unlike alerts, whose four
 * seams are named `alert` / `health-check` / `silence-model` and needed declaring. Verified
 * by planting a stray `config('comments.model')` outside the seam and watching it go red.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

/**
 * The Dependency Policy as a test. No `alsoAllow`: comments' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is wrong
 * — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
