<?php

namespace tallowandsons\cleaver\models;

use craft\base\Model;
use tallowandsons\cleaver\Cleaver;

/**
 * ChopConfig represents all user-defined options for a chop operation
 */
class ChopConfig extends Model
{
    /**
     * Section handles to chop from
     */
    public array $sectionHandles = [];

    /**
     * Percentage of entries to delete (1-100)
     */
    public int $percent = 90;

    /**
     * Entry statuses to consider (e.g. ['live', 'disabled'])
     */
    public array $statuses = [];

    /**
     * Minimum number of entries to keep per section (regardless of percentage)
     */
    public int $minimumEntries = 1;

    /**
     * Whether to use soft delete
     */
    public bool $softDelete = true;

    /**
     * If true, no entries are deleted (simulation mode)
     */
    public bool $dryRun = false;

    /**
     * If true, print detailed output
     */
    public bool $verbose = false;

    /**
     * Optional origin tag (e.g. 'cli' or 'cp')
     */
    public ?string $source = null;

    /**
     * Create a ChopConfig with defaults from plugin settings
     */
    public static function fromDefaults(): self
    {
        $settings = Cleaver::getInstance()->getSettings();

        return new self([
            'sectionHandles' => $settings->defaultSections,
            'percent' => $settings->defaultPercent,
            'minimumEntries' => $settings->minimumEntries,
            'statuses' => $settings->defaultStatuses,
            'softDelete' => $settings->defaultDeleteMode === Settings::DELETE_MODE_SOFT,
        ]);
    }

    /**
     * Set the source and return this instance for chaining
     */
    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function defineRules(): array
    {
        return [
            ['sectionHandles', 'each', 'rule' => ['string']],
            ['sectionHandles', 'default', 'value' => []],
            ['percent', 'integer', 'min' => 1, 'max' => 100],
            ['percent', 'default', 'value' => 90],
            ['statuses', 'each', 'rule' => ['string']],
            ['statuses', 'default', 'value' => []],
            ['minimumEntries', 'integer', 'min' => 0],
            ['minimumEntries', 'default', 'value' => 1],
            ['softDelete', 'boolean'],
            ['softDelete', 'default', 'value' => true],
            ['dryRun', 'boolean'],
            ['dryRun', 'default', 'value' => false],
            ['verbose', 'boolean'],
            ['verbose', 'default', 'value' => false],
            ['source', 'string'],
        ];
    }

    /**
     * Get a human-readable summary of the configuration
     */
    public function getSummary(): string
    {
        $parts = [];

        if (!empty($this->sectionHandles)) {
            $parts[] = "sections: " . implode(', ', $this->sectionHandles);
        } else {
            $parts[] = "sections: all";
        }

        $parts[] = "delete: {$this->percent}%";

        if (!empty($this->statuses)) {
            $parts[] = "statuses: " . implode(', ', $this->statuses);
        }

        $parts[] = "min keep: {$this->minimumEntries}";
        $parts[] = $this->softDelete ? "soft delete" : "hard delete";

        if ($this->dryRun) {
            $parts[] = "DRY RUN";
        }

        if ($this->source) {
            $parts[] = "via {$this->source}";
        }

        return implode(', ', $parts);
    }

    /**
     * Get the delete mode string for Settings comparison
     */
    public function getDeleteMode(): string
    {
        return $this->softDelete ? Settings::DELETE_MODE_SOFT : Settings::DELETE_MODE_HARD;
    }

    /**
     * Convert to array for job serialization
     */
    public function toJobArray(): array
    {
        return [
            'sectionHandles' => $this->sectionHandles,
            'percent' => $this->percent,
            'statuses' => $this->statuses,
            'minimumEntries' => $this->minimumEntries,
            'softDelete' => $this->softDelete,
            'dryRun' => $this->dryRun,
            'verbose' => $this->verbose,
            'source' => $this->source,
        ];
    }

    /**
     * Create from array (for job deserialization)
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
