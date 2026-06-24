<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Models\Comment;

final class CommentBuilder
{
    private ?Model $author = null;

    private string $body = '';

    private bool $visible = true;

    private ?Comment $parent = null;

    public function __construct(
        private readonly CommentManager $manager,
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

    public function post(): Comment
    {
        return $this->manager->write(new WriteCommentData(
            commentable: $this->commentable,
            body: $this->body,
            author: $this->author,
            visible: $this->visible,
            parent: $this->parent,
        ));
    }
}
