<?php

declare(strict_types=1);

namespace Momentum\Lock\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Momentum\Lock\Lock;
use Parental\HasChildren;
use Spatie\LaravelData\Concerns\WithDeprecatedCollectionMethod;
use Spatie\LaravelData\Contracts\DeprecatedData;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Str;

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

    public static function getModels(): Collection
    {
        $models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();
                $class = sprintf(
                    '\%s%s',
                    Container::getInstance()->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\')
                );

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;

                if (class_exists($class)) {
                    $reflection = new \ReflectionClass($class);
                    $valid = $reflection->isSubclassOf(Model::class) &&
                        !$reflection->isAbstract();
                }

                return $valid;
            });

        return $models->values();
    }

    public static function collect(mixed $items, ?string $into = null): array|DataCollection|PaginatedDataCollection|CursorPaginatedDataCollection|Enumerable|AbstractPaginator|PaginatorContract|AbstractCursorPaginator|CursorPaginatorContract|LazyCollection|Collection
    {
        $parent = parent::collect($items, $into);


        /** @var static $data */
        $data = $parent->through(function ($data, $key) use ($items) {
            $class = $data->modelClass ?? static::getModels()->filter(function (string $class) use ($data) {
                return str()
                        ->of($class)
                        ->afterLast('\\')
                        ->value()
                    ===
                        str()
                            ->of(class_basename($data))
                            ->replace('Index', '')
                            ->beforeLast('Data')
                            ->value();
            })
                ->firstOrFail();

            if (
                (\Composer\InstalledVersions::isInstalled('tightenco/parental')
                || \Composer\InstalledVersions::isInstalled('calebporzio/parental'))
                && trait_exists(HasChildren::class)
                && in_array(HasChildren::class, class_uses_recursive($class))
            ) {
                $inheritanceColumn = (new $class)->getInheritanceColumn();

                $hydarateData = array_merge($items[$key]->toArray(), [
                    $inheritanceColumn => $items[$key]->toArray()[$inheritanceColumn]['value'],
                ]);
            } else {
                $hydarateData = $items[$key]->toArray();
            }

            $data->setModel($class::hydrate([$hydarateData])->first());

            return $data;
        });

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
