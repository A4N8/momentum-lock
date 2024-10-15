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
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;

class DataResource extends Data
{
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
        $parentData = parent::collect($items, $into);

        $modelClass = $parentData->items()->first()?->modelClass;

        if (filled($modelClass)) {
            $models = $modelClass::whereIn('id', $parentData->items()->pluck('id'))->get();

            /** @var static $data */
            $data = parent::collect($items, $into)->through(function ($data, $key) use ($items, $models) {
                if ($models->contains($data->id)) {
                    $data->setModel($models->only($data->id)->first());
                }

                return $data;
            });
        } else {
            $data = $parentData;
        }

        if ($data instanceof PaginatedDataCollection) {
            return new PaginatedDataCollection($data->dataClass, $data->items());
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
