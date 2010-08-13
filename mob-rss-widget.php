<?php
/*
Plugin Name: White Whale RSS Widget
Plugin URI: http://wordpress.org/extend/plugins/moby-rss-widget/
Description: A replacement for the built in Wordpress RSS widget that doesn't use Wordpress functions
Author: Mark Brawn
Version: 1.0.0
Author URI: http://www.thewhitewhale.co.uk/
Thanks: Thanks to http://justcoded.com/article/wordpress-multi-widgets/ for his excellently concise tutorial on multi-widgets
*/

define('MOB_RSS_WIDGET_DATE_FORMAT_LONG','jS M, Y');
define('MOB_RSS_WIDGET_DATE_FORMAT_SHORT','jS M');
define('MOB_RSS_WIDGET_PREFIX','mob_rss_widget');
define('MOB_RSS_WIDGET_TITLE','MOBy RSS Widget');
define('MOB_RSS_WIDGET_DESCRIPTION','Entries from any RSS or Atom feed');
define('MOB_RSS_WIDGET_ITEM_TITLE_MAXLENGTH',50);
define('MOB_RSS_WIDGET_ITEM_DESCRIPTION_MAXLENGTH',100);

add_action('init', 'mob_rss_widget_register');

/**
 * Register the widget(s)
 *
 */
function mob_rss_widget_register() {
 
	$prefix = MOB_RSS_WIDGET_PREFIX; // $id prefix
	$name = __(MOB_RSS_WIDGET_TITLE);
	$widget_ops = array('classname' => MOB_RSS_WIDGET_PREFIX, 'description' => __(MOB_RSS_WIDGET_DESCRIPTION));
	$control_ops = array('width' => 200, 'height' => 200, 'id_base' => $prefix);
 
	$options = get_option(MOB_RSS_WIDGET_PREFIX);
	if(isset($options[0])) unset($options[0]);
 
	if(!empty($options))
	{
		foreach(array_keys($options) as $widget_number)
		{
			wp_register_sidebar_widget($prefix.'-'.$widget_number, $name, 'mob_rss_widget', $widget_ops, array( 'number' => $widget_number ));
			wp_register_widget_control($prefix.'-'.$widget_number, $name, 'mob_rss_widget_control', $control_ops, array( 'number' => $widget_number ));
		}
	} 
	else
	{
		$options = array();
		$widget_number = 1;
		wp_register_sidebar_widget($prefix.'-'.$widget_number, $name, 'mob_rss_widget', $widget_ops, array( 'number' => $widget_number ));
		wp_register_widget_control($prefix.'-'.$widget_number, $name, 'mob_rss_widget_control', $control_ops, array( 'number' => $widget_number ));
	}
}
/**
 * Widget "main"
 *
 * @param mixed $args
 * @param mixed $vars
 */
function mob_rss_widget($args, $vars = array()) {
	extract($args);
	// get widget saved options
	$prefix = MOB_RSS_WIDGET_PREFIX; // $id prefix
	$widget_number = (int)str_replace($prefix.'-', '', @$widget_id);
	$options = get_option(MOB_RSS_WIDGET_PREFIX);
	if(!empty($options[$widget_number])){
		$vars = $options[$widget_number];
	}
	// widget open tags
	echo $before_widget;
 
	// print title from admin 
	if(!empty($vars['title']))
	{
		echo $before_title;
		if($vars['show-rss-icon'])
		{
			echo '<img src="'.plugin_dir_url(__FILE__).'/rss.png" style="float:right;margin-right:10px;"/> ';
		}
		echo $vars['title'];
		echo $after_title;
	} 
	// print content and widget end tags
	mob_rss_widget_get_feed($vars['url'],$vars['items-to-show'],$vars['show-descriptions'],$vars['show-dates']);
	echo $after_widget;
}
/**
 * Control panel for the widget
 *
 * @param mixed $args
 */
function mob_rss_widget_control($args) {
 
	$prefix = MOB_RSS_WIDGET_PREFIX; // $id prefix
	$options = get_option(MOB_RSS_WIDGET_PREFIX);
	if(empty($options)) $options = array();
	if(isset($options[0])) unset($options[0]);
 
	// update options array
	if(!empty($_POST[$prefix]) && is_array($_POST))
	{
		foreach($_POST[$prefix] as $widget_number => $values)
		{
			if(empty($values) && isset($options[$widget_number])) // user clicked cancel
			{
				continue;
			}
			if(!isset($options[$widget_number]) && $args['number'] == -1)
			{
				$args['number'] = $widget_number;
				$options['last_number'] = $widget_number;
			}
			$options[$widget_number] = $values;
		}
 
		// update number
		if($args['number'] == -1 && !empty($options['last_number'])){
			$args['number'] = $options['last_number'];
		}
 
		// clear unused options and update options in DB. return actual options array
		$options = bf_smart_multiwidget_update($prefix, $options, $_POST[$prefix], $_POST['sidebar'], 'mob_rss_widget');
	}
 
	// $number - is dynamic number for multi widget, gived by WP by default $number = -1 (if no widgets activated). In this case we should use %i% for inputs to allow WP generate number automatically
	$number = ($args['number'] == -1)? '%i%' : $args['number'];
 
	// now we can output control
	$opts = @$options[$number];
	
	// Standard input box fields
	$fields = array('title','url');
	foreach($fields as $f)
	{
		$$f = @$opts[$f];
		echo '<p><label for="'.$prefix.'-'.$number.'-'.$f.'">'.__(ucwords($f)).':</label><input class="widefat" id="'.$prefix.'-'.$number.'-'.$f.'" type="text" name="'.$prefix.'['.$number.']['.$f.']" value="'.$$f.'" /></p>';
	}	
	
	// Checkbox fields
	echo '<p>';
	$fields = array('show-descriptions','show-dates','show-rss-icon');
	$labels = array('Show Descriptions','Show Dates','Show RSS Icon');
	foreach($fields as $k=>$f)
	{
		$$f = @$opts[$f];
		echo '<input type="checkbox" id="'.$prefix.'-'.$number.'-'.$f.'" name="'.$prefix.'['.$number.']['.$f.']"'.($$f?' checked="true"':'').'" /> <label for="'.$prefix.'-'.$number.'-'.$f.'">'.__($labels[$k]).'</label><br/>';
	}
	echo '</p>';
	
	// Other fields
	$f = 'items-to-show';
	$$f = @$opts[$f];
	echo '<p><label for="'.$prefix.'-'.$number.'-'.$f.'">'.__(ucwords(str_replace('-',' ',$f))).':</label>';
	echo '<select id="'.$prefix.'-'.$number.'-'.$f.'" name="'.$prefix.'['.$number.']['.$f.']">';
	for($i=1;$i<=10;$i++)
	{
		echo '<option value="'.$i.'"'.($$f==$i?' selected="selected"':'').'>'.$i.'</option>';
	}
	echo '</select></p>';
}
/**
 * Load and output a feed
 *
 * @param string $url
 * @param array $opts
 */
function mob_rss_widget_get_feed($url,$opts)
{
	
	if($xml = @DOMDocument::load($url))
	{	
		$items = @$xml->getElementsByTagName('item');
		if(empty($items))
		{
			$items = @$xml->getElementsByTagName('entry');
		}
		if(!empty($items))
		{
			$i = 1;
			echo '<ul>';
			foreach($items as $item)
			{			
				$title = @$item->getElementsByTagName('title')->item(0)->nodeValue;
				// Truncate if nessessary
				$title = strlen($title)>=MOB_RSS_WIDGET_ITEM_TITLE_MAXLENGTH ? '<span title="'.$title.'">'.substr($title,0,MOB_RSS_WIDGET_ITEM_TITLE_MAXLENGTH-3).'...</span>' : $title;
	
				$url = @$item->getElementsByTagName('link')->item(0)->nodeValue;
				if(@$opts['show-descriptions'])
				{
					$description = @$item->getElementsByTagName('description')->item(0)->nodeValue;
					// Truncate if nessessary
					$description = '<br/><small title="'.$description.'">'.(strlen($description)>=MOB_RSS_WIDGET_ITEM_DESCRIPTION_MAXLENGTH ? substr($description,0,MOB_RSS_WIDGET_ITEM_DESCRIPTION_MAXLENGTH-3).'...' : $description).'</small>';
				}
				if(@$opts['show-dates'])
				{
					$dateMade = strtotime(@$item->getElementsByTagName('pubDate')->item(0)->nodeValue);
					$format = date('Y',$dateMade)==date('Y') ? MOB_RSS_WIDGET_DATE_FORMAT_SHORT : MOB_RSS_WIDGET_DATE_FORMAT_LONG;
					$date = '<small style="float:right">'.date($format,$dateMade).'</small>';
				}
				echo '<li>'.@$date.'<a href="'.$url.'">'.$title.'</a>'.@$description.'</li>';
				
				if(++$i > @$opts['items-to-show']) break;
			
			}
			echo '</ul>';
		}
		else 
		{
			echo '<p>-</p>';
		}
	}
	//echo $xml->saveXML();
}
if(!function_exists('bf_smart_multiwidget_update'))
{
	function bf_smart_multiwidget_update($id_prefix, $options, $post, $sidebar, $option_name = '')
	{
		global $wp_registered_widgets;
		static $updated = false;
 
		// get active sidebar
		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();
 
		// search unused options
		foreach ( $this_sidebar as $_widget_id ) 
		{
			if(preg_match('/'.$id_prefix.'-([0-9]+)/i', $_widget_id, $match))
			{
				$widget_number = $match[1];
 
				// $_POST['widget-id'] contain current widgets set for current sidebar
				// $this_sidebar is not updated yet, so we can determine which was deleted
				if(!in_array($match[0], $_POST['widget-id']))
				{
					unset($options[$widget_number]);
				}
			}
		}
 
		// update database
		if(!empty($option_name))
		{
			update_option($option_name, $options);
			$updated = true;
		}
 
		// return updated array
		return $options;
	}
}
?>
