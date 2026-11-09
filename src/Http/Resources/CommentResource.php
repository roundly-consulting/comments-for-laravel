<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentRevision;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * @mixin Comment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A soft-deleted comment under `comments.deleted_comments = placeholder` keeps its
        // place in the thread, but nothing it said or who said it: only the structure renders.
        $placeholder = $this->isPlaceholder();

        return [
            'id' => $this->id,
            'body' => $placeholder ? null : $this->comment,
            'status' => $this->status->value,
            'visible' => $this->visible,
            'parent_id' => $this->parent_id,
            'locked' => $this->isLocked(),
            'deleted' => $this->when(
                CommentsConfig::deletedCommentsAsPlaceholders(),
                fn (): bool => $this->trashed(),
            ),
            // Through the configured author resource, never the stored `actor_type`: without a
            // morph map that is the author's class name. Resolved here, so `toArray()` still
            // holds a plain array. A deleted author (loaded, but null) renders null.
            'author' => $placeholder
                ? $this->whenLoaded('actor', static fn (): null => null)
                : $this->whenLoaded('actor', static function (Model $actor) use ($request): array {
                    $resource = CommentsConfig::authorResource();

                    return (new $resource($actor))->resolve($request);
                }),
            // Compact like payload (count / viewer_state / breakdown) from
            // likes-for-laravel; only rendered when the caller opts in by loading
            // the `likes` relation. The viewer is never resolved from the guard.
            'likes' => $this->when(
                ! $placeholder && $this->relationLoaded('likes'),
                fn (): array => $this->likeState(),
            ),
            'attachments' => $this->when(
                ! $placeholder && $this->relationLoaded('media'),
                fn (): array => $this->attachments()
                    ->map(fn (Media $media): array => [
                        'id' => $media->uuid,
                        // Signed for a private attachment, never its public URL.
                        'url' => $this->resolveAttachmentUrl($media),
                    ])
                    ->values()
                    ->all(),
            ),
            // The edit history (`comments.edit_history`), oldest edit first; only rendered when
            // the caller loads `revisions` (and `revisions.editor` for the editors).
            'history' => $this->when(
                ! $placeholder && $this->relationLoaded('revisions'),
                fn (): array => $this->revisions
                    ->map(static fn (CommentRevision $revision): array => (new CommentRevisionResource($revision))->resolve($request))
                    ->values()
                    ->all(),
            ),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
