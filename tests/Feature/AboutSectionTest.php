<?php

declare(strict_types=1);

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`, so every "does not leak" check was vacuous. Comments had NO about test at
 * all, so this is new coverage rather than a replacement.
 *
 * Comments has no API key. What it must never render is the **blocklist** — the moderation
 * terms a site filters on are the one genuinely sensitive thing in this config: publishing
 * them tells an abuser exactly which words to route around, which is why the provider
 * reports a count and never the terms. `mustRender` is asserted BEFORE any secret check and
 * throws at call time if empty, so this cannot degrade into the purchases shape.
 */
it('renders the comments section without leaking the moderation blocklist', function (): void {
    config()->set('comments.blocklist', ['swearword-alpha', 'slur-beta', 'spam-gamma']);
    config()->set('comments.require_approval', true);
    config()->set('comments.authorization', true);
    config()->set('comments.moderation.auto_hide', true);
    config()->set('comments.media.inline.enabled', true);

    expect('comments')->toLeakNoSecrets(
        secrets: [
            // Publishing the blocklist is a moderation-evasion manual.
            'swearword-alpha',
            'slur-beta',
            'spam-gamma',
        ],
        mustRender: [
            // The count itself must render — the positive proof the line reports rather
            // than being silently empty, which is the whole purchases lesson.
            '3 term(s)',
            'Comment',
            'Require approval',
            'ON',
            'chars',
        ],
    );
});

/**
 * The switches render as switches, and an empty blocklist reports NONE rather than an empty
 * string. Kept separate: it is a rendering pin, not a leak pin, and folding it into the case
 * above would need the opposite config.
 */
it('reports the configured model and switches in the about section', function (): void {
    config()->set('comments.blocklist', []);
    config()->set('comments.require_approval', false);
    config()->set('comments.authorization', false);
    config()->set('comments.moderation.auto_hide', false);
    config()->set('comments.media.inline.enabled', false);

    expect('comments')->toLeakNoSecrets(
        secrets: ['swearword-alpha'],
        mustRender: ['Comment', 'NONE', 'OFF'],
    );
});
