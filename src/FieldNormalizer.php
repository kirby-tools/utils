<?php

declare(strict_types = 1);

namespace KirbyTools\Utils;

use Kirby\Cms\App;
use Kirby\Form\Field;

final class FieldNormalizer
{
    private const SUPPORTED_TYPES = [
        'blocks', 'buttons', 'checkboxes', 'color', 'date', 'email', 'entries',
        'filelist', 'filepicker', 'gap', 'headline', 'hidden', 'info', 'layout',
        'line', 'link', 'list', 'markdown', 'multiselect', 'number', 'object',
        'pagelist', 'pagepicker', 'password', 'radio', 'range', 'select', 'slug',
        'stats', 'structure', 'tags', 'tel', 'text', 'textarea', 'time', 'toggle',
        'toggles', 'url', 'userpicker', 'writer',
    ];

    private static array|null $supportedTypesMap = null;

    /**
     * Resolves a custom field type to its standard base type: the type of
     * the nearest core field class its registered class extends.
     */
    public static function resolveBaseType(string $type): string
    {
        self::$supportedTypesMap ??= array_flip(self::SUPPORTED_TYPES);

        if (isset(self::$supportedTypesMap[$type])) {
            return $type;
        }

        $class = Field::$types[$type] ?? null;

        if (!is_string($class) || !class_exists($class)) {
            return $type;
        }

        $coreTypes = array_flip(App::instance()->core()->fields());

        for ($ancestor = $class; $ancestor !== false; $ancestor = get_parent_class($ancestor)) {
            if (isset($coreTypes[$ancestor])) {
                return $coreTypes[$ancestor];
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
