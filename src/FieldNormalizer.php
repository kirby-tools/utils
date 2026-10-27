<?php

declare(strict_types = 1);

namespace KirbyTools\Utils;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Form\Field;

final class FieldNormalizer
{
    /**
     * Field types that consumers treat as base types although Kirby's core
     * doesn't register them, like the markdown field plugin.
     */
    private const THIRD_PARTY_TYPES = ['markdown'];

    /**
     * Core field types keyed by their class name.
     */
    private static array|null $coreTypesByClass = null;

    /**
     * Resolves a field type to its core base type: the type of the nearest
     * core field class among its registered class and that class's parents.
     */
    public static function resolveBaseType(string $type): string
    {
        self::$coreTypesByClass ??= array_flip(App::instance()->core()->fields());

        if (in_array($type, self::THIRD_PARTY_TYPES, true) || in_array($type, self::$coreTypesByClass, true)) {
            return $type;
        }

        try {
            $class = Field::resolve($type);
        } catch (InvalidArgumentException) {
            return $type;
        }

        for ($ancestor = $class; $ancestor !== false; $ancestor = get_parent_class($ancestor)) {
            if (isset(self::$coreTypesByClass[$ancestor])) {
                return self::$coreTypesByClass[$ancestor];
            }
        }

        return $type;
    }

    /**
     * Normalizes a fields array by resolving custom types and
     * recursing into nested `fields` and `fieldsets[*].tabs[*].fields`.
     */
    public static function normalizeFields(array $fields): array
    {
        foreach ($fields as &$field) {
            if (isset($field['type'])) {
                $field['type'] = self::resolveBaseType($field['type']);
            }

            // `structure` and `object` fields carry their own fields.
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field['fields'] = self::normalizeFields($field['fields']);
            }

            // `blocks` and `layout` fields carry theirs inside fieldset tabs.
            if (isset($field['fieldsets']) && is_array($field['fieldsets'])) {
                foreach ($field['fieldsets'] as &$fieldset) {
                    if (isset($fieldset['tabs']) && is_array($fieldset['tabs'])) {
                        foreach ($fieldset['tabs'] as &$tab) {
                            if (isset($tab['fields']) && is_array($tab['fields'])) {
                                $tab['fields'] = self::normalizeFields($tab['fields']);
                            }
                        }
                    }
                }
            }
        }

        return $fields;
    }
}
