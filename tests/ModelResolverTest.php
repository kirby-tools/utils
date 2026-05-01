<?php

declare(strict_types = 1);

use JohannSchopplich\KirbyTools\ModelResolver;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModelResolverTest extends TestCase
{
    private function app(): App
    {
        $app = new App([
            'roots' => ['index' => __DIR__],
            'options' => [
                'api' => ['allowImpersonation' => true],
            ],
            'site' => [
                'files' => [['filename' => 'banner.jpg']],
                'children' => [
                    [
                        'slug' => 'about',
                        'template' => 'default',
                        'files' => [['filename' => 'cover.jpg']],
                    ],
                ],
                'drafts' => [
                    ['slug' => 'draft-page', 'template' => 'default'],
                ],
            ],
            'users' => [
                [
                    'id' => 'admin',
                    'email' => 'admin@example.com',
                    'role' => 'admin',
                    'files' => [['filename' => 'avatar.jpg']],
                ],
            ],
        ]);

        $app->impersonate('admin@example.com');

        return $app;
    }

    #[Test]
    public function resolve_from_id_returns_site_for_site_keyword(): void
    {
        $this->app();

        $this->assertInstanceOf(Site::class, ModelResolver::resolveFromId('site'));
    }

    #[Test]
    public function resolve_from_id_returns_page_for_page_id(): void
    {
        $this->app();

        $page = ModelResolver::resolveFromId('about');

        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('about', $page->id());
    }

    #[Test]
    public function resolve_from_id_includes_drafts(): void
    {
        $this->app();

        $page = ModelResolver::resolveFromId('draft-page');

        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('draft-page', $page->id());
    }

    #[Test]
    public function resolve_from_id_returns_file_for_file_id(): void
    {
        $this->app();

        $file = ModelResolver::resolveFromId('about/cover.jpg');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('about/cover.jpg', $file->id());
    }

    #[Test]
    public function resolve_from_id_returns_null_for_unknown_id(): void
    {
        $this->app();

        $this->assertNull(ModelResolver::resolveFromId('nonexistent'));
    }

    #[Test]
    public function resolve_from_path_returns_site_for_site_keyword(): void
    {
        $this->app();

        $this->assertInstanceOf(Site::class, ModelResolver::resolveFromPath('site'));
    }

    #[Test]
    public function resolve_from_path_returns_page_for_pages_path(): void
    {
        $this->app();

        $page = ModelResolver::resolveFromPath('pages/about');

        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('about', $page->id());
    }

    #[Test]
    public function resolve_from_path_returns_page_file_for_pages_files_path(): void
    {
        $this->app();

        $file = ModelResolver::resolveFromPath('pages/about/files/cover.jpg');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('about/cover.jpg', $file->id());
    }

    #[Test]
    public function resolve_from_path_returns_site_file_for_site_files_path(): void
    {
        $this->app();

        $file = ModelResolver::resolveFromPath('site/files/banner.jpg');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('banner.jpg', $file->id());
    }

    #[Test]
    public function resolve_from_path_returns_user_file_for_users_files_path(): void
    {
        $this->app();

        $file = ModelResolver::resolveFromPath('users/admin/files/avatar.jpg');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('admin/avatar.jpg', $file->id());
    }

    #[Test]
    public function resolve_from_path_returns_account_file_for_account_files_path(): void
    {
        $this->app();

        $file = ModelResolver::resolveFromPath('account/files/avatar.jpg');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('admin/avatar.jpg', $file->id());
    }

    #[Test]
    public function resolve_from_path_returns_null_for_unrecognized_path(): void
    {
        $this->app();

        $this->assertNull(ModelResolver::resolveFromPath('foo/bar'));
    }
}
