<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Executes the not operator on the value and returns the result.
 */
class IsNull extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($value = null)
    {
        if ($value == null || $value == '') {
            return true;
        }
        return false;
    }
}