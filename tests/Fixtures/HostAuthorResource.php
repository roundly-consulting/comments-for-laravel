<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host's own author resource, swapped in through `comments.resources.author` — the shape a
 * real app adds (a display name, an avatar) around the actor model the package hands it.
 */
final class HostAuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'display_name' => 'Actor #'.$this->resource->getKey(),
        ];
    }
}
