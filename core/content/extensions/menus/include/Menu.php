<?php
namespace uCMS\Core\Extensions\Menus;
use uCMS\Core\ORM\Model;
use uCMS\Core\Extensions\Users\User;

class Menu extends Model{

	public function init(){
		$this->tableName('menus');
		$this->primaryKey('menu');
		$this->hasMany('\\uCMS\\Core\\Extensions\\Menus\\MenuLink', ['bind' => 'links',
			'key' => 'menu',
			'conditions' => ['orders' => ['lid' => 'ASC']]
		]);
	}

	public function render($row, $class = ""){
		if( !empty($class) ) $class = ' class ="'.$this->prepare($class).'"';
		echo "<ul$class>";
		// TODO: Tree structure
		$links = is_array($row->links) ? $row->links : [];
		$currentUser = User::Current();
		foreach ($links as $link) {
			$selected = $link->isCurrentPage() ? ' class="selected"' : '';
			if( $currentUser->can($link->permission) ){
				echo '<li><a '.$selected.' href="'.$link->getLink().'" title="'.$this->tr($link->title).'">'.$this->tr($link->title).'</a></li>';
			}
		}
		echo "</ul>";
	}
}
?>