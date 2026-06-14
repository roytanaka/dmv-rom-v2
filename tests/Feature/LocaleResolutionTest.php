<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Seam A — locale resolution at the HTTP/Inertia boundary (ADR-0008's both-URLs
// smoke test). For each translatable route, the English URL resolves locale `en`
// and the /fr/ twin resolves locale `fr` to the SAME page component. Written as a
// data provider so route twins added in later slices (#109) just extend the set.
class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{en: string, fr: string, component: string}>
     */
    public static function translatableRoutes(): array
    {
        return [
            'dashboard' => ['en' => '/dashboard', 'fr' => '/fr/tableau-de-bord', 'component' => 'Dashboard'],
        ];
    }

    #[DataProvider('translatableRoutes')]
    public function test_english_url_resolves_the_english_locale(string $en, string $fr, string $component): void
    {
        $this->actingAs(User::factory()->create());

        $this->get($en)->assertInertia(
            fn (Assert $page) => $page->component($component)->where('locale', 'en')
        );
    }

    #[DataProvider('translatableRoutes')]
    public function test_french_twin_resolves_the_french_locale_and_same_component(string $en, string $fr, string $component): void
    {
        $this->actingAs(User::factory()->create());

        $this->withLocaleRoutes('fr', function () use ($fr, $component) {
            $this->get($fr)->assertInertia(
                fn (Assert $page) => $page->component($component)->where('locale', 'fr')
            );
        });
    }
}
