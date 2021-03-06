<?php
include_once("../_magi_class.php");

class plugins extends magi_class{
	var $_plugin_folder = "";
	var $_current_language = "";
	
	function plugins($plugin_folder = ""){
		$this->_plugin_folder = $plugin_folder;
		
		//$GLOBALS['userID'];
		if($GLOBALS['user_language'] != ""){
			$this->_current_language = $GLOBALS['user_language'];
		}else if($GLOBALS['default_language']!=""){
			$this->_current_language = $GLOBALS['default_language'];
		}
	}
	
	private function infoExists($plugin_folder){
		if(file_exists($plugin_folder."/_info.json")){
			return true;
		}
		return false;
	}
	
	private function getInfo($plugin_folder){
		if($this->infoExists("plugins/$plugin_folder")){
			$file = file_get_contents("plugins/".$plugin_folder."/_info.json");
			$json = json_decode($file, true);
			return $json['plugin'];
		}
		return false;
	}
	
	private function isComplete($plugin_folder){
		$info = $this->getInfo($plugin_folder);
		if($this->infoExists("plugins/$plugin_folder") && $info['installed']){
			return true;
		}
		return false;
	}
	
	public function isSite($sitename){
		$plugins = $this->getPlugins();
		foreach($plugins as $plugin){
			$name = $plugin['plugin']['info']['name'];
			$folder = $plugin['plugin']['info']['folder'];
			$sites = $plugin['plugin']['sites'];
			
			if(in_array($sitename, $sites)){
				return "plugins/".$folder."/".$sitename;
			}
		}
		return false;
	}
	
	public function isAdminSite($sitename){
		chdir("../");
		$plugins = $this->getPlugins();
		foreach($plugins as $plugin){
			$name = $plugin['plugin']['info']['name'];
			$folder = $plugin['plugin']['info']['folder'];
			$adminsite = $plugin['plugin']['admin'];
			if($adminsite == $sitename){
				return "plugins/".$folder."/".$sitename;
			}
		}
		return false;
	}
	
	public function getPlugins(){
		$plugins = array();
		$dirs = array_filter(glob('plugins/*'), 'is_dir');
		foreach($dirs as $dir){
			if(file_exists($dir."/_info.json")){
				$file = file_get_contents($dir."/_info.json");
				$json = json_decode($file, true);
				$plugins[] = $json;
			}
		}
		return $plugins;
	}
	
	public function getHeader(){
		echo ($this->getStyles()).($this->getScripts());
	}
	
	private function getStyles(){
		$plugins = $this->getPlugins();
		$styles = "";
		if(count($plugins)>0){
			foreach($plugins as $plugin){
				$plugin_path = $plugin['plugin']['info']['folder'];
				$plugin_styles = $plugin['plugin']['styles'];
				foreach($plugin_styles as $style){
					$styles .= ' 
					<link href="plugins/'.$plugin_path.'/css/'.$style.'" rel="stylesheet" type="text/css" /> 
					'.PHP_EOL;
				}
			}
		}
		return $styles;
	}
	
	private function getScripts(){
		$plugins = $this->getPlugins();
		$scripts = "";
		if(count($plugins)>0){
			foreach($plugins as $plugin){
				$plugin_path = $plugin['plugin']['info']['folder'];
				$plugin_scripts = $plugin['plugin']['scripts'];
				foreach($plugin_scripts as $script){
					$scripts .= ' 
					<script src="plugins/'.$plugin_path.'/js/'.$script.'" type="text/javascript"></script> 
					'.PHP_EOL;
				}
			}
		}
		return $scripts;
	}
	
	public function showWidget($name){
		$plugin_folder = $this->_plugin_folder;
		$plugin_path   = "plugins/$plugin_folder";
		if($this->isComplete($plugin_folder)){
			$plugin = $this->getInfo($plugin_folder);
			$widgets = $plugin['widgets'];
			if(in_array($name.".php", $widgets)){
				$widget_file = "$plugin_path/".$name.".php";
				return $widget_file;
			}
		}
		return false;
	}
	
	public function view($template, $section, $variables = array()){
		$template = $this->get_Template($template, $section);
		foreach($variables as $key=>$value){
			$template = str_replace("__".$key, $value, $template);
			$template = $this->replaceLanguage($template);
		}
		echo $template;
	}
	
	public function get_Template($template, $section){
		$plugin_folder = $this->_plugin_folder;
		$plugin_path   = "plugins/$plugin_folder";
		if($this->isComplete($plugin_folder)){
			$template_file = $plugin_path."/templates/".$template.".html";
			if(file_exists($template_file)){
				$file = file_get_contents($template_file);
				$section = strtoupper($section);
				$section_part = $this->get_string_between($file, "<!-- ".$section."_START -->", "<!-- ".$section."_END -->");
				return $section_part;
			}
		}
		return false;
	}
	
	public function getAdminMenu(){
		chdir("../");
		$plugins = $this->getPlugins();
		$menu = "";
		if(count($plugins)>0){
			foreach($plugins as $plugin){
				if($this->isComplete($plugin['plugin']['info']['folder'])){
					$name = $plugin['plugin']['info']['name'];
					$folder = $plugin['plugin']['info']['folder'];
					$adminsite = $plugin['plugin']['admin'];
					if(file_exists("plugins/$folder/$adminsite.php")){
						$menu .= "<li><a href='admincenter.php?site=$adminsite'>$name</a></li>";
					}
				}
			}
		}
		chdir("admin");
		echo $menu;
	}
	
	public function install($plugin_folder = ""){
		if($plugin_folder==""){
			$plugin_folder = $this->_plugin_folder;
		}else{
			$this->_plugin_folder = $plugin_folder;
		}
		
		if(!$this->isInstalled()){
			$plugin_path = "plugins/".$plugin_folder."/";
			if(file_exists($plugin_path.$plugin_folder."_install.php")){
				include($plugin_path.$plugin_folder."_install.php");
				if(isset($install_result)){
					if($install_result){
						@unlink($plugin_path.$plugin_folder."_install.php");
						
						$jsonString = file_get_contents($plugin_path.'_info.json');
						$data = json_decode($jsonString, true);
				
						$data["plugin"]["installed"] = 1;
						
						$newJsonString = json_encode($data);
						file_put_contents($plugin_path.'_info.json', $newJsonString);
						
						return $install_result;
					}else{
						echo "Not installed.";
					}
				}else{
					echo "No variable.";
				}
			}else{
				echo "File not exists";
			}
		}else{
			echo "It is just installed.";
		}
		return false;
	}
	
	public function uninstall($plugin_folder = ""){
		if($plugin_folder==""){
			$plugin_folder = $this->_plugin_folder;
		}else{
			$this->_plugin_folder = $plugin_folder;
		}
		if($this->isInstalled()){
			$plugin_path = "plugins/".$plugin_folder."/";
			$infos = $this->getInfo($plugin_folder);
			$db_tables = $infos["tables"];
			foreach($db_tables as $table){
				$delete_query = "DROP TABLE IF EXISTS ".PREFIX.$table."";
				if(!$result = $this->safe_query($delete_query)){
					return false;
				}
			}
			if($this->Delete_Folder('plugins/'.$plugin_folder.'/')){
				return true;
			}
			return false;
		}else{
			echo "It is not installed.";
		}
		return false;
	}
	
	public function isInstalled($plugin_folder = ""){
		if($plugin_folder==""){
			$plugin_folder = $this->_plugin_folder;
		}else{
			$this->_plugin_folder = $plugin_folder;
		}
		$json = $this->getInfo($plugin_folder);
		$isInstalled = $json['installed'];
		return $isInstalled && !file_exists("../plugins/".$plugin_folder."/".$plugin_folder."_install.php");
	}
	
	public function replaceLanguage($inputString){
		$lang_array = $this->getLanguageFile($this->_current_language);
		foreach($lang_array as $control=>$word){
			$inputString = str_replace("%".$control."%", $word, $inputString);
		}
		return $inputString;
	}
	
	public function getTranslation($inputControl){
		$lang_array = $this->getLanguageFile($this->_current_language);
		if(array_key_exists ( $inputControl , $lang_array )){
			return $lang_array[$inputControl];
		}
		return false;
	}
	
	private function getLanguageFile($language){
		$plugin_folder = $this->_plugin_folder;
		$plugin_info = $this->getInfo($plugin_folder);
		$plugin_path = "plugins/".$plugin_folder."/languages/";
		if(file_exists($plugin_path.$language.".php")){
			include($plugin_path.$language.".php");
			if(isset($_languages)){
				return $_languages;
			}
		}else{
			// fallback
			include($plugin_path.$plugin_info["default_language"].".php");
			if(isset($_languages)){
				return $_languages;
			}
		}
		return false;
	}
	
}
?>