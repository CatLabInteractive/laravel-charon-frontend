<?php

namespace CatLab\CharonFrontend\Widgets;

use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Properties\ResourceField;

/**
 * Picks the widget for a field, in this order:
 *  1. a widget set for that field by display name (forField),
 *  2. for a relationship: a widget set for its related resource definition
 *     (forRelationship), else a LinkableWidget,
 *  3. a SelectWidget when the field has allowed values,
 *  4. the widget set for the field's type (forType), else the default for
 *     that type.
 */
class WidgetRegistry
{
    /**
     * @var Widget[]
     */
    private $fields = [];

    /**
     * @var Widget[]
     */
    private $relationships = [];

    /**
     * @var Widget[]
     */
    private $types = [];

    /**
     * @param string $displayName
     * @param Widget $widget
     * @return $this
     */
    public function forField(string $displayName, Widget $widget): self
    {
        $this->fields[$displayName] = $widget;
        return $this;
    }

    /**
     * @param string $resourceDefinitionClass The related definition (or a parent class of it).
     * @param Widget $widget
     * @return $this
     */
    public function forRelationship(string $resourceDefinitionClass, Widget $widget): self
    {
        $this->relationships[$resourceDefinitionClass] = $widget;
        return $this;
    }

    /**
     * @param string $type A Charon PropertyType, e.g. "string", "html", "dateTime".
     * @param Widget $widget
     * @return $this
     */
    public function forType(string $type, Widget $widget): self
    {
        $this->types[$type] = $widget;
        return $this;
    }

    /**
     * @param Field $field
     * @return Widget
     */
    public function resolve(Field $field): Widget
    {
        $name = $field->getDisplayName();
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        if ($field instanceof RelationshipField) {
            $definition = $field->getChildResourceDefinition();
            foreach ($this->relationships as $class => $widget) {
                if ($definition instanceof $class) {
                    return $widget;
                }
            }
            return new LinkableWidget();
        }

        if ($field instanceof ResourceField && count($field->getAllowedValues() ?? []) > 0) {
            return new SelectWidget();
        }

        $type = $field->getType();
        if (isset($this->types[$type])) {
            return $this->types[$type];
        }

        return $this->getDefaultForType($type);
    }

    /**
     * @param string|null $type
     * @return Widget
     */
    protected function getDefaultForType($type): Widget
    {
        switch ($type) {
            case 'dateTime':
                return new DateTimeWidget();

            case 'boolean':
                return new CheckboxWidget();

            case 'integer':
                return new NumberWidget([ 'step' => 1 ]);

            case 'number':
                return new NumberWidget();

            case 'html':
                return new TextareaWidget(true);

            default:
                return new TextWidget();
        }
    }
}
