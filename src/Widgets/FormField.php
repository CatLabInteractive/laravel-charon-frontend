<?php

namespace CatLab\CharonFrontend\Widgets;

use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\RESTResource;
use Illuminate\Support\Str;

/**
 * One field of a form: the definition's field, the resource being edited
 * (if any), and the names its inputs use. Every input of a field lives
 * under fields[<display name>].
 */
class FormField
{
    /**
     * @var Field
     */
    private $field;

    /**
     * @var RESTResource|null
     */
    private $resource;

    /**
     * @var string[]
     */
    private $options = [];

    /**
     * @param Field $field
     * @param RESTResource|null $resource
     */
    public function __construct(Field $field, ?RESTResource $resource = null)
    {
        $this->field = $field;
        $this->resource = $resource;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getResource(): ?RESTResource
    {
        return $this->resource;
    }

    public function getName(): string
    {
        return $this->field->getDisplayName();
    }

    /**
     * The definition's label() when it has one, the display name in words
     * otherwise ("is_published" and "isPublished" both become "Is published").
     */
    public function getLabel(): string
    {
        $label = $this->field->getLabel();
        if ($label !== ucfirst($this->getName())) {
            return $label;
        }

        return Str::ucfirst(str_replace('_', ' ', Str::snake($this->getName())));
    }

    public function getDescription(): ?string
    {
        return $this->field->getDescription() ?: null;
    }

    public function isRelationship(): bool
    {
        return $this->field instanceof RelationshipField;
    }

    /**
     * An array field, or a relationship to many.
     */
    public function isMultiple(): bool
    {
        if ($this->field instanceof RelationshipField) {
            return $this->field->getCardinality() === Cardinality::MANY;
        }

        return method_exists($this->field, 'isArray') && $this->field->isArray();
    }

    /**
     * The resource's current value: a scalar or array for a field, the
     * identifier (or identifiers, for many) for a relationship.
     *
     * @return mixed
     */
    public function getValue()
    {
        if (!$this->resource) {
            return $this->isMultiple() ? [] : null;
        }

        $property = $this->resource->getProperties()->getProperty($this->field);
        $value = $property ? $property->getValue() : null;

        if (!$this->isRelationship()) {
            return $value ?? ($this->isMultiple() ? [] : null);
        }

        if ($this->isMultiple()) {
            $out = [];
            foreach (($value ?? []) as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $out[] = $item['id'];
                }
            }
            return $out;
        }

        return is_array($value) && isset($value['id']) ? $value['id'] : null;
    }

    /**
     * Old input after a failed submit, else $default.
     *
     * @param string $path Below the field, in dot notation ("id", "date").
     * @param mixed $default
     * @return mixed
     */
    public function old(string $path = '', $default = null)
    {
        return old($this->getDotName($path), $default);
    }

    /**
     * Whether the form was redisplayed after a failed submit.
     */
    public function hasOldInput(): bool
    {
        $old = old();
        return is_array($old) && count($old) > 0;
    }

    /**
     * @param string $suffix Bracketed, e.g. "[id]" or "[]".
     */
    public function getInputName(string $suffix = ''): string
    {
        return 'fields[' . $this->getName() . ']' . $suffix;
    }

    /**
     * @param string $path Below the field, in dot notation.
     */
    public function getDotName(string $path = ''): string
    {
        return 'fields.' . $this->getName() . ($path !== '' ? '.' . $path : '');
    }

    public function getId(string $suffix = ''): string
    {
        return 'field-' . $this->getName() . ($suffix !== '' ? '-' . $suffix : '');
    }

    /**
     * @return string[] Label by value; allowed values or linkable resources.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string[] $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}
