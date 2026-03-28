<?php

declare(strict_types = 1);

use JohannSchopplich\KirbyTools\FieldNormalizer;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'fields' => [
                'custom-writer' => [
                    'extends' => 'writer',
                ],
                'custom-files' => [
                    'extends' => 'files',
                ],
                'deep-custom' => [
                    'extends' => 'custom-writer',
                ],
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
        $this->assertSame('files', FieldNormalizer::resolveBaseType('files'));
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('writer'));
    }

    #[Test]
    public function resolve_base_type_resolves_custom_type(): void
    {
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('custom-writer'));
        $this->assertSame('files', FieldNormalizer::resolveBaseType('custom-files'));
    }

    #[Test]
    public function resolve_base_type_resolves_multi_level_chain(): void
    {
        // deep-custom → custom-writer → writer
        $this->assertSame('writer', FieldNormalizer::resolveBaseType('deep-custom'));
    }

    #[Test]
    public function resolve_base_type_returns_unknown_type_as_is(): void
    {
        $result = @FieldNormalizer::resolveBaseType('nonexistent-field-type');
        $this->assertSame('nonexistent-field-type', $result);
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
        $this->assertSame('files', $blockFields['image']['type']);
    }

    #[Test]
    public function normalize_fields_preserves_non_type_properties(): void
    {
        $fields = [
            'text' => [
                'type' => 'text',
                'translate' => true,
                'translateInKirbyOnly' => false,
            ],
            'image' => [
                'type' => 'files',
                'translateInKirbyOnly' => true,
            ],
        ];

        $normalized = FieldNormalizer::normalizeFields($fields);

        $this->assertTrue($normalized['text']['translate']);
        $this->assertFalse($normalized['text']['translateInKirbyOnly']);
        $this->assertTrue($normalized['image']['translateInKirbyOnly']);
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

        // Options are passed through as-is (normalization is the consumer's responsibility)
        $this->assertSame(['grid' => 'Grid', 'list' => 'List'], $normalized['style']['options']);
    }
}
