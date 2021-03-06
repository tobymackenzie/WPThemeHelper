<?php
/*
Class: SettingHelper
Manager for WordPress settings
*/
namespace TJM\WPThemeHelper;

class SettingHelper{
	/*
	Attribute: actions
	Actions and their methods and settings to hook into for applying settings.  Anything not defined here is run at 'after_setup_theme', so we don't have to maintain a list of anything but the ones that should be run later.
	*/
	protected $actions = Array(
		'widgets_init'=> Array(
			'widget-areas'
			,'widgets'
		)
	);

	/*
	Attribute: settingsWithDefinedActions
	Used internally to store which settings are to be applied for a special action.
	*/
	protected $settingsWithDefinedActions;

	/*
	Attribute: settings
	Settings to set
	*/
	protected $settings;

	/*
	Attribute: appliedSettings
	Array of setting names that have been applied.  `apply()` moves items from $unappliedSettings to this array.
	*/
	protected $appliedSettings;

	/*
	Attribute: unappliedSettings
	Array of setting names that have not yet been applied.  `set()` adds items to this array
	*/
	protected $unappliedSettings;

	/*
	Method: getBaseDefaults
	Get the default settings.
	Parameters:
		settings(Array|callable): settings to override defaults with.  If a function, will call the function, its return being an array of settings to override the defaults with.
	Return: (Array) settings
	*/
	static public function getBaseDefaults(){
		return Array(
			'automatic-feed-links'=> true
			,'content-width'=> 625
			,'custom-background'=> Array(
				'background-color'=> 'ffffff'
			)
			,'custom-header'=> Array(
				'default-image'=> ''
				,'default-text-color'=> '000000'
				,'flex-height'=> true
				,'flex-width'=> true
				,'height'=> 250
				,'max-width'=> 2000
				,'random-default'=> false
				,'width'=> 960
			)
			,'editor-style'=> false
			,'html5'=> array(
				'comment-list'
				,'comment-form'
				,'search-form'
			)
			,'i18n'=> array(
				'dir'=> 'languages'
				,'domain'=> 'tjmbase'
			)
			// ,'image-size'=> array(
			// 	'category-thumb'=> Array(
			// 		300
			// 		,9999
			// 	)
			// 	,'small'=> Array(
			// 		100
			// 		,9999
			// 	)
			// )
			,'nav-menus'=> Array(
				'footer'=> __('Footer', 'tjmbase')
				,'header'=> __('Header', 'tjmbase')
			)
			,'post-formats'=> false
			,'post-thumbnails'=> true
			,'post-thumbnail-size'=> Array(625, 9999)
			,'title-tag'=> true
			,'widget-areas'=> Array(
				Array(
					'name'=> __('Aside 1', 'tjmbase')
					,'id'=> 'aside-1'
					,'before_widget'=> '<div id="%1$s" class="widget %2$s">'
					,'after_widget'=> '</div>'
					,'before_title'=> '<h3 class="widget-title">'
					,'after_title'=> '</h3>'
				)
				,Array(
					'name'=> __('Aside 2', 'tjmbase')
					,'id'=> 'aside-2'
					,'before_widget'=> '<div id="%1$s" class="widget %2$s">'
					,'after_widget'=> '</div>'
					,'before_title'=> '<h3 class="widget-title">'
					,'after_title'=> '</h3>'
				)
				,Array(
					'name'=> __('Header Widgets', 'tjmbase')
					,'id'=> 'header-widget-area'
					,'before_widget'=> '<div id="%1$s" class="widget %2$s">'
					,'after_widget'=> '</div>'
					,'before_title'=> '<h3 class="widget-title">'
					,'after_title'=> '</h3>'
				)
				,Array(
					'name'=> __('Footer Widgets', 'tjmbase')
					,'id'=> 'footer-widget-area'
					,'before_widget'=> '<div id="%1$s" class="widget %2$s">'
					,'after_widget'=> '</div>'
					,'before_title'=> '<h3 class="widget-title">'
					,'after_title'=> '</h3>'
				)
			)
			// ,'widgets'=> Array(
			// 	'WidgetOne'
			// 	,'WidgetTwo'
			// )
		);
	}

	/*
	Method: buildSettingsArray()
	Each argument is a settings collection or a callable that is modifies the currently built setting collection.  Loops through all arguments and merges them into a single settings array.
	Paremeters:
		args(mixed):
			(Array): Array of settings
			(callable): modifies settings array built to that point.
			(String): path to json file containing settings collection.  Will use `file_get_contents` and convert it to an associative array.
	*/
	static public function buildSettingsArray(){
		$args = func_get_args();
		$settings = Array();
		foreach($args as $i=> $arg){
			if(is_array($arg)){
				$settings = array_merge($settings, $arg);
			}elseif(is_string($arg)){
				$file = (substr($arg, 0, 1) === '/')
					? $arg
					: PathHelper::getThemeFilePath($arg)
				;
				if(file_exists($file)){
					$decodedSettings = json_decode(file_get_contents($file), true);
					$settings = array_merge($settings, $decodedSettings);
				}
			}elseif(is_callable($arg)){
				call_user_func_array($arg, Array(&$settings, $args));
			}
		}
		return $settings;
	}

	/*
	Method: getDefaults
	Get the default settings.  Simply runs `getBaseDefaults()`, but passing in $this as the second parameter.
	Parameters:
		settings(Array): settings to override defaults with
	Return: (Array) settings
	*/
	public function getDefaults($settings = Array()){
		$defaults = self::buildSettingsArray(self::getBaseDefaults(), $settings);
		return $defaults;
	}

	/*
	Method: Constructor
	Parameters:
		opts(Array):
			overrideDefaults(boolean): whether or not passed in settings should override defaults.  True by default
			settings(Array): Array of settings use.  Will override defaults unless 'overrideDefaults' is false
	*/
	public function __construct($opts = Array()){
		if(isset($opts['settings']) && $opts['settings']){
			if(is_array($opts['settings']) && isset($opts['overrideDefaults']) && !$opts['overrideDefaults']){
				$this->set($opts['settings']);
			}else{
				$this->set($this->getDefaults($opts['settings']));
			}
		}else{
			$this->set($this->getDefaults());
		}

		//--determine which settings have actions defined, for use by `applySettingsForDefaultAction()`
		foreach($this->actions as $actionSettings){
			foreach($actionSettings as $setting){
				$this->settingsWithDefinedActions[] = $setting;
			}
		}

		//--push all non-deferred settings into setting

		//--add actions to apply settings
		$_this = $this; //-# for use in closures
		//---add_action for each defined action
		foreach($this->actions as $action=> $settings){
			add_action($action, function() use ($_this, $action){
				$_this->applySettingsForAction($action);
			});
		}
		//---apply settings for default action
		add_action('after_setup_theme', function() use ($_this){
			$_this->applySettingsForDefaultAction();
		});
	}

	/*
	Method: applySettingsForAction
	Apply all settings that are supposed to be applied for a given action
	*/
	public function applySettingsForAction($action){
		if(isset($this->actions[$action])){
			$settings = $this->actions[$action];
			foreach($settings as $setting){
				$this->apply($setting);
			}
		}
		return $this;
	}

	/*
	Method: applySettingsForDefaultAction
	Apply all settings that aren't marked for being run at a special action.  These are run during the 'after_setup_theme' action.
	*/
	public function applySettingsForDefaultAction(){
		foreach($this->unappliedSettings as $setting){
			if(!in_array($setting, $this->settingsWithDefinedActions)){
				$this->apply($setting);
			}
		}
	}

	/*
	Method: applyUnappliedSettings
	Apply all settings remaining in $this->unappliedSettings
	*/
	public function applyUnappliedSettings(){
		foreach($this->unappliedSettings as $setting){
			$this->apply($setting);
		}
		return $this;
	}

	/*
	Method: apply
	Apply a WordPress setting, abstracting the settings from the functions that need to be called.
	*/
	public function apply($name, $setting = 'undefined'){
		global $content_width, $wp_version;
		if($setting === 'undefined'){
			$setting = $this->get($name);
		}else{
			$this->set($name, $setting);
		}

		if($setting !== null){
			switch($name){
				case 'automatic-feed-links':
					if($setting){
						//--!BCBREAK for WP < 3.0, uncomment for support
						// if(version_compare($wp_version, '3.0', '>=')){
							add_theme_support('automatic-feed-links');
						// }else{
						// 	automatic_feed_links();
						// }
					}
				break;
				case 'content-width':
					if(!isset($content_width)){
						$content_width = $setting;
					}
				break;
				case 'custom-background':
					//--!future BCBREAK for WP < 3.4
					if(version_compare($wp_version, '3.4', '>=')){
						add_theme_support('custom-background', $setting);
					}elseif($setting){
						add_custom_background();
					}
				break;
				case 'custom-header':
					//--!future BCBREAK for WP < 3.4
					if(version_compare($wp_version, '3.4', '>=') && is_array($setting)){
						add_theme_support('custom-header', $setting);
					}elseif($setting){
						add_custom_image_header();
					}
				break;
				case 'editor-style':
					if($setting){
						add_editor_style();
					}
				break;
				case 'i18n':
				case 'text-domain': //-# for BC
					if(is_string($setting)){
						$domain = $setting;
					}else{
						$domain = (isset($setting['domain']))
							? $setting['domain']
							: 'tjmbase'
						;
						if(isset($setting['dir'])){
							$dir = $setting['dir'];
						}
					}
					if(!isset($dir)){
						$dir = 'languages';
					}
					if(substr($dir, 0, 1) !== DIRECTORY_SEPARATOR){
						$dir = PathHelper::getThemeFilePath($dir);
					}
					if($domain && $dir){
						load_theme_textdomain($domain, $dir);
					}
				break;
				case 'image-size':
					if(is_array($setting)){
						//-# dirty test to see if it is an associative array
						if(!isset($setting[0])){
							foreach($setting as $sizeName=> $arguments){
								array_unshift($arguments, $sizeName);
								call_user_func_array('add_image_size', $arguments);
							}
						}else{
							call_user_func_array('add_image_size', $setting);
						}
					}
				break;
				case 'nav-menus':
					if(is_array($setting)){
						register_nav_menus($setting);
					}
				break;
				case 'post-formats':
					if(is_array($setting)){
						add_theme_support($name, $setting);
					}
				break;
				case 'post-thumbnails':
					if($setting){
						add_theme_support('post-thumbnails');
					}
				break;
				case 'post-thumbnail-size':
					if(is_array($setting)){
						call_user_func_array('set_post_thumbnail_size', $setting);
					}
				break;
				case 'title-tag':
					if($setting){
						add_theme_support('post-thumbnails');
					}
				break;
				case 'widget-areas':
					if(is_array($setting)){
						foreach($setting as $sidebar){
							register_sidebar($sidebar);
						}
					}
				break;
				case 'widgets':
					if(is_array($setting)){
						foreach($setting as $widget){
							register_widget($widget);
						}
					}else{
						register_widget($setting);
					}
				break;
				default:
					add_theme_support($name, $setting);
				break;
			}

			//--move from unapplied to applied
			$this->appliedSettings[] = $name;
			$unappliedIndex = array_search($name, $this->unappliedSettings);
			if($unappliedIndex !== false){
				array_splice($this->unappliedSettings, $unappliedIndex, 1);
			}
		}
		return $this;
	}

	/*
	Method: get
	Get a WordPress setting from the settings array by key
	Parameters:
		key(String): key of setting as set in $this->settings
	*/
	public function get($key){
		return (array_key_exists($key, $this->settings))
			? $this->settings[$key]
			: null
		;
	}

	/*
	Method: set
	Set a WordPress setting in the settings array by key.  This will set the setting in the settings array, but will not actually apply the settings.
	Parameters:
		keyOrMap(String|Array): key of setting as set in $this->settings.  If an array, will run set for each key value pair in array.
		value(mixed): value to assign to settings
	*/
	public function set($keyOrMap, $value = null){
		if(is_array($keyOrMap)){
			foreach($keyOrMap as $key=> $value){
				$this->set($key, $value);
			}
		}else{
			$this->settings[$keyOrMap] = $value;
			$this->unappliedSettings[] = $keyOrMap;
		}
		return $this;
	}
}
