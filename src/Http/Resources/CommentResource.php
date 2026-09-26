<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Comments\Models\Comment;
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
        return [
            'id' => $this->id,
            'body' => $this->comment,
            'status' => $this->status->value,
            'visible' => $this->visible,
            'parent_id' => $this->parent_id,
            'locked' => $this->isLocked(),
            'author' => $this->whenLoaded('actor', fn (): array => [
                'type' => $this->actor_type,
                'id' => $this->actor_id,
            ]),
            // Compact like payload (count / viewer_state / breakdown) from
            // likes-for-laravel; only rendered when the caller opts in by loading
            // the `likes` relation. The viewer is never resolved from the guard.
            'likes' => $this->when(
                $this->relationLoaded('likes'),
                fn (): array => $this->likeState(),
            ),
            'attachments' => $this->when(
                $this->relationLoaded('media'),
                fn (): array => $this->attachments()
                    ->map(fn (Media $media): array => [
                        'id' => $media->uuid,
                        // Signed for a private attachment, never its public URL.
                        'url' => $this->resolveAttachmentUrl($media),
                    ])
                    ->values()
                    ->all(),
            ),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
