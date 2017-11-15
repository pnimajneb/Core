<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;

/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading.
 * It also can optionally accept new values.
 *
 * @see InputCombo
 *
 * @author Andrej Kabachnik
 */
class InputCombo extends InputSelect implements iSupportLazyLoading
{

    private $lazy_loading = true;

    // Combos should use lazy autosuggest in general
    private $lazy_loading_action = 'exface.Core.Autosuggest';

    // FIXME move default value to template config option WIDGET.INPUTCOMBO.MAX_SUGGESTION like PAGE_SIZE of tables
    private $max_suggestions = 20;

    private $allow_new_values = true;

    private $autoselect_single_suggestion = true;

    private $lazy_loading_group_id = null;

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     * By default lazy loading is used to fetch autosuggest values.
     * Set to FALSE to preload the values.
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = $value;
    }

    /**
     * Returns the alias of the action to be called by the lazy autosuggest.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     * Defines the alias of the action to be called by the autosuggest.
     * Default: exface.Core.Autosuggest.
     *
     * @uxon-property lazy_loading_action
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    public function getAllowNewValues()
    {
        return $this->allow_new_values;
    }

    /**
     * By default the InputCombo will also accept values not present in the autosuggest.
     * Set to FALSE to prevent this
     *
     * @uxon-property allow new values
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAllowNewValues($value)
    {
        $this->allow_new_values = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    public function getMaxSuggestions()
    {
        return $this->max_suggestions;
    }

    /**
     * Limits the number of suggestions loaded for every autosuggest.
     *
     * @uxon-property max_suggestions
     * @uxon-type number
     *
     * @param integer $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setMaxSuggestions($value)
    {
        $this->max_suggestions = intval($value);
        return $this;
    }

    public function getAutoselectSingleSuggestion()
    {
        return $this->autoselect_single_suggestion;
    }

    /**
     * Set to FALSE to disable automatic selection of the suggested value if only one suggestion found.
     *
     * @uxon-property autoselect_single_suggestion
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAutoselectSingleSuggestion($value)
    {
        $this->autoselect_single_suggestion = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    public function getLazyLoadingGroupId()
    {
        return $this->lazy_loading_group_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
}
?>