<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Form;

/**
 *
 * @method Form getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryFormTrait {

    function buildHtmlButtons()
    {
        $output = '';
        foreach ($this->getWidget()->getButtons() as $btn) {
            $output .= $this->getTemplate()->buildHtml($btn);
        }
        
        return $output;
    }

    function buildJsButtons()
    {
        $output = '';
        foreach ($this->getWidget()->getButtons() as $btn) {
            $output .= $this->getTemplate()->buildJs($btn);
        }
        
        return $output;
    }
}
?>