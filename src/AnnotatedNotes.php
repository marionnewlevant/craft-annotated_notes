<?php
/**
 * Annotated Notes plugin for Craft CMS 3.x
 *
 * Field for multiple notes with automatic annotation
 *
 * @link      http://marion.newlevant.com
 * @copyright Copyright (c) 2019 Marion Newlevant
 */

namespace marionnewlevant\annotatednotes;

use marionnewlevant\annotatednotes\services\AnnotatedNotesService as AnnotatedNotesServiceService;

use marionnewlevant\annotatednotes\fields\AnnotatedNotesField;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\services\Elements;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use craft\elements\Asset;
use craft\events\ElementEvent;

use craft\web\UploadedFile;
use yii\base\Event;

/**
 * Class AnnotatedNotes
 *
 * @author    Marion Newlevant
 * @package   AnnotatedNotes
 * @since     1.0.0
 *
 * @property  AnnotatedNotesServiceService $annotatedNotesService
 *
 */
class AnnotatedNotes extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var AnnotatedNotes
     */
    public static $plugin;

    /**
     * Stores the IDs of elements we already preparsed the fields for.
     *
     * @var array
     */
    public $preparsedElements;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->preparsedElements = [
            'onSave' => [],
        ];

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = AnnotatedNotesField::class;
            }
        );

        // After save element event handler
       Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
           function (ElementEvent $event) {
               /** @var Element $element */
               $element = $event->element;
               $key = $element->id . '__' . $element->siteId;

               if (!\in_array($key, $this->preparsedElements['onSave'], true)) {
                   $this->preparsedElements['onSave'][] = $key;

                   $content = self::$plugin->annotatedNotesService->getAnnotatedContent($element);

                   if (!empty($content)) {
                       $this->resetUploads();

                       if ($element instanceof Asset) {
                           $element->setScenario(Element::SCENARIO_DEFAULT);
                       }

                       $element->setFieldValues($content);
                       $success = Craft::$app->getElements()->saveElement($element, true, false);

                       // if no success, log error
                       if (!$success) {
                           Craft::error('Couldn’t save element with id “' . $element->id . '”', __METHOD__);
                       }
                   }
               }
           }
       );

        Craft::info(
            Craft::t(
                'annotated-notes',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Fix file uploads being processed twice by craft, which causes an error.
     *
     * @see https://github.com/aelvan/Preparse-Field-Craft/issues/23#issuecomment-284682292
     */
    private function resetUploads()
    {
        unset($_FILES);
        UploadedFile::reset();
    }
}
