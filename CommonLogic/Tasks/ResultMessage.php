<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultMessage implements ResultInterface
{
    private $task = null;
    
    private $isDataModified = false;
    
    private $isContextModified = false;
    
    private $isUndoable = false;
    
    private $message = null;
    
    private $workbench = null;
    
    private $responseCode = 200;
    
    /**
     * @deprecated Use ResultFactory::createMessageResult() instead
     * 
     * @param TaskInterface $task
     */
    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
        $this->workbench = $task->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::setDataModified()
     */
    public function setDataModified(bool $value): ResultInterface
    {
        $this->isDataModified = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isUndoable()
     */
    public function isUndoable(): bool
    {
        return $this->isUndoable;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::setUndoable()
     */
    public function setUndoable(bool $trueOrFalse): ResultInterface
    {
        $this->isUndoable = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isDataModified()
     */
    public function isDataModified(): bool
    {
        return $this->isDataModified;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getMessage()
     */
    public function getMessage(): string
    {
        // If there is a custom result message text defined, use it instead of the autogenerated message
        if (is_null($this->message)) {
            $message = '';
        } else {
            $message = $this->message;
            $placeholders = StringDataType::findPlaceholders($message);
            if (! empty($placeholders)) {
                $message = '';
                foreach ($this->getResultDataSheet()->getRows() as $row) {
                    $message_line = $this->getResultMessageText();
                    foreach ($placeholders as $ph) {
                        $message_line = str_replace('[#' . $ph . '#]', $row[$ph], $message_line);
                    }
                    $message .= ($message ? "\n" : '') . $message_line;
                }
            }
        }
        
        return $message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getTask()
     */
    public function getTask(): TaskInterface
    {
        return $this->task;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::setMessage()
     */
    public function setMessage(string $string): ResultInterface
    {
        $this->message = $string;
        return $this;
    }
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::setReponseCode()
     */
    public function setReponseCode(int $number) : ResultInterface
    {
        $this->responseCode = $number;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getResponseCode()
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isContextModified()
     */
    public function isContextModified(): bool
    {
        return $this->isContextModified;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::setContextModified()
     */
    public function setContextModified(bool $trueOrFalse): ResultInterface
    {
        $this->isContextModified = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return $this->getMessage() === '' && ! $this->isContextModified() && ! $this->isDataModified();   
    }
}