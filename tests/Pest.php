<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Tests\Fixtures\SwappedModelsTestCase;
use RoundlyConsulting\Comments\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the Configured directory below needs a different
// base case (comments.model swapped BEFORE boot) and a blanket bind would claim it first —
// Pest binds a test case per directory, not per file. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the `comments.model` config default and so needs the
// app booted; an arch file is not automatically test-cased.
uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proof needs `comments.model` pointed at a host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory.
uses(SwappedModelsTestCase::class)->in('Configured');
