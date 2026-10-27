<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Form\Field\FilePickerField;
use Kirby\Form\Field\StringField;
use Kirby\Form\Field\TextareaField;
use Kirby\Form\Field\WriterField;
use KirbyTools\Utils\FieldNormalizer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FieldNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'fields' => [
                'custom-writer' => CustomWriterField::class,
                'custom-files' => CustomFilePickerField::class,
                'deep-custom' => DeepCustomField::class,
                'custom-string' => CustomStringField::class,
                'writer-alias' => WriterField::class,
                'markdown' => MarkdownField::class,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function resolve_base_type_returns_known_type_as_is(): void
    {
        $this->assertSame('text', FieldNormalizer::resolveBaseType('text'));
        $this->assertSame('blocks', FieldNormalizer::resolveBaseType('blocks'));
        $this->assertSame('filepicker', FieldNormalizer::resolveBaseType('filepicker'));
        $this->assertSame('pagepicker', FieldNormalizer::resolveBaseType('pagepicker'));
        $this->assertSame('userpicker', FieldNormalizer::resolveBaseType('userpicker'));
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('writer'));
    }

    #[Test]
    public function resolve_base_type_resolves_custom_type(): void
    {
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('custom-writer'));
        $this->assertSame('filepicker', FieldNormalizer::resolveBaseType('custom-files'));
    }

    #[Test]
    public function resolve_base_type_resolves_multi_level_chain(): void
    {
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('deep-custom'));
    }

    #[Test]
    public function resolve_base_type_resolves_type_registered_with_core_class(): void
    {
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('writer-alias'));
    }

    #[Test]
    public function resolve_base_type_returns_type_without_core_ancestor_as_is(): void
    {
        $this->assertSame('custom-string', FieldNormalizer::resolveBaseType('custom-string'));
    }

    #[Test]
    public function resolve_base_type_keeps_markdown_although_its_class_extends_a_core_field(): void
    {
        $this->assertSame('markdown', FieldNormalizer::resolveBaseType('markdown'));
    }

    #[Test]
    public function resolve_base_type_returns_unknown_type_as_is(): void
    {
        $this->assertSame('nonexistent-field-type', FieldNormalizer::resolveBaseType('nonexistent-field-type'));
        $this->assertSame('pages', FieldNormalizer::resolveBaseType('pages'));
    }

    #[Test]
    public function normalize_fields_resolves_nested_custom_types(): void
    {
        $fields = [
            'title' => ['type' => 'text'],
            'content' => [
                'type' => 'structure',
                'fields' => [
                    'heading' => ['type' => 'text'],
                    'body' => ['type' => 'custom-writer'],
                ],
            ],
        ];

        $normalized = FieldNormalizer::normalizeFields($fields);

        $this->assertSame('writer', $normalized['content']['fields']['body']['type']);
    }

    #[Test]
    public function normalize_fields_resolves_custom_types_in_fieldset_tabs(): void
    {
        $fields = [
            'blocks' => [
                'type' => 'blocks',
                'fieldsets' => [
                    'myblock' => [
                        'tabs' => [
                            'content' => [
                                'fields' => [
                                    'text' => ['type' => 'custom-writer'],
                                    'image' => ['type' => 'custom-files'],
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $normalized = FieldNormalizer::normalizeFields($fields);

        $blockFields = $normalized['blocks']['fieldsets']['myblock']['tabs']['content']['fields'];
        $this->assertSame('writer', $blockFields['text']['type']);
        $this->assertSame('filepicker', $blockFields['image']['type']);
    }

    #[Test]
    public function normalize_fields_preserves_non_type_properties(): void
    {
        $fields = [
            'text' => [
                'type' => 'text',
                'translate' => true,
                'copilot' => false,
            ],
            'image' => [
                'type' => 'filepicker',
                'copilot' => ['userPrompt' => 'Describe the image'],
            ],
        ];

        $normalized = FieldNormalizer::normalizeFields($fields);

        $this->assertTrue($normalized['text']['translate']);
        $this->assertFalse($normalized['text']['copilot']);
        $this->assertSame(['userPrompt' => 'Describe the image'], $normalized['image']['copilot']);
    }

    #[Test]
    public function normalize_fields_preserves_options(): void
    {
        $fields = [
            'style' => [
                'type' => 'select',
                'options' => [
                    'grid' => 'Grid',
                    'list' => 'List',
                ],
            ],
        ];

        $normalized = FieldNormalizer::normalizeFields($fields);

        // Options are passed through as-is (normalization is the consumer's responsibility).
        $this->assertSame(['grid' => 'Grid', 'list' => 'List'], $normalized['style']['options']);
    }
}

class CustomWriterField extends WriterField
{
}

final class CustomFilePickerField extends FilePickerField
{
}

final class DeepCustomField extends CustomWriterField
{
}

final class CustomStringField extends StringField
{
}

final class MarkdownField extends TextareaField
{
}
