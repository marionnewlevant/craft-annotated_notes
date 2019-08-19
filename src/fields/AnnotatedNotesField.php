<?php
/**
 * Annotated Notes plugin for Craft CMS 3.x
 *
 * Field for multiple notes with automatic annotation
 *
 * @link      http://marion.newlevant.com
 * @copyright Copyright (c) 2019 Marion Newlevant
 */

namespace marionnewlevant\annotatednotes\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Element;
use craft\fields\Table;
use craft\helpers\Json;
use craft\web\assets\timepicker\TimepickerAsset;
use craft\web\assets\tablesettings\TableSettingsAsset;

/**
 * @author    Marion Newlevant
 * @package   AnnotatedNotes
 * @since     1.0.0
 */
class AnnotatedNotesField extends Table
{
    // Public Properties
    // =========================================================================

    public $annotationTwig = '';
    public $annotationHeading = 'Annotation';
    public $noteHeading = 'Notes';
    public $noteColumnWidth = 80;

    /**
     * @var array|null The columns that should be shown in the table
     */
    public $columns = [];

    /**
     * @var array The default row values that new elements should have
     */
    public $defaults = [];

    public $annotation = '';

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('annotated-notes', 'Annotated Notes');
    }

    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->columns = [
            'col1' => [
                'heading' => $this->noteHeading,
                'handle' => 'note',
                'width' => ''.$this->noteColumnWidth.'%',
                'type' => 'multiline'
            ],
            'col2' => [
                'heading' => $this->annotationHeading,
                'handle' => 'annotation',
                'width' => ''.(100-$this->noteColumnWidth).'%',
                'type' => 'singleline'
            ],
        ];
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules = array_merge($rules, [
            [['annotationTwig', 'annotationHeading', 'noteHeading'], 'string'],
            ['annotationTwig', 'default', 'value' => ''],
            ['annotationHeading', 'default', 'value' => 'Annotation'],
            ['noteHeading', 'default', 'value' => 'Note'],
            ['noteColumnWidth', 'number', 'integerOnly' => true, 'min' => 10, 'max' => 90],
            ['noteColumnWidth', 'default', 'value' => 80],
        ]);

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        $typeOptions = [
            'multiline' => Craft::t('app', 'Multi-line text'),
            'singleline' => Craft::t('app', 'Single-line text'),
        ];

        // Make sure they are sorted alphabetically (post-translation)
        asort($typeOptions);

        $columnSettings = [
            'heading' => [
                'heading' => Craft::t('app', 'Column Heading'),
                'type' => 'singleline',
                'autopopulate' => 'handle'
            ],
            'handle' => [
                'heading' => Craft::t('app', 'Handle'),
                'code' => true,
                'type' => 'singleline'
            ],
            'width' => [
                'heading' => Craft::t('app', 'Width'),
                'code' => true,
                'type' => 'singleline',
                'width' => 50
            ],
            'type' => [
                'heading' => Craft::t('app', 'Type'),
                'class' => 'thin',
                'type' => 'select',
                'options' => $typeOptions,
            ],
        ];

        $view = Craft::$app->getView();

        $view->registerAssetBundle(TableSettingsAsset::class);
        $view->registerJs('new Craft.TableFieldSettings(' .
            Json::encode($view->namespaceInputName('columns'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($view->namespaceInputName('defaults'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($this->columns, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($this->defaults, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($columnSettings, JSON_UNESCAPED_UNICODE) .
            ');');

        $columnsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', [
            [
                'label' => Craft::t('app', 'Table Columns'),
                'instructions' => Craft::t('annotated-notes', 'These are the columns your table will have.'),
                'id' => 'columns',
                'name' => 'columns',
                'cols' => $columnSettings,
                'rows' => $this->columns,
                'initJs' => false,
                'static' => true // make it not editable
            ]
        ]);

        $defaultsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', [
            [
                'label' => Craft::t('app', 'Default Values'),
                'instructions' => Craft::t('app', 'Define the default values for the field.'),
                'id' => 'defaults',
                'name' => 'defaults',
                'cols' => $this->columns,
                'rows' => $this->defaults,
                'initJs' => false
            ]
        ]);

        $annotationField = $view->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('annotated-notes', 'Annotation pattern'),
                'instructions' => Craft::t('annotated-notes', 'instructions...'),
            ]
        ]);

        return $view->renderTemplate('annotated-notes/_components/fields/AnnotatedNotesField_settings', [
            'field' => $this,
            'columnsField' => $columnsField,
            'defaultsField' => $defaultsField,
            'annotationField' => $annotationField,
        ]);
    }


    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Register our asset bundle
        Craft::$app->getView()->registerAssetBundle(TimepickerAsset::class);

        /** @var Element $element */
        if (empty($this->columns)) {
            return '';
        }

        // Translate the column headings
        foreach ($this->columns as &$column) {
            if (!empty($column['heading'])) {
                $column['heading'] = Craft::t('site', $column['heading']);
            }
        }
        unset($column);

        if (!is_array($value)) {
            $value = [];
        }

        // Explicitly set each cell value to an array with a 'value' key
        $checkForErrors = $element && $element->hasErrors($this->handle);
        foreach ($value as &$row) {
            foreach ($this->columns as $colId => $col) {
                if (isset($row[$colId])) {
                    $row[$colId] = [
                        'value' => $row[$colId],
                        'hasErrors' => $checkForErrors,
                    ];
                }
            }
        }
        unset($row);

        // Make sure the value contains at least the minimum number of rows
        if ($this->minRows) {
            for ($i = count($value); $i < $this->minRows; $i++) {
                $value[] = [];
            }
        }

        $view = Craft::$app->getView();
        $id = $view->formatInputId($this->handle);

        // Render the input template
        return $view->renderTemplate('annotated-notes/_components/fields/AnnotatedNotesField_input', [
            'id' => $id,
            'name' => $this->handle,
            'cols' => $this->columns,
            'rows' => $value,
            'minRows' => $this->minRows,
            'maxRows' => $this->maxRows,
            'static' => false,
            'addRowLabel' => Craft::t('site', $this->addRowLabel),
        ]);
    }
}
