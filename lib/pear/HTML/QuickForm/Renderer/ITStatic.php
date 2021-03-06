<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Bertrand Mansion <bmansion@mamasam.com>                      |
// +----------------------------------------------------------------------+
//
// $Id: ITStatic.php,v 1.3 2003/06/20 20:27:47 avb Exp $

require_once('HTML/QuickForm/Renderer.php');

/**
 * A static renderer for HTML_QuickForm compatible 
 * with HTML_Template_IT and HTML_Template_Sigma.
 *
 * As opposed to the dynamic renderer, this renderer needs
 * every elements and labels in the form to be specified by
 * placeholders at the position you want them to be displayed.
 * 
 * @author Bertrand Mansion <bmansion@mamasam.com>
 * @access public
 */
class HTML_QuickForm_Renderer_ITStatic extends HTML_QuickForm_Renderer
{
   /**
    * An HTML_Template_IT or some other API compatible Template instance
    * @var object
    */
    var $_tpl = null;

   /**
    * Rendered form name
    * @var string
    */
    var $_formName = 'form';

   /**
    * The errors that were not shown near concrete fields go here
    * @var array
    */
    var $_errors = array();

   /**
    * Show the block with required note?
    * @var bool
    */
    var $_showRequired = false;

   /**
    * Which group are we currently parsing ?
    * @var string
    */
    var $_inGroup;

   /**
    * Index of the element in its group
    * @var int
    */
    var $_elementIndex = 0;

   /**
    * If elements have been added with the same name
    * @var array
    */
    var $_duplicateElements = array();

   /**
    * How to handle the required tag for required fields
    * @var string
    */
    var $_required = '{label}<font size="1" color="red">*</font>';

   /**
    * How to handle error messages in form validation
    * @var string
    */
    var $_error = '<font color="red">{error}</font><br />{html}';

   /**
    * Constructor
    *
    * @param object     An HTML_Template_IT or other compatible Template object to use
    */
    function HTML_QuickForm_Renderer_ITStatic(&$tpl)
    {
        $this->HTML_QuickForm_Renderer();
        $this->_tpl =& $tpl;
    } // end constructor

   /**
    * Called when visiting a form, before processing any form elements
    *
    * @param    object      An HTML_QuickForm object being visited
    * @access   public
    * @return   void
    */
    function startForm(&$form)
    {
        $this->_formName = $form->getAttribute('name');

        if (count($form->_duplicateIndex) > 0) {
            // Take care of duplicate elements
            foreach ($form->_duplicateIndex as $elementName => $indexes) {
                $this->_duplicateElements[$elementName] = 0;
            }
        }
    } // end func startForm

   /**
    * Called when visiting a form, after processing all form elements
    * 
    * @param    object     An HTML_QuickForm object being visited
    * @access   public
    * @return   void
    */
    function finishForm(&$form)
    {
        // display errors above form
        if (!empty($this->_errors) && $this->_tpl->blockExists($this->_formName.'_error_loop')) {
            foreach ($this->_errors as $error) {
                $this->_tpl->setVariable($this->_formName.'_error', $error);
                $this->_tpl->parse($this->_formName.'_error_loop');
            }
        }
        // show required note
        if ($this->_showRequired) {
            $this->_tpl->setVariable($this->_formName.'_required_note', $form->getRequiredNote());
        }
        // assign form attributes
        $this->_tpl->setVariable($this->_formName.'_attributes', $form->getAttributes(true));
        // assign javascript validation rules
        $this->_tpl->setVariable($this->_formName.'_javascript', $form->getValidationScript());
    } // end func finishForm

   /**
    * Called when visiting a header element
    *
    * @param    object     An HTML_QuickForm_header element being visited
    * @access   public
    * @return   void
    */
    function renderHeader(&$header)
    {
        $name = $header->getName();
        $varName = $this->_formName.'_header';

        // Find placeHolder
        if (!empty($name) && $this->_tpl->placeHolderExists($this->_formName.'_header_'.$name)) {
            $varName = $this->_formName.'_header_'.$name;
        }
        $this->_tpl->setVariable($varName, $header->toHtml());
    } // end func renderHeader

   /**
    * Called when visiting an element
    *
    * @param    object     An HTML_QuickForm_element object being visited
    * @param    bool       Whether an element is required
    * @param    string     An error message associated with an element
    * @access   public
    * @return   void
    */
    function renderElement(&$element, $required, $error)
    {
        $name = $element->getName();

        // are we inside a group?
        if (!empty($this->_inGroup)) {
            $varName = $this->_formName.'_'.str_replace(array('[', ']'), '_', $name);
            if (substr($varName, -2) == '__') {
                // element name is of type : group[]
                $varName = $this->_inGroup.'_'.$this->_elementIndex.'_';
                $this->_elementIndex++;
            }
            if ($varName != $this->_inGroup) {
                // element name is of type : group[name]
                $label = $element->getLabel();
                $html = $element->toHtml();

                if ($required && !$element->isFrozen()) {
                    $this->_renderRequired($label, $html);
                    $this->_showRequired = true;
                }
                if (!empty($label)) {
                    if (is_array($label)) {
                        foreach ($label as $key => $value) {
                            $this->_tpl->setVariable($varName.'label_'.$key, $value);
                        }
                    } else {
                        $this->_tpl->setVariable($varName.'label', $label);
                    }
                }
                $this->_tpl->setVariable($varName.'html', $html);
            }

        } else {

            $name = str_replace(array('[', ']'), array('_', ''), $name);

            if (isset($this->_duplicateElements[$name])) {
                // Element is a duplicate
                $varName = $this->_formName.'_'.$name.'_'.$this->_duplicateElements[$name];
                $this->_duplicateElements[$name]++;
            } else {
                $varName = $this->_formName.'_'.$name;
            }

            $label = $element->getLabel();
            $html = $element->toHtml();

            if ($required) {
                $this->_showRequired = true;
                $this->_renderRequired($label, $html);
            }
            if (!empty($error)) {
                $this->_renderError($label, $html, $error);
            }
            if (is_array($label)) {
                foreach ($label as $key => $value) {
                    $this->_tpl->setVariable($varName.'_label_'.$key, $value);
                }
            } else {
                $this->_tpl->setVariable($varName.'_label', $label);
            }
            $this->_tpl->setVariable($varName.'_html', $html);
        }
    } // end func renderElement

   /**
    * Called when visiting a hidden element
    * 
    * @param    object     An HTML_QuickForm_hidden object being visited
    * @access   public
    * @return   void
    */
    function renderHidden(&$element)
    {
        $this->_tpl->setVariable($this->_formName.'_'.$element->getName().'_html', $element->toHtml());
    } // end func renderHidden

   /**
    * Called when visiting a group, before processing any group elements
    *
    * @param    object     An HTML_QuickForm_group object being visited
    * @param    bool       Whether a group is required
    * @param    string     An error message associated with a group
    * @access   public
    * @return   void
    */
    function startGroup(&$group, $required, $error)
    {
        $name = $group->getName();
        $varName = $this->_formName.'_'.$name;

        $this->_elementIndex = 0;

        $html = $this->_tpl->placeholderExists($varName.'_html') ? $group->toHtml() : '';
        $label = $group->getLabel();

        if ($required) {
            $this->_renderRequired($label, $html);
        }
        if (!empty($error)) {
            $this->_renderError($label, $html, $error);
        }
        if (!empty($html)) {
            $this->_tpl->setVariable($varName.'_html', $html);
        } else {
            // Uses error blocks to set the special groups layout error
            // <!-- BEGIN form_group_error -->{form_group_error}<!-- END form_group_error -->
            if (!empty($error)) {
                if ($this->_tpl->placeholderExists($varName.'_error') &&
                   (strpos($this->_error, '{html}') !== false || strpos($this->_error, '{label}') !== false)) {
                    $error = str_replace('{error}', $error, $this->_error);
                    $this->_tpl->setVariable($varName.'_error', $error);
                    array_pop($this->_errors);
                }
            }
        }
        if (is_array($label)) {
            foreach ($label as $key => $value) {
                $this->_tpl->setVariable($varName.'_label_'.$key, $value);
            }
        } else {
            $this->_tpl->setVariable($varName.'_label', $label);
        }
        $this->_inGroup = $varName;
    } // end func startGroup

   /**
    * Called when visiting a group, after processing all group elements
    *
    * @param    object     An HTML_QuickForm_group object being visited
    * @access   public
    * @return   void
    */
    function finishGroup(&$group)
    {
        $this->_inGroup = '';
    } // end func finishGroup

   /**
    * Sets the way required elements are rendered
    *
    * You can use {label} or {html} placeholders to let the renderer know where
    * where the element label or the element html are positionned according to the
    * required tag. They will be replaced accordingly with the right value.
    * For example:
    * <font color="red">*</font>{label}
    * will put a red star in front of the label if the element is required.
    *
    * @param    string      The required element template
    * @access   public
    * @return   void
    */
    function setRequiredTemplate($template)
    {
        $this->_required = $template;
    } // end func setRequiredTemplate

   /**
    * Sets the way elements with validation errors are rendered
    *
    * You can use {label} or {html} placeholders to let the renderer know where
    * where the element label or the element html are positionned according to the
    * error message. They will be replaced accordingly with the right value.
    * The error message will replace the {error} place holder.
    * For example:
    * <font color="red">{error}</font><br />{html}
    * will put the error message in red on top of the element html.
    *
    * If you want all error messages to be output in the main error block, do not specify
    * {html} nor {label}.
    *
    * Groups can have special layouts. With this kind of groups, the renderer will need
    * to know where to place the error message. In this case, use error blocks like:
    * <!-- BEGIN form_group_error -->{form_group_error}<!-- END form_group_error -->
    * where you want the error message to appear in the form.
    *
    * @param    string      The element error template
    * @access   public
    * @return   void
    */
    function setErrorTemplate($template)
    {
        $this->_error = $template;
    } // end func setErrorTemplate

   /**
    * Called when an element is required
    *
    * This method will add the required tag to the element label and/or the element html
    * such as defined with the method setRequiredTemplate
    *
    * @param    string      The element label
    * @param    string      The element html rendering
    * @see      setRequiredTemplate()
    * @access   private
    * @return   void
    */
    function _renderRequired(&$label, &$html)
    {
        if (!empty($label) && strpos($this->_required, '{label}') !== false) {
            if (is_array($label)) {
                $label[0] = str_replace('{label}', $label[0], $this->_required);
            } else {
                $label = str_replace('{label}', $label, $this->_required);
            }
        }
        if (!empty($html) && strpos($this->_required, '{html}') !== false) {
            $html = str_replace('{html}', $html, $this->_required);
        }
    } // end func _renderRequired

   /**
    * Called when an element has a validation error
    *
    * This method will add the error message to the element label or the element html
    * such as defined with the method setErrorTemplate. If the error placeholder is not found
    * in the template, the error will be displayed in the form error block.
    *
    * @param    string      The element label
    * @param    string      The element html rendering
    * @param    string      The element error
    * @see      setErrorTemplate()
    * @access   private
    * @return   void
    */
    function _renderError(&$label, &$html, $error)
    {
        if (!empty($label) && strpos($this->_error, '{label}') !== false) {
            if (is_array($label)) {
                $label[0] = str_replace(array('{label}', '{error}'), array($label[0], $error), $this->_error);
            } else {
                $label = str_replace(array('{label}', '{error}'), array($label, $error), $this->_error);
            }
        } elseif (!empty($html) && strpos($this->_error, '{html}') !== false) {
            $html = str_replace(array('{html}', '{error}'), array($html, $error), $this->_error);
        } else {
            $this->_errors[] = $error;
        }
    }// end func _renderError
} // end class HTML_QuickForm_Renderer_ITStatic
?>