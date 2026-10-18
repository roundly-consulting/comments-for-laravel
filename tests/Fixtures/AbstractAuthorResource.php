<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A JsonResource subclass that cannot be built — `comments.resources.author` refuses it up
 * front rather than fataling with "Cannot instantiate abstract class" mid-render.
 */
abstract class AbstractAuthorResource extends JsonResource {}
