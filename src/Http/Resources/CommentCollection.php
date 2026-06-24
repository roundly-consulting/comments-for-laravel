<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class CommentCollection extends ResourceCollection
{
    public $collects = CommentResource::class;
}
