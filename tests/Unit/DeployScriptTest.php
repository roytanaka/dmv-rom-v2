<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Regression guard for issue #120: the deploy script must use the
// locale-aware route cache commands. Stock `route:cache` only registers the
// default locale at cache-build time, so every `/fr/` route 404s on the
// deployed server (mcamara/laravel-localization). This footgun already
// shipped once — keep it from coming back.
class DeployScriptTest extends TestCase
{
    private function deployScript(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/scripts/deploy.sh');
    }

    public function test_deploy_script_uses_locale_aware_route_cache(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('route:trans:cache', $script);
        $this->assertStringContainsString('route:trans:clear', $script);
    }

    public function test_deploy_script_has_no_bare_route_cache_command(): void
    {
        $script = $this->deployScript();

        // Match a `route:cache`/`route:clear` invocation that is NOT the
        // locale-aware `route:trans:cache`/`route:trans:clear` form.
        $this->assertDoesNotMatchRegularExpression(
            '/route:(?!trans:)(cache|clear)/',
            $script,
            'scripts/deploy.sh uses stock route:cache/route:clear — use route:trans:cache/route:trans:clear (#120).',
        );
    }
}
