<?php

namespace HasanAlyazidi\DataTables\View;

/**
 * Minimal stand-in for Laravel's ComponentAttributeBag.
 *
 * Theme views print {{ $attributes->merge([...]) }}. Inside <x-datatable>
 * the framework supplies the real bag, but the @datatable directive renders
 * the same views as plain views (and Laravel 6 has no component bag at
 * all) — so the renderer hands them this object instead. It implements
 * exactly what the theme views use: merge() and printing.
 */
class AttributeBag
{
    /**
     * @var array
     */
    protected $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Merge defaults behind the given attributes. Like the real bag,
     * 'class' values concatenate (defaults first) instead of replacing.
     *
     * @return static
     */
    public function merge(array $defaults = [])
    {
        $attributes = $this->attributes;

        foreach ($defaults as $key => $value) {
            if ($key === 'class') {
                $existing = isset($attributes['class']) ? $attributes['class'] : '';
                $attributes['class'] = trim($value.' '.$existing);

                continue;
            }

            if (! array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        return new static($attributes);
    }

    public function __toString()
    {
        $html = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $value = $key;
            }

            $html .= ' '.$key.'="'.e($value).'"';
        }

        return trim($html);
    }
}
