<?php

declare(strict_types = 1);

namespace KirbyTools\Utils;

use Kirby\Cms\ModelWithContent;
use Kirby\Form\Form;

final class FieldResolver
{
    /**
     * Resolves blueprint field definitions from a model, excluding
     * `title` and `slug` fields and stripping field values.
     */
    public static function resolveModelFields(ModelWithContent $model): array
    {
        $fields = Form::for($model)->fields()->toArray();
        unset($fields['title'], $fields['slug']);

        foreach ($fields as $index => $props) {
            unset($fields[$index]['value']);
        }

        return $fields;
    }
}
