<?php
/**
 * Site navigation menu
 *
 * @uses $vars['menu']['default']
 * @uses $vars['menu']['more']
 */

echo '<ul class="elgg-menu elgg-menu-site clearfix">';
foreach ($vars['menu']['default'] as $menu_item) {
	echo elgg_view('navigation/menu/elements/item', array('item' => $menu_item));
}

if (isset($vars['menu']['more'])) {
	echo '<li class="elgg-more">';

	$more = elgg_echo('more');
	echo "<a title=\"$more\">$more</a>";
	
	echo elgg_view('navigation/menu/elements/group', array(
		'class' => 'elgg-menu', 
		'section' => 'more', 
		'items' => $vars['menu']['more'],
	));
	
	echo '</li>';
}
echo '</ul>';
