<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(CommentModel::class())->toBe(Comment::class);
});

it('resolves a configured model that extends the packaged one', function (): void {
    config()->set('comments.model', CustomCommentTestModel::class);

    expect(CommentModel::class())->toBe(CustomCommentTestModel::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('comments.model', ActorTestModel::class);

    expect(fn (): string => CommentModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.model] must be a class-string of ['.Comment::class.'], ['.ActorTestModel::class.'] given.',
    );
});

it('throws when the configured model is not an eloquent model', function (): void {
    config()->set('comments.model', 'NotAModel');

    CommentModel::class();
})->throws(InvalidConfigurationException::class);
