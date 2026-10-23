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
                'blocks/teaser' => [
                    'name' => 'Teaser',
                    'tabs' => [
                        'content' => [
                            'fields' => [
                                'heading' => ['type' => 'text', 'copilot' => ['userPrompt' => 'Write a heading']],
                            ]
                        ],
                        'media' => [
                            'fields' => [
                                'image' => ['type' => 'files', 'translateInKirbyOnly' => true],
                            ]
                        ]
                    ]
                ],
                'pages/test' => [
                    'title' => 'Test',
                    'fields' => [
                        'text' => [
                            'type' => 'text',
                            'translateExternalOnly' => true,
                            'copilot' => ['userPrompt' => 'Write a tagline'],
                        ],
                        'description' => ['type' => 'textarea'],
                        'sku' => ['type' => 'text', 'translate' => false],
                        'image' => ['type' => 'files'],
                        'related' => ['type' => 'pages'],
                        'author' => ['type' => 'users'],
                        'faq' => [
                            'type' => 'structure',
                            'fields' => [
                                'question' => ['type' => 'text', 'translateInKirbyOnly' => true],
                                'id' => ['type' => 'text', 'translate' => false],
                            ]
                        ],
                        'meta' => [
                            'type' => 'object',
                            'fields' => [
                                'summary' => ['type' => 'textarea', 'copilot' => ['userPrompt' => 'Summarize']],
                            ]
                        ],
                        'blocks' => [
                            'type' => 'blocks',
                            'fieldsets' => [
                                'teaser',
                                'note' => [
                                    'fields' => [
                                        'text' => ['type' => 'text', 'translate' => false],
                                    ]
                                ],
                                'media' => [
                                    'type' => 'group',
                                    'fieldsets' => ['image'],
                                ],
                            ]
                        ],
                        'layout' => [
                            'type' => 'layout',
                            'fieldsets' => ['teaser'],
                            'settings' => [
                                'fields' => [
                                    'class' => ['type' => 'text', 'translate' => false],
                                ]
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
    public function resolves_picker_fields_to_kirby_6_type_names(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));

        $this->assertSame('filepicker', $fields['image']['type']);
        $this->assertSame('pagepicker', $fields['related']['type']);
        $this->assertSame('userpicker', $fields['author']['type']);
    }

    #[Test]
    public function merges_raw_blueprint_keys_back_into_fields(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));

        $this->assertTrue($fields['text']['translateExternalOnly']);
        $this->assertSame(['userPrompt' => 'Write a tagline'], $fields['text']['copilot']);
        $this->assertFalse($fields['sku']['translate']);

        // Resolved props win over raw blueprint keys.
        $this->assertSame('Text', $fields['text']['label']);
        $this->assertSame('filepicker', $fields['image']['type']);
    }

    #[Test]
    public function merges_raw_blueprint_keys_back_into_nested_fields(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));

        $this->assertTrue($fields['faq']['fields']['question']['translateInKirbyOnly']);
        $this->assertFalse($fields['faq']['fields']['id']['translate']);
        $this->assertSame(['userPrompt' => 'Summarize'], $fields['meta']['fields']['summary']['copilot']);
    }

    #[Test]
    public function merges_raw_blueprint_keys_back_into_fieldset_tabs(): void
    {
        $fields = FieldResolver::resolveModelFields($this->app->page('test-page'));
        $fieldsets = $fields['blocks']['fieldsets'];

        $this->assertSame(['userPrompt' => 'Write a heading'], $fieldsets['teaser']['tabs']['content']['fields']['heading']['copilot']);
        $this->assertTrue($fieldsets['teaser']['tabs']['media']['fields']['image']['translateInKirbyOnly']);
        $this->assertSame('filepicker', $fieldsets['teaser']['tabs']['media']['fields']['image']['type']);

        // Loose fieldset fields land in a `content` tab.
        $this->assertFalse($fieldsets['note']['tabs']['content']['fields']['text']['translate']);

        // Grouped fieldsets are flattened.
        $this->assertArrayHasKey('image', $fieldsets);

        $layout = $fields['layout'];
        $this->assertSame(['userPrompt' => 'Write a heading'], $layout['fieldsets']['teaser']['tabs']['content']['fields']['heading']['copilot']);
        $this->assertFalse($layout['settings']['tabs']['content']['fields']['class']['translate']);
    }

    #[Test]
    public function resolves_fields_in_the_current_translation(): void
    {
        App::destroy();

        $app = new App([
            'roots' => ['index' => __DIR__],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'blueprints' => [
                'pages/test' => [
                    'title' => 'Test',
                    'fields' => [
                        'text' => ['type' => 'text', 'translateExternalOnly' => true],
                        'sku' => ['type' => 'text', 'translate' => false],
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'test-page',
                        'template' => 'test',
                        'content' => ['title' => 'Test Page', 'text' => 'Hello World'],
                    ]
                ]
            ]
        ]);
        $app->setCurrentLanguage('de');

        $fields = FieldResolver::resolveModelFields($app->page('test-page'));

        $this->assertSame(['text', 'sku'], array_keys($fields));
        $this->assertTrue($fields['text']['translateExternalOnly']);
        $this->assertFalse($fields['sku']['translate']);
        $this->assertArrayNotHasKey('value', $fields['text']);
    }
}
