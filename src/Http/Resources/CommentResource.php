<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Comments\Models\Comment;

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
            'reaction_counts' => $this->when(
                $this->relationLoaded('reactions'),
                fn (): array => $this->reactionCounts(),
            ),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
