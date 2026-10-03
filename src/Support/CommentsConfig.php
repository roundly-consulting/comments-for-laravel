<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A default applies only when the key is absent (null). Anything present but unusable — `five`
 * for a length, `olderst` for an order, `pubilc` for a visibility, a blank disk — throws
 * {@see InvalidConfigurationException} naming the key, so a typo never quietly picks a side.
 *
 * @internal
 */
final class CommentsConfig
{
    public const string VISIBILITY_PRIVATE = 'private';

    public const string VISIBILITY_PUBLIC = 'public';

    public static function maxLength(): int
    {
        return Config::integer('comments.max_length', 5000, 1);
    }

    public static function maxDepth(): int
    {
        return Config::integer('comments.max_depth', 5, 1);
    }

    /** `latest` (newest first) or `oldest`. */
    public static function order(): string
    {
        return Config::oneOf('comments.order', ['latest', 'oldest'], 'latest');
    }

    /**
     * The banned words / patterns; empty when unset.
     *
     * @return list<string>
     */
    public static function blocklist(): array
    {
        return self::stringList('comments.blocklist', config('comments.blocklist') ?? []);
    }

    /** `reject`, `pending` or `hidden`. */
    public static function blocklistAction(): string
    {
        return Config::oneOf('comments.blocklist_action', ['reject', 'pending', 'hidden'], 'reject');
    }

    /**
     * The mention resolver as a callable, or null when unset. An invokable class-string or a
     * `[Class::class, 'method']` pair is built through the container; any other callable (a
     * closure works only while the config is not cached) is used as-is. Anything that does not
     * resolve to a callable — an unknown class, a missing method — throws.
     */
    public static function mentionResolver(): ?callable
    {
        $key = 'comments.mention_resolver';
        $resolver = config($key);

        if ($resolver === null) {
            return null;
        }

        if (is_string($resolver) && class_exists($resolver)) {
            $resolver = app($resolver);
        } elseif (is_array($resolver) && count($resolver) === 2 && is_string($resolver[0] ?? null)
            && is_string($resolver[1] ?? null) && class_exists($resolver[0])) {
            $resolver = [app($resolver[0]), $resolver[1]];
        }

        if (! is_callable($resolver)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be an invokable class-string or a [class-string, method] pair, [".self::describe(config($key)).'] given.',
            );
        }

        return $resolver;
    }

    /** `hide`, or null when the resolved-report path is disabled. */
    public static function onResolved(): ?string
    {
        return config('comments.moderation.on_resolved') === null
            ? null
            : Config::oneOf('comments.moderation.on_resolved', ['hide'], 'hide');
    }

    public static function attachmentsBucket(): string
    {
        return self::string('comments.media.attachments_bucket', 'attachments');
    }

    /** `private` or `public`. */
    public static function attachmentsVisibility(): string
    {
        return Config::oneOf(
            'comments.media.visibility',
            [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC],
            self::VISIBILITY_PRIVATE,
        );
    }

    /** The explicit media disk, or null to choose one by visibility. */
    public static function disk(): ?string
    {
        return config('comments.media.disk') === null ? null : self::string('comments.media.disk', '');
    }

    public static function privateDisk(): string
    {
        return self::string('comments.media.private_disk', 'local');
    }

    /**
     * The accepted mime types; an empty list (the default) accepts any type.
     *
     * @return list<string>
     */
    public static function acceptedMimeTypes(): array
    {
        return self::stringList('comments.media.accepted_mime_types', config('comments.media.accepted_mime_types') ?? []);
    }

    /** The attachment size cap in bytes, or null for media-library's own limit. */
    public static function maxFileSize(): ?int
    {
        return config('comments.media.max_file_size') === null
            ? null
            : Config::integer('comments.media.max_file_size', 1, 1);
    }

    /**
     * The responsive width ladder, or null for media-library's default ladder.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        $key = 'comments.media.responsive_widths';
        $widths = config($key);

        if ($widths === null) {
            return null;
        }

        if (! is_array($widths) || ! array_is_list($widths)) {
            throw self::notAList($key, 'positive integers', $widths);
        }

        $clean = [];

        foreach ($widths as $width) {
            // Validated under the setting's own key, so the message names it.
            $clean[] = Config::for([$key => $width])->integer($key, 1, 1);
        }

        return array_values(array_unique($clean));
    }

    /** Lifetime, in minutes, of a signed attachment URL; media-library's default when unset. */
    public static function temporaryUrlLifetime(): int
    {
        if (config('comments.media.temporary_url_lifetime') === null) {
            return Config::integer('media.temporary_url_default_lifetime', 5, 1);
        }

        return Config::integer('comments.media.temporary_url_lifetime', 5, 1);
    }

    /** The variant an inline token renders by default; `''` (the original) when unset. */
    public static function inlineDefaultVariant(): string
    {
        $key = 'comments.media.inline.default_variant';
        $variant = config($key) ?? '';

        if (! is_string($variant)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be a variant name (a string, '' for the original), [".self::describe($variant).'] given.',
            );
        }

        return $variant;
    }

    /** `strip` or `keep` an inline token whose media is missing. */
    public static function inlineOnMissing(): string
    {
        return Config::oneOf('comments.media.inline.on_missing', ['strip', 'keep'], 'strip');
    }

    private static function string(string $key, string $default): string
    {
        $value = config($key);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw self::notAList($key, 'non-empty strings', $values);
        }

        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw self::notAList($key, 'non-empty strings', $value);
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private static function notAList(string $key, string $of, mixed $value): InvalidConfigurationException
    {
        return new InvalidConfigurationException("Configuration value [{$key}] must be a list of {$of}, [".self::describe($value).'] given.');
    }

    private static function describe(mixed $value): string
    {
        return match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
