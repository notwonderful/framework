<?php

namespace Illuminate\Http\Resources\Json;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Attributes\Wrap;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;

class ResourceResponse implements Responsable
{
    /**
     * The underlying resource.
     *
     * @var mixed
     */
    public $resource;

    /**
     * The cached "Wrap" attribute keys for each resource class, or false when there is none.
     *
     * @var array<class-string, string|null|false>
     */
    protected static $cachedWrapAttributes = [];

    /**
     * Create a new resource response.
     *
     * @param  mixed  $resource
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse($request)
    {
        return tap(response()->json(
            $this->wrap(
                $this->resource->resolve($request),
                $this->resource->with($request),
                $this->resource->additional
            ),
            $this->calculateStatus(),
            [],
            $this->resource->jsonOptions()
        ), function ($response) use ($request) {
            $response->original = $this->resource->resource;

            $this->resource->withResponse($request, $response);
        });
    }

    /**
     * Wrap the given data if necessary.
     *
     * @param  \Illuminate\Support\Collection|array  $data
     * @param  array  $with
     * @param  array  $additional
     * @return array
     */
    protected function wrap($data, $with = [], $additional = [])
    {
        if ($data instanceof Collection) {
            $data = $data->all();
        }

        if ($this->haveDefaultWrapperAndDataIsUnwrapped($data)) {
            $data = [$this->wrapper() => $data];
        } elseif ($this->haveAdditionalInformationAndDataIsUnwrapped($data, $with, $additional)) {
            $data = [($this->wrapper() ?? 'data') => $data];
        }

        return array_merge_recursive($data, $with, $additional);
    }

    /**
     * Determine if we have a default wrapper and the given data is unwrapped.
     *
     * @param  array  $data
     * @return bool
     */
    protected function haveDefaultWrapperAndDataIsUnwrapped($data)
    {
        if ($this->resource instanceof JsonResource && $this->resource::$forceWrapping) {
            return $this->wrapper() !== null;
        }

        return $this->wrapper() && ! array_key_exists($this->wrapper(), $data);
    }

    /**
     * Determine if "with" data has been added and our data is unwrapped.
     *
     * @param  array  $data
     * @param  array  $with
     * @param  array  $additional
     * @return bool
     */
    protected function haveAdditionalInformationAndDataIsUnwrapped($data, $with, $additional)
    {
        return (! empty($with) || ! empty($additional)) &&
               (! $this->wrapper() ||
                ! array_key_exists($this->wrapper(), $data));
    }

    /**
     * Get the default data wrapper for the resource.
     *
     * @return string
     */
    protected function wrapper()
    {
        $class = get_class($this->resource);

        if (! array_key_exists($class, static::$cachedWrapAttributes)) {
            $attribute = (new ReflectionClass($class))->getAttributes(Wrap::class)[0] ?? null;

            static::$cachedWrapAttributes[$class] = $attribute && (new ReflectionProperty($class, 'wrap'))->getDeclaringClass()->getName() !== $class
                ? $attribute->newInstance()->key
                : false;
        }

        return static::$cachedWrapAttributes[$class] !== false
            ? static::$cachedWrapAttributes[$class]
            : $class::$wrap;
    }

    /**
     * Calculate the appropriate status code for the response.
     *
     * @return int
     */
    protected function calculateStatus()
    {
        return $this->resource->resource instanceof Model &&
               $this->resource->resource->wasRecentlyCreated ? 201 : 200;
    }
}
