<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment's author, as {@see CommentResource} renders it: the key and the class basename,
 * never the stored morph type — on a host without a morph map that is the author's PHP class
 * name, which no API client should see.
 *
 * Wraps the actor model. A host that wants more (a name, an avatar) points
 * `comments.resources.author` at its own JsonResource, which receives the same model.
 *
 * @property Model $resource
 */
final class CommentAuthorResource extends JsonResource
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
