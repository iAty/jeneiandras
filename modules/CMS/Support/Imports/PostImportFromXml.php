<?php

namespace Juzaweb\CMS\Support\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Juzaweb\CMS\Support\Collections\BloggerCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Juzaweb\Backend\Models\Post;
use Juzaweb\Backend\Models\Taxonomy;
use Juzaweb\CMS\Support\FileManager;

class PostImportFromXml
{
    protected string $file;

    protected string $type;

    protected int $chunkSize = 50;

    protected int $userId;

    protected array $errors;

    public function __construct()
    {
        //
    }

    public function import(string $file, string $type): static
    {
        $this->file = $file;

        $this->type = $type;

        $data = $this->getCacheInfo();
        $collection = $this->collection();

        if (empty($data)) {
            $cacheData = [
                'total' => $collection->count(),
                'next_page' => 1,
                'max_page' => $collection->chunk($this->chunkSize)->count()
            ];

            $data = $this->setCacheInfo($cacheData);
        }

        $items = $collection->forPage($data['next_page'], $this->chunkSize);

        $this->importItems($items);

        if ($data['next_page'] + 1 <= $data['max_page']) {
            $this->setCacheInfo(['next_page' => $data['next_page'] + 1]);
        } else {
            Cache::store('file')->forget($this->getCacheKey());
        }

        return $this;
    }

    public function setUserID(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function importItems($items): void
    {
        foreach ($items as $item) {
            $item = (array) $item;
            DB::beginTransaction();
            try {
                if ($item['thumbnail'] && is_url($item['thumbnail'])) {
                    $thumb = FileManager::addFile(
                        $item['thumbnail'],
                        'image',
                        null,
                        $this->userId
                    );
                    $item['thumbnail'] = $thumb->path;
                } else {
                    $item['thumbnail'] = null;
                }

                $item['type'] = 'posts';
                $item['status'] = 'publish';
                $item['created_by'] = $this->userId;
                $item['updated_by'] = $this->userId;

                $model = new Post();
                $model->fill($item);
                $model->slug = $model->generateSlug($item['slug']);
                $model->save();
                $model->syncTaxonomies(
                    [
                        'categories' => $this->getCreateTaxonomies($item['categories'])
                    ]
                );
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                report($e);
                $this->errors[] = $e->getMessage();
            }
        }
    }

    protected function getCreateTaxonomies(array $categories): array
    {
        $taxonomies = [];

        foreach ($categories as $category) {
            $taxonomy = Taxonomy::firstOrCreate(
                [
                    'name' => $category,
                    'taxonomy' => 'categories',
                    'post_type' => 'posts'
                ]
            );

            $taxonomies[] = $taxonomy->id;
        }

        return $taxonomies;
    }

    protected function setCacheInfo(array $update): ?array
    {
        $info = $this->getCacheInfo([]);

        $info = array_merge($info, $update);

        Cache::store('file')->forever($this->getCacheKey(), $info);

        return $info;
    }

    public function getCacheInfo($default = null): ?array
    {
        return Cache::store('file')->get($this->getCacheKey(), $default);
    }

    protected function collection(): bool|Collection
    {
        return match ($this->type) {
            'blogger' => app(BloggerCollection::class)->getCollection($this->getFilePath()),
            default => false,
        };
    }

    protected function getCacheKey(): string
    {
        return cache_prefix('imports_' . md5($this->file));
    }

    protected function getFilePath(): string
    {
        return Storage::disk('public')->path($this->file);
    }
}
