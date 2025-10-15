<?php
declare(strict_types=1);

namespace AwesomeNova\Filters;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Filters\Filter;

class DependentFilter extends Filter
{
    /**
     * @var callable|null
     */
    protected $optionsCallback;

    /**
     * @var callable|null
     */
    protected $applyCallback;

    /**
     * @var string[]
     */
    public $dependentOf = [];

    /**
     * Default value.
     *
     * @var mixed
     */
    public $default = '';

    /**
     * @var string
     */
    public $attribute;

    /**
     * @var bool
     */
    public $hideWhenEmpty = false;

    /**
     * @var string
     */
    public $component = 'awesome-nova-dependent-filter';

    /**
     * RelatedFilter constructor.
     * @param string|null $name
     * @param string|null $attribute
     */
    public function __construct(?string $name = null, ?string $attribute = null)
    {
        $this->name = $name ?? $this->name;
        $this->attribute = $attribute ?? $this->attribute ?? str_replace(' ', '_', Str::lower($this->name()));
    }

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value): mixed
    {
        if ($this->applyCallback) {
            return call_user_func($this->applyCallback, $request, $query, $value);
        }

        return $query->whereIn($this->attribute, (array)$value);
    }

    /**
     * Get the key for the filter.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->attribute;
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  array $filters
     * @return array|\Illuminate\Support\Collection
     */
    public function options(Request $request, array $filters = []): mixed
    {
        return call_user_func($this->optionsCallback, $request, $filters);
    }

    /**
     * @param  mixed $filter
     * @return $this
     */
    final public function dependentOf(mixed $filter): self
    {
        if (! is_array($filter)) {
            $filter = func_get_args();
        }

        $this->dependentOf = $filter;

        return $this;
    }

    /**
     * @param  \Illuminate\Http\Request $request
     * @param  array $filters
     * @return array
     */
    final public function getOptions(Request $request, array $filters = []): array
    {
        return collect(
            $this->options($request, $filters + array_fill_keys($this->dependentOf, ''))
        )->map(function ($value, $key) {
            return is_array($value) ? ($value + ['value' => $key]) : ['label' => $value, 'value' => $key];
        })->values()->all();
    }

    /**
     * @param  callable|array $callback
     * @param  mixed $dependentOf
     * @return $this
     */
    final public function withOptions(mixed $callback, mixed $dependentOf = null): self
    {
        if (! is_callable($callback)) {
            $callback = function () use ($callback) {
                return $callback;
            };
        }

        $this->optionsCallback = $callback;

        if (! is_null($dependentOf)) {
            $this->dependentOf($dependentOf);
        }

        return $this;
    }

    /**
     * Set the default value for the filter.
     *
     * @param  mixed $value
     * @return $this
     */
    final public function withDefault(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Get the default options for the filter.
     *
     * @return mixed
     */
    public function default(): mixed
    {
        return $this->default;
    }

    /**
     * Set callback for apply method.
     *
     * @param  callable $callback
     * @return $this
     */
    final public function withApply(callable $callback): self
    {
        $this->applyCallback = $callback;
        return $this;
    }

    /**
     * @param  bool $value
     * @return $this
     */
    public function hideWhenEmpty(bool $value = true): self
    {
        $this->hideWhenEmpty = $value;

        return $this;
    }

    /**
     * Prepare the filter for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge([
            'class' => $this->key(),
            'name' => $this->name(),
            'component' => $this->component(),
            'options' => count($this->dependentOf) === 0 ? $this->getOptions(app(Request::class)) : [],
            'currentValue' => $this->default() ?? '',
            'dependentOf' => $this->dependentOf,
            'hideWhenEmpty' => $this->hideWhenEmpty,
        ], $this->meta());
    }

    /**
     * @param  mixed ...$args
     * @return self
     */
    public static function make(mixed ...$args): self
    {
        return new static(...$args);
    }
}
