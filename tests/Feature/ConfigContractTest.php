<?php

declare(strict_types=1);

/**
 * C — the config-key contract, pinned in both directions.
 *
 * Reads are scraped from source **tokens**, never a regex — media #27's near-miss was a
 * regex over raw text satisfied by a *docblock mention* of the key, which stayed green with
 * the fix reverted. A docblock is a comment token here, never a read.
 *
 *  - forward — every key the code reads is shipped (shops #18: a whole feature reading
 *    `shops.payments.*` while the file shipped `payment.*`, green because the suite set the
 *    same wrong key the code read);
 *  - reverse — every shipped leaf is read (alerts #24; media #27's `max_file_size` cap that
 *    never applied). Comments' config is long and heavily commented, which is exactly the
 *    soil dead config grows in.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/comments.php')->toSatisfyConfigContract([__DIR__.'/../../src', __DIR__.'/../../database'], [
        // `comments.model` is read through the toolkit's `ModelResolver::for('comments.model', …)`
        // seam rather than a `config()` call. It is a real read — it drives the whole model
        // swap — but it is not a `config(` token, so the prefix is what makes it visible to
        // the scraper. The key is named EXACTLY rather than using a blanket `'comments.'`:
        // that would count any string literal under the prefix as a read, including
        // translation keys like `comments::comments.locked`, which are not config keys at
        // all and would silently satisfy the reverse direction (the trap alerts hit with its
        // `alerts.health` route name).
        // `comments.key_type` is read through `KeyType::fromConfig(…)` in the migration
        // (a scanned `database/` path), not a `config(` token, so it is named here too.
        // The rest are read through the strict readers in Support\CommentsConfig —
        // `Config::oneOf(…)` and the class's own string / list / resolver helpers — which are
        // not `config(` tokens either, so each is named exactly for the same reason.
        'extraReadPrefixes' => [
            'comments.model',
            'comments.key_type',
            'comments.order',
            'comments.blocklist_action',
            'comments.mention_resolver',
            'comments.media.attachments_bucket',
            'comments.media.visibility',
            'comments.media.private_disk',
            'comments.media.responsive_widths',
            'comments.media.inline.default_variant',
            'comments.media.inline.on_missing',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but CommentsServiceProvider's `contributesToAbout()` closure reads
        // '.require_approval', '.authorization', '.moderation.auto_hide' and
        // '.media.inline.enabled' for real (and the rest through CommentsConfig). Excluding
        // it would discard real readers for nothing.
    ]);
});
