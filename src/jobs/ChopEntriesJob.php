<?php

namespace tallowandsons\cleaver\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Chop Entries Job queue job
 */
class ChopEntriesJob extends BaseJob
{
    function execute($queue): void
    {
        // ...
    }

    protected function defaultDescription(): ?string
    {
        return null;
    }
}
