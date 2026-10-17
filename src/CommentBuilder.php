<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Models\Comment;

/**
 * The write side: `Comments::on($post)->as($user)->body('Hi')->reply($parent)->meta([...])->post()`. Posting
 * goes through {@see CommentsManager::write()}, so the fake records it.
 */
final class CommentBuilder
{
    private ?Model $author = null;

    private string $body = '';

    private bool $visible = true;

    private ?Comment $parent = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    public function __construct(
        private readonly CommentsManager $manager,
        private readonly Model $commentable,
    ) {}

    public function as(Model $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function visible(bool $visible = true): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function reply(Comment $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Attach metadata, stored in the comment's `meta` json column. Repeated calls merge: a
     * later call's top-level keys replace earlier ones, other keys are kept. Namespace your keys
     * (`['tickets' => [...]]`) so packages writing to the same comment never collide.
     *
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = array_replace($this->meta, $meta);

        return $this;
    }

    public function post(): Comment
    {
        return $this->manager->write(new WriteCommentData(
            commentable: $this->commentable,
            body: $this->body,
            author: $this->author,
            visible: $this->visible,
            parent: $this->parent,
            meta: $this->meta,
        ));
    }
}
