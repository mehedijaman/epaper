<?php

namespace App\Services;

use App\Models\Edition;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

class PageImageUploadService
{
    public const STRATEGY_AUTO = 'auto';

    public const STRATEGY_FILENAME = 'filename';

    public const STRATEGY_NEXT_AVAILABLE = 'next_available';

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{warnings: array<int, string>, uploaded_count: int}
     */
    public function upload(
        Edition $edition,
        array $files,
        ?string $pageNoStrategy,
        ?int $uploadedBy,
        ?int $categoryId = null,
    ): array {
        $warnings = [];
        $disk = Storage::disk(config('epaper.disk'));
        $basePath = sprintf('epaper/%s', $edition->edition_date->format('Y-m-d'));
        $orderedFiles = $this->orderedFiles($files, $pageNoStrategy);
        $startPageNo = $this->startPageNo($edition, $orderedFiles, $pageNoStrategy);

        foreach ($orderedFiles as $index => $file) {
            $pageNo = $startPageNo + $index;
            $extension = $this->normalizeExtension($file);
            $token = Str::uuid()->toString();

            $fileName = sprintf('page-%04d-%s.%s', $pageNo, $token, $extension);
            $originalPath = sprintf('%s/original/%s', $basePath, $fileName);
            $largePath = sprintf('%s/large/%s', $basePath, $fileName);
            $thumbPath = sprintf('%s/thumb/%s', $basePath, $fileName);

            [$width, $height] = $this->dimensions($file);

            if ($width !== null && $height !== null && ($width < 1900 || $height < 2470)) {
                $warnings[] = sprintf(
                    '%s is below recommended resolution (%dx%d).',
                    $file->getClientOriginalName(),
                    $width,
                    $height,
                );
            }

            $existing = Page::query()
                ->where('edition_id', $edition->id)
                ->where('page_no', $pageNo)
                ->first();

            if ($existing !== null) {
                $this->deleteFiles($existing);
            }

            $disk->putFileAs(
                dirname($originalPath),
                $file,
                basename($originalPath),
            );

            $largeImage = Image::read($file->getRealPath())->scaleDown(width: 2000);
            $thumbImage = Image::read($file->getRealPath())->scaleDown(width: 400);

            $disk->put($largePath, $this->encodeImage($largeImage, $extension));
            $disk->put($thumbPath, $this->encodeImage($thumbImage, $extension));

            Page::query()->updateOrCreate(
                [
                    'edition_id' => $edition->id,
                    'page_no' => $pageNo,
                ],
                [
                    'category_id' => $categoryId ?? $existing?->category_id,
                    'image_original_path' => $this->normalizePath($originalPath),
                    'image_large_path' => $this->normalizePath($largePath),
                    'image_thumb_path' => $this->normalizePath($thumbPath),
                    'width' => $width,
                    'height' => $height,
                    'uploaded_by' => $uploadedBy,
                ],
            );
        }

        return [
            'warnings' => $warnings,
            'uploaded_count' => count($files),
        ];
    }

    public function replace(Page $page, UploadedFile $file, ?int $uploadedBy): void
    {
        $page->loadMissing('edition');

        $disk = Storage::disk(config('epaper.disk'));
        $basePath = sprintf('epaper/%s', $page->edition->edition_date->format('Y-m-d'));
        $extension = $this->normalizeExtension($file);
        $token = Str::uuid()->toString();
        $fileName = sprintf('page-%04d-%s.%s', $page->page_no, $token, $extension);
        $originalPath = sprintf('%s/original/%s', $basePath, $fileName);
        $largePath = sprintf('%s/large/%s', $basePath, $fileName);
        $thumbPath = sprintf('%s/thumb/%s', $basePath, $fileName);

        [$width, $height] = $this->dimensions($file);

        $this->deleteFiles($page);

        $disk->putFileAs(
            dirname($originalPath),
            $file,
            basename($originalPath),
        );

        $largeImage = Image::read($file->getRealPath())->scaleDown(width: 2000);
        $thumbImage = Image::read($file->getRealPath())->scaleDown(width: 400);

        $disk->put($largePath, $this->encodeImage($largeImage, $extension));
        $disk->put($thumbPath, $this->encodeImage($thumbImage, $extension));

        $page->forceFill([
            'image_original_path' => $this->normalizePath($originalPath),
            'image_large_path' => $this->normalizePath($largePath),
            'image_thumb_path' => $this->normalizePath($thumbPath),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $uploadedBy,
        ])->save();
    }

    public function deleteFiles(Page $page): void
    {
        $disk = Storage::disk(config('epaper.disk'));

        foreach ([$page->image_original_path, $page->image_large_path, $page->image_thumb_path] as $path) {
            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, UploadedFile>
     */
    private function orderedFiles(array $files, ?string $strategy): array
    {
        $items = collect($files)->values();

        if ($items->isEmpty()) {
            return [];
        }

        $resolvedStrategy = $this->resolveStrategy($items, $strategy);

        if ($resolvedStrategy !== self::STRATEGY_FILENAME) {
            return $items->all();
        }

        /** @var Collection<int, UploadedFile> $ordered */
        $ordered = $items->sortBy(function (UploadedFile $file): array {
            $number = $this->leadingNumber($file);

            return [
                $number ?? PHP_INT_MAX,
                strtolower($file->getClientOriginalName()),
            ];
        })->values();

        return $ordered->all();
    }

    /**
     * @param  Collection<int, UploadedFile>  $files
     */
    private function resolveStrategy(Collection $files, ?string $strategy): string
    {
        $requestedStrategy = $strategy ?? self::STRATEGY_AUTO;
        $allHaveNumbers = $files->every(fn (UploadedFile $file): bool => $this->leadingNumber($file) !== null);

        if ($requestedStrategy === self::STRATEGY_AUTO) {
            return $allHaveNumbers ? self::STRATEGY_FILENAME : self::STRATEGY_NEXT_AVAILABLE;
        }

        if ($requestedStrategy === self::STRATEGY_FILENAME && ! $allHaveNumbers) {
            throw ValidationException::withMessages([
                'files' => 'Filename strategy requires all files to start with a numeric prefix (e.g. 01.jpg).',
            ]);
        }

        return $requestedStrategy;
    }

    /**
     * @param  array<int, UploadedFile>  $orderedFiles
     */
    private function startPageNo(Edition $edition, array $orderedFiles, ?string $strategy): int
    {
        $files = collect($orderedFiles);

        if ($files->isEmpty()) {
            return 1;
        }

        $resolvedStrategy = $this->resolveStrategy($files, $strategy);

        if ($resolvedStrategy === self::STRATEGY_FILENAME) {
            $firstNumber = $this->leadingNumber($orderedFiles[0]);

            return max(1, $firstNumber ?? 1);
        }

        $maxPageNo = (int) Page::query()
            ->where('edition_id', $edition->id)
            ->max('page_no');

        return $maxPageNo + 1;
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return [null, null];
        }

        $size = @getimagesize($realPath);

        if (! is_array($size)) {
            return [null, null];
        }

        return [
            isset($size[0]) ? (int) $size[0] : null,
            isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    private function leadingNumber(UploadedFile $file): ?int
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if (! preg_match('/^(\d+)/', $name, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function normalizeExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->extension());

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    private function normalizePath(string $path): string
    {
        return ltrim($path, '/');
    }

    private function encodeImage(ImageInterface $image, string $extension): string
    {
        return match ($extension) {
            'jpg' => (string) $image->toJpeg(quality: 85),
            'png' => (string) $image->toPng(),
            'webp' => (string) $image->toWebp(quality: 85),
            default => (string) $image->toJpeg(quality: 85),
        };
    }
}
