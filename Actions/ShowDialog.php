<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;

class ShowDialog extends ShowWidget implements iShowDialog {
	private $dialog_widget = null;
	private $widget_was_enhanced = false;
	
	/**
	 * Creates the dialog widget. If not contents is passed, an empty dialog widget will be returned.
	 * 
	 * @return \exface\Core\Widgets\Dialog
	 */
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL){
		/* @var $dialog \exface\Core\Widgets\Dialog */
		$parent_widget = $this->get_called_by_widget();
		$dialog = $this->get_called_on_ui_page()->create_widget('Dialog', $parent_widget);
		$dialog->set_meta_object_id($this->get_meta_object()->get_id());
		
		if ($contained_widget){
			$dialog->add_widget($contained_widget);
		}
		
		return $dialog;
	}
	
	/**
	 * Add some default attributes to a given dialog, that can be derived from the specifics of the action: the dialog caption, icon, etc.
	 * These attributes can thus be ommited, when manually defining a dialog for the action.
	 * @param Dialog $dialog
	 * @return \exface\Core\Widgets\Dialog
	 */
	protected function enhance_dialog_widget(Dialog $dialog){
		$dialog->set_close_button_caption($this->get_app()->get_translator()->translate('ACTION.SHOWDIALOG.CANCEL_BUTTON'));
		
		// If the widget calling the action (typically a button) is known, inherit some of it's attributes
		if ($this->get_called_by_widget()){
			if (!$dialog->get_icon_name()){
				$dialog->set_icon_name($this->get_called_by_widget()->get_icon_name());
			}
			if (!$dialog->get_caption()){
				$dialog->set_caption($this->get_called_by_widget()->get_caption());
			}
		} else {
			if(!$dialog->get_icon_name()){
				$dialog->set_icon_name($this->get_icon_name());
			}
			// TODO get some default action attributes from the meta model once the actions have one
		}
		return $dialog;
	}
	
	/**
	 * The widget shown by ShowDialog is a dialog of course. However, specifying the entire dialog widget for custom dialogs is a lot of work,
	 * so you can also specify just the contents of the dialog in the widget property of the action in UXON. In this case, those widgets 
	 * specified there will be automatically wrapped in a dialog. This makes creating dialog easier and you can also reuse existing widgets,
	 * that are no dialogs (for example an entire page can be easily show in a dialog).
	 * 
	 * @see \exface\Core\Actions\ShowWidget::get_widget()
	 */
	public function get_widget(){
		$widget = parent::get_widget();
		if (!($widget instanceof Dialog)){
			$widget = $this->create_dialog_widget($widget);
			$this->set_widget($widget);
		} 
		
		if (!$this->widget_was_enhanced){
			$widget = $this->enhance_dialog_widget($widget);
			$this->widget_was_enhanced = true;
		}
		return $widget;
	}
	
	public function get_dialog_widget(){
		return $this->get_widget();
	}
	
	/**
	 * The output for action showing dialogs is either the rendered contents of the dialog (if lazy loading is enabled) 
	 * or the rendered dialog itself.
	 * @see \exface\Core\Actions\ShowWidget::get_result_output()
	 */
	public function get_result_output(){
		$dialog = $this->get_result();
		if ($dialog->get_lazy_loading()){
			$dialog_contents = $dialog->get_contents();
			$code = '';
			// If the dialog has multiple widgets, we need to put them in a container here, so we cann draw the whole thing
			// If we draw each widget individually, the respective template elements will get instantiated one after another,
			// so those instatiated first, can't access the ones instantiated later on. Putting everything in a container 
			// makes the template instatiate all elements before actually drawing them!
			if (is_array($dialog_contents)){
				$container = $dialog->get_page()->create_widget('Container', $dialog);
				foreach ($dialog_contents as $w){
					$container->add_widget($w);
				}
				$code = $this->get_app()->get_workbench()->ui()->draw($container);
			} else {
				$code = $this->get_app()->get_workbench()->ui()->draw($dialog_contents);
			}
		} else {
			$code = parent::get_result_output();
		}
		return $code;
	} 
}
?>