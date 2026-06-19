<?php

namespace Tests\Unit;

use App\Services\ProfilePictureUrlResolver;
use Tests\TestCase;

class ProfilePictureUrlResolverTest extends TestCase
{
    public function test_it_builds_authenticated_url_for_relative_path_without_double_slashes(): void
    {
        $url = app(ProfilePictureUrlResolver::class)->resolve('profile-pictures/avatar.jpg');

        $this->assertSame('/profile-pictures/profile-pictures/avatar.jpg', $url);
    }

    public function test_it_returns_absolute_urls_unchanged(): void
    {
        $absoluteUrl = 'https://cdn.example.com/avatar.jpg';

        $url = app(ProfilePictureUrlResolver::class)->resolve($absoluteUrl);

        $this->assertSame($absoluteUrl, $url);
    }

    public function test_it_returns_root_relative_storage_urls_unchanged(): void
    {
        $rootRelative = '/storage/profile-pictures/avatar.jpg';

        $url = app(ProfilePictureUrlResolver::class)->resolve($rootRelative);

        $this->assertSame($rootRelative, $url);
    }
}
