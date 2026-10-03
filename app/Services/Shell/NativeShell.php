<?php

declare(strict_types=1);

namespace App\Services\Shell;

use Illuminate\Http\Request;

/**
 * Whether the page is being drawn inside the iPhone app's native tab bar.
 *
 * The app names itself in the user agent as `byagainApp/<major>`. Only builds
 * from 2 on have a native tab bar; 1.x was a single web view that relies on
 * the page's own bar to get anywhere, so hiding the bar from it would leave a
 * reader with no way out of the first screen until they rebuild.
 */
final class NativeShell
{
    private const FIRST_TABBED_MAJOR = 2;

    public static function hasTabBar(Request $request): bool
    {
        if (preg_match('#byagainApp/(\d+)#', (string) $request->userAgent(), $match) !== 1) {
            return false;
        }

        return (int) $match[1] >= self::FIRST_TABBED_MAJOR;
    }
}
