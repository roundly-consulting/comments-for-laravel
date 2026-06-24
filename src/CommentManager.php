<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Actions\ApproveCommentAction;
use RoundlyConsulting\Comments\Actions\DeleteCommentAction;
use RoundlyConsulting\Comments\Actions\HideCommentAction;
use RoundlyConsulting\Comments\Actions\RestoreCommentAction;
use RoundlyConsulting\Comments\Actions\UpdateCommentAction;
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Models\Comment;

final class CommentManager
{
    public function __construct(
        private readonly WriteCommentAction $write,
        private readonly UpdateCommentAction $update,
        private readonly DeleteCommentAction $delete,
        private readonly RestoreCommentAction $restore,
        private readonly ApproveCommentAction $approve,
        private readonly HideCommentAction $hide,
    ) {}

    /**
     * Start a fluent comment on the given subject.
     */
    public function on(Model $commentable): CommentBuilder
    {
        return new CommentBuilder($this, $commentable);
    }

    public function write(WriteCommentData $data): Comment
    {
        return $this->write->execute($data);
    }

    public function update(Comment $comment, string $body): Comment
    {
        return $this->update->execute(new UpdateCommentData(comment: $comment, body: $body));
    }

    public function delete(Comment $comment): void
    {
        $this->delete->execute($comment);
    }

    public function restore(Comment $comment): Comment
    {
        return $this->restore->execute($comment);
    }

    public function approve(Comment $comment): Comment
    {
        return $this->approve->execute($comment);
    }

    public function hide(Comment $comment): Comment
    {
        return $this->hide->execute($comment);
    }
}
