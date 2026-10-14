<?php

namespace Illuminate\Http\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Wrap
{
    /**
     * Create a new attribute instance.
     *
     * @param  string|null  $key
     */
    public function __construct(public ?string $key)
    {
        //
    }
}
