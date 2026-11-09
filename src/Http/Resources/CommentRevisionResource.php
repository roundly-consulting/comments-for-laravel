<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Comments\Models\CommentRevision;
use RoundlyConsulting\Comments\Support\CommentsConfig;

/**
 * One entry of a comment's edit history: the revision number, the body that edit replaced,
 * the editor (through the configured `comments.resources.editor`, when `editor` is loaded) and
 * when the edit landed. Never the stored `editor_type`.
 *
 * @mixin CommentRevision
 */
final class CommentRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'revision' => $this->revision,
            'body' => $this->body,
            // Resolved in place, so `toArray()` holds a plain array; a deleted editor (loaded,
            // but null) renders null.
            'editor' => $this->whenLoaded('editor', static function (Model $editor) use ($request): array {
                $resource = CommentsConfig::editorResource();

                return (new $resource($editor))->resolve($request);
            }),
            'edited_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
