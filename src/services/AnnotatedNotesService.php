<?php
/**
 */

namespace marionnewlevant\annotatednotes\services;

use marionnewlevant\annotatednotes\fields\AnnotatedNotesField;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\web\View;

/**
 * AnnotatedNotes Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Marion Newlevant
 * @package   AnnotatedNotes
 * @since     1.0.0
 */
class AnnotatedNotesService extends Component
{
    /**
     * Loops over fields in element to determine if they have preparse fields.
     *
     * @param Element $element
     *
     * @return array
     */
    public function getAnnotatedContent(Element $element)
    {
        $content = [];
        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout) {
            foreach ($fieldLayout->getFields() as $field) {
                if ($field && \get_class($field) === 'marionnewlevant\annotatednotes\fields\AnnotatedNotesField')
                {
                    /** @var AnnotatedNotesField $field */

                    $annotation = $this->calculateAnnotation($field, $element);

                    if ($annotation !== null && $element[$field->handle]) {
                        $content[$field->handle] = $this->annotateElement($element[$field->handle], $annotation);
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Determines annotation for a given element.
     *
     * @param AnnotatedNotesField $field
     * @param Element           $element
     *
     * @return null|string
     */
    public function calculateAnnotation(AnnotatedNotesField $field, Element $element)
    {
        $annotationTwig = $field->annotationTwig;
        $fieldValue = null;

        $elementTemplateName = 'element';

        if (method_exists($element, 'refHandle')) {
            $elementTemplateName = strtolower($element->refHandle());
        }

        // Enable generateTransformsBeforePageLoad always
        $generateTransformsBeforePageLoad = Craft::$app->config->general->generateTransformsBeforePageLoad;
        Craft::$app->config->general->generateTransformsBeforePageLoad = true;

        // save cp template path and set to site templates
        $oldMode = Craft::$app->view->getTemplateMode();
        Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        // render value from the field template
        try {
            $vars = array_merge(['element' => $element], [$elementTemplateName => $element]);
            $fieldValue = Craft::$app->view->renderString($annotationTwig, $vars);
        } catch (\Exception $e) {
            Craft::error('Couldn’t render value for element with id “'.$element->id.'” and preparse field “'.
                $field->handle.'” ('.$e->getMessage().').', __METHOD__);
        }

        // restore cp template paths
        Craft::$app->view->setTemplateMode($oldMode);

        // set generateTransformsBeforePageLoad back to whatever it was
        Craft::$app->config->general->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

        if (null === $fieldValue) {
            return null;
        }


        return $fieldValue;
    }

    /**
     * @param array  $fieldValue
     * @param String $annotation
     *
     * @return array
     */
    public function annotateElement(array $fieldValue, String $annotation)
    {
        // here is where we set the annotation for
        // any as yet un-annotated rows.
        $annotatedFieldValue = [];
        foreach ($fieldValue as $note) {
            if ($note['col2'] === '' && $note['col1'] !== '') {
                $note['col2'] = $annotation;
                $note['annotation'] = $annotation;
            }
            $annotatedFieldValue[] = $note;
        }
        return $annotatedFieldValue;
    }
}
