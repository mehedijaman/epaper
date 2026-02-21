<?php

use App\Support\DiskUrl;

uses(Tests\TestCase::class);

it('normalizes absolute local disk urls to relative paths', function (): void {
    config()->set('filesystems.disks.epaper_test_abs', [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
    ]);

    $url = DiskUrl::fromPath('epaper_test_abs', '/epaper/2026-02-20/thumb/page-0001.jpg');

    expect($url)->toBe('/storage/epaper/2026-02-20/thumb/page-0001.jpg');
});

it('keeps relative local disk urls unchanged', function (): void {
    config()->set('filesystems.disks.epaper_test_relative', [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => '/storage',
        'visibility' => 'public',
        'throw' => false,
    ]);

    $url = DiskUrl::fromPath('epaper_test_relative', '/epaper/2026-02-20/thumb/page-0001.jpg');

    expect($url)->toBe('/storage/epaper/2026-02-20/thumb/page-0001.jpg');
});
