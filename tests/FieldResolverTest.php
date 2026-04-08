<?php

declare(strict_types = 1);

use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FieldResolverTest extends TestCase
{
    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => ['index' => __DIR__],
            'blueprints' => [
                'pages/test' => [
                    'title' => 'Test',
                    'fields' => [
                        'text' => ['type' => 'text'],
                        'description' => ['type' => 'textarea'],
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
    public function resolves_fields_excluding_title_slug_and_values(): void
    {
        $page = $this->kirby->page('test-page');
        $fields = FieldResolver::resolveModelFields($page);

        // Contains blueprint-defined fields
        $this->assertArrayHasKey('text', $fields);
        $this->assertArrayHasKey('description', $fields);
        $this->assertSame('text', $fields['text']['type']);
        $this->assertSame('textarea', $fields['description']['type']);

        // Excludes title and slug
        $this->assertArrayNotHasKey('title', $fields);
        $this->assertArrayNotHasKey('slug', $fields);

        // Values are stripped
        $this->assertArrayNotHasKey('value', $fields['text']);
        $this->assertArrayNotHasKey('value', $fields['description']);
    }
}
