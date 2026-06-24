<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('loads an approved comment count onto an instance', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->count(2)->create();
    Comment::factory()->for($post, 'commentable')->pending()->create();

    $post->loadCommentCount();

    expect($post->comments_count)->toBe(2);
});

it('adds comment counts via the query scope without N+1', function (): void {
    $a = PostTestModel::create();
    $b = PostTestModel::create();
    Comment::factory()->for($a, 'commentable')->count(2)->create();
    Comment::factory()->for($b, 'commentable')->create();

    $posts = PostTestModel::withCommentCounts()->get();

    expect($posts->firstWhere('id', $a->id)->comments_count)->toBe(2)
        ->and($posts->firstWhere('id', $b->id)->comments_count)->toBe(1);
});
