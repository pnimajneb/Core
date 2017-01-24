<?php namespace exface\Core\Actions;

/**
 * Adds instances from the input data to the favorites basket of the current user.
 * 
 * This action is similar to ObjectBasketAdd except that it saves things for longer use. Favorites are attached to a 
 * specific user and are the same in all windows/sessions of this user. They get restored once the user logs on.
 * 
 * @author Andrej Kabachnik
 *
 */
class FavoritesAdd extends ObjectBasketAdd {
	
	protected function init(){
		parent::init();
		$this->set_icon_name('star');
	}
	
	/**
	 * In constrast to the generic object basket, favorites are always stored in the user context scope.
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Actions\ObjectBasketAdd::get_scope()
	 */
	public function get_scope(){
		$this->set_scope('User');
		return parent::get_scope();
	}

}
?>