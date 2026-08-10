<?php

declare(strict_types = 1);

use JohannSchopplich\KirbyTools\QueryResolver;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class QueryResolverTest extends TestCase
{
    private function articlePage(): Page
    {
        $app = new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'children' => [
                    [
                        'slug' => 'about',
                        'template' => 'default',
                        'content' => ['title' => 'Analyzing Kirby']
                    ]
                ]
            ]
        ]);

        return $app->page('about');
    }

    #[Test]
    public function resolve_replaces_a_placeholder_with_its_query_result(): void
    {
        $this->assertSame(
            'Analyzing Kirby',
            QueryResolver::resolve($this->articlePage(), '{{ page.title }}')
        );
    }

    #[Test]
    public function resolve_replaces_every_placeholder_in_one_value(): void
    {
        $this->assertSame(
            'Analyzing Kirby – about',
            QueryResolver::resolve($this->articlePage(), '{{ page.title }} – {{ page.slug }}')
        );
    }

    #[Test]
    public function resolve_keeps_the_text_around_a_placeholder(): void
    {
        $this->assertSame(
            'SEO: Analyzing Kirby!',
            QueryResolver::resolve($this->articlePage(), 'SEO: {{ page.title }}!')
        );
    }

    #[Test]
    public function resolve_empties_a_placeholder_whose_query_returns_null(): void
    {
        $this->assertSame(
            '',
            QueryResolver::resolve($this->articlePage(), '{{ page.nonexistent }}')
        );
    }

    #[Test]
    public function resolve_returns_a_value_without_a_placeholder_unchanged(): void
    {
        $this->assertSame(
            'developers',
            QueryResolver::resolve($this->articlePage(), 'developers')
        );
    }

    #[Test]
    public function resolve_returns_the_fallback_for_a_null_value(): void
    {
        $this->assertSame(
            'developers',
            QueryResolver::resolve($this->articlePage(), null, 'developers')
        );
    }

    #[Test]
    public function resolve_returns_null_for_a_null_value_without_a_fallback(): void
    {
        $this->assertNull(QueryResolver::resolve($this->articlePage(), null));
    }

    #[Test]
    public function resolve_returns_a_list_of_placeholders_unchanged(): void
    {
        $synonyms = ['{{ page.title }}', 'kirby'];

        $this->assertSame(
            $synonyms,
            QueryResolver::resolve($this->articlePage(), $synonyms)
        );
    }
}
