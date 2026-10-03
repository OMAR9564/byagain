<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Shell;

use App\Services\Shell\NativeShell;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativeShellTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function agents(): array
    {
        $safari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

        return [
            'mobile safari' => [$safari.' Version/17.0 Safari/604.1', false],
            // The first build has no tab bar of its own: it must keep the page's.
            'app 1.0' => [$safari.' byagainApp/1.0', false],
            'app 2.0' => [$safari.' byagainApp/2.0', true],
            'app 12.3' => [$safari.' byagainApp/12.3', true],
        ];
    }

    #[Test]
    #[DataProvider('agents')]
    public function only_tabbed_builds_count_as_the_native_shell(string $agent, bool $expected): void
    {
        $request = Request::create('/', server: ['HTTP_USER_AGENT' => $agent]);

        $this->assertSame($expected, NativeShell::hasTabBar($request));
    }
}
