<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use KirbyTools\Utils\FieldResolver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FieldResolverTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App([
            'roots' => ['index' => __DIR__],
            'blueprints' => [
                'pages/test' => [
                    'title' => 'Test',
                    'fields' => [
                        'text' => ['type' => 'text'],
                        'description' => ['type' => 'textarea'],
                        'metaTitle' => ['type' => 'text'],
                        'sku' => ['type' => 'text', 'translate' => false],
                        'faq' => [
                            'type' => 'structure',
                            'fields' => [
                                'id' => ['type' => 'text', 'translate' => false],
                            ]
                        ],
                        'blocks' => [
                            'type' => 'blocks',
                            'fieldsets' => [
                                'note' => [
                                    'fields' => [
                                        'text' => ['type' => 'text', 'translate' => false],
                                    ]
                                ],
                            ]
                        ],
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'test-page',
                        'template' => 'test',
                        'content' => [
                            'title' => 'Test Page',
                            'text' => 'Hello World',
                            'description' => 'Some description',
                        ]
                    ]
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function resolves_fields_excluding_title_and_slug_and_omitting_values(): void
    {
        $page = $this->app->page('test-page');
        $fields = FieldResolver::resolveModelFields($page);

        // Contains blueprint-defined fields
        $this->assertArrayHasKey('text', $fields);
        $this->assertArrayHasKey('description', $fields);
        $this->assertSame('text', $fields['text']['type']);
        $this->assertSame('textarea', $fields['description']['type']);

        $this->assertArrayNotHasKey('title', $fields);
        $this->assertArrayNotHasKey('slug', $fields);

        $this->assertArrayNotHasKey('value', $fields['text']);
        $this->assertArrayNotHasKey('value', $fields['description']);
    }

    #[Test]
    public function keys_fields_by_their_lowercased_name(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));

        $this->assertArrayHasKey('metatitle', $fields);
        $this->assertArrayNotHasKey('metaTitle', $fields);
    }

    #[Test]
    public function exports_translate_at_every_depth(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));

        $this->assertTrue($fields['text']['translate']);
        $this->assertFalse($fields['sku']['translate']);
        $this->assertFalse($fields['faq']['fields']['id']['translate']);
        $this->assertFalse($fields['blocks']['fieldsets']['note']['tabs']['content']['fields']['text']['translate']);
    }
}
