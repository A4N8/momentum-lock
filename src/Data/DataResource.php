<?php

declare(strict_types=1);

namespace Momentum\Lock\Data;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Momentum\Lock\Lock;
use Spatie\LaravelData\Concerns\WithDeprecatedCollectionMethod;
use Spatie\LaravelData\Contracts\DeprecatedData;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;

class DataResource extends Data implements DeprecatedData
{
    use WithDeprecatedCollectionMethod;

    protected ?Model $model;

    /** @var null|array */
    protected $permissions = null;

    protected string $modelClass;

    public static function from(mixed ...$payloads): static
    {
        /** @var static $data */
        $data = parent::from(...$payloads);

        if (count($payloads) === 1 && $payloads[0] instanceof Model) {
            $data->setModel($payloads[0]);
        }

        return $data;
    }

    public static function collect(mixed $items, ?string $into = null): array|DataCollection|PaginatedDataCollection|CursorPaginatedDataCollection|Enumerable|AbstractPaginator|PaginatorContract|AbstractCursorPaginator|CursorPaginatorContract|LazyCollection|Collection
    {
        $originalItems = static::extractOriginalItems($items);

        $parentData = parent::collect($items, $into);

        if (is_array($parentData)) {
            foreach ($parentData as $key => $data) {
                $parentData[$key] = static::attachOriginalModel($data, $originalItems[$key] ?? null);
            }

            return $parentData;
        }

        if ($parentData instanceof Collection || $parentData instanceof \Illuminate\Database\Eloquent\Collection) {
            return $parentData->transform(fn ($data, $key) => static::attachOriginalModel($data, $originalItems[$key] ?? null));
        }

        return $parentData->through(fn ($data, $key) => static::attachOriginalModel($data, $originalItems[$key] ?? null));
    }

    protected static function extractOriginalItems(mixed $items): array
    {
        if (
            $items instanceof PaginatorContract
            || $items instanceof AbstractPaginator
            || $items instanceof CursorPaginatorContract
            || $items instanceof AbstractCursorPaginator
        ) {
            return $items->items();
        }

        if ($items instanceof Enumerable) {
            return $items->all();
        }

        return is_array($items) ? $items : [];
    }

    protected static function attachOriginalModel(mixed $data, mixed $originalItem): mixed
    {
        if ($data instanceof static && $originalItem instanceof Model) {
            $data->setModel($originalItem);
        }

        return $data;
    }

    protected function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    protected function appendPermissions(): void
    {
        if (isset($this->model)) {
            $this->additional([
                'permissions' => Lock::getPermissions($this->model, $this->permissions),
            ]);
        }
    }

    public function transform(
        null|TransformationContextFactory|TransformationContext $transformationContext = null,
    ): array {
        $this->appendPermissions();

        return parent::transform($transformationContext);
    }
}
