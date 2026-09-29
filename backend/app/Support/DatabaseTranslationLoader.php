<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Translation\FileLoader;

/**
 * lang/{locale}/{group}.php files, with rows from the translations table layered on top
 * so admins can edit strings without touching files.
 */
class DatabaseTranslationLoader extends FileLoader
{
    public static function from(FileLoader $loader): self
    {
        $db = new self($loader->files, $loader->paths);
        $db->jsonPaths = $loader->jsonPaths;
        $db->hints = $loader->hints;

        return $db;
    }

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        if (($namespace !== null && $namespace !== '*') || $group === '*') {
            return $lines;
        }

        // ponytail: one query per locale+group per request; cache here if it shows up in profiling.
        try {
            $rows = Translation::where('locale', $locale)->where('group', $group)->pluck('value', 'key')->all();
        } catch (QueryException) {
            return $lines; // table not migrated yet (install wizard, fresh checkout)
        }

        return array_replace_recursive($lines, Arr::undot($rows));
    }
}
