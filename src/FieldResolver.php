<?php

declare(strict_types = 1);

namespace KirbyTools\Utils;

use Kirby\Blueprint\Blueprint;
use Kirby\Cms\App;
use Kirby\Cms\Fieldsets;
use Kirby\Cms\ModelWithContent;
use Kirby\Form\Form;

final class FieldResolver
{
    /**
     * Resolves blueprint field definitions from a model, excluding
     * `title` and `slug` fields and stripping field values.
     *
     * Kirby's fields export only their declared props, so the raw blueprint
     * keys they drop – `translate` and custom keys like `copilot` – are
     * merged back by field name, down to nested and block fields.
     */
    public static function resolveModelFields(ModelWithContent $model): array
    {
        $form = Form::for($model, language: $model->kirby()->languageCode());

        $fields = self::mergeBlueprintKeys(
            $form->fields()->toArray(),
            $model->blueprint()->fields()
        );
        unset($fields['title'], $fields['slug']);

        foreach ($fields as $index => $props) {
            unset($fields[$index]['value']);
        }

        return $fields;
    }

    /**
     * Adds each raw blueprint key a resolved field lacks, recursing into
     * nested `fields`, `fieldsets[*].tabs[*].fields` and layout `settings`.
     */
    private static function mergeBlueprintKeys(array $fields, array $definitions): array
    {
        foreach ($fields as $name => $props) {
            $definition = $definitions[$name] ?? null;

            if (!is_array($props) || !is_array($definition)) {
                continue;
            }

            // `structure` and `object` fields carry their own fields.
            if (is_array($props['fields'] ?? null) && is_array($definition['fields'] ?? null)) {
                $props['fields'] = self::mergeBlueprintKeys($props['fields'], $definition['fields']);
            }

            // `blocks` and `layout` fields carry theirs inside fieldset tabs.
            if (is_array($props['fieldsets'] ?? null)) {
                $fieldsets = self::fieldsetDefinitions($definition['fieldsets'] ?? null);

                foreach ($props['fieldsets'] as $type => $fieldset) {
                    if (is_array($fieldset) && isset($fieldsets[$type])) {
                        $props['fieldsets'][$type] = self::mergeFieldsetKeys($fieldset, $fieldsets[$type]);
                    }
                }
            }

            if (is_array($props['settings'] ?? null) && isset($definition['settings'])) {
                $props['settings'] = self::mergeFieldsetKeys($props['settings'], Blueprint::extend($definition['settings']));
            }

            $fields[$name] = $props + $definition;
        }

        return $fields;
    }

    /**
     * Resolves a `fieldsets` definition into raw fieldset definitions by type,
     * the way `Fieldsets::factory()` expands references and flattens groups.
     */
    private static function fieldsetDefinitions(mixed $fieldsets): array
    {
        $fieldsets ??= App::instance()->option('blocks.fieldsets', Fieldsets::DEFAULT);
        $definitions = [];

        if (!is_array($fieldsets)) {
            return $definitions;
        }

        foreach ($fieldsets as $type => $fieldset) {
            if (is_int($type) && is_string($fieldset)) {
                $type = $fieldset;
                $fieldset = 'blocks/' . $type;
            }

            if ($fieldset === false) {
                continue;
            }

            if ($fieldset === true) {
                $fieldset = 'blocks/' . $type;
            }

            $fieldset = Blueprint::extend($fieldset);
            $fieldset['type'] ??= $type;

            if ($fieldset['type'] === 'group') {
                $definitions = [...$definitions, ...self::fieldsetDefinitions($fieldset['fieldsets'] ?? [])];
                continue;
            }

            $definitions[$fieldset['type']] = $fieldset;
        }

        return $definitions;
    }

    /**
     * Merges the raw field definitions of a fieldset into its resolved tabs,
     * the way `Fieldset` wraps loose `fields` in a `content` tab.
     */
    private static function mergeFieldsetKeys(array $fieldset, array $definition): array
    {
        $tabs = empty($definition['tabs'])
            ? ['content' => ['fields' => $definition['fields'] ?? []]]
            : $definition['tabs'];

        foreach ($fieldset['tabs'] ?? [] as $name => $tab) {
            if (!is_array($tab) || !is_array($tab['fields'] ?? null) || !isset($tabs[$name])) {
                continue;
            }

            $tabDefinition = Blueprint::extend($tabs[$name]);

            $fieldset['tabs'][$name]['fields'] = self::mergeBlueprintKeys(
                $tab['fields'],
                Blueprint::fieldsProps($tabDefinition['fields'] ?? [])
            );
        }

        return $fieldset;
    }
}
