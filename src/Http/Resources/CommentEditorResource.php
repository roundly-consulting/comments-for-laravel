<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who made an edit, as {@see CommentRevisionResource} renders it: the key and the class
 * basename, never the stored morph type — on a host without a morph map that is the editor's
 * PHP class name, which no API client should see.
 *
 * Wraps the editor model. A host that wants more (a name, an avatar) points
 * `comments.resources.editor` at its own JsonResource, which receives the same model.
 *
 * @property Model $resource
 */
final class CommentEditorResource extends JsonResource
{
    /**
     * @return array{id: mixed, type: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'type' => class_basename($this->resource),
        ];
    }
}
