<?php

	Class extension_frontend_authentication extends Extension{

		public function about(){
			return array('name' => 'Front End Authentication',
						 'version' => '1.0',
						 'release-date' => '2008-09-24',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'team@symphony21.com')
				 		);
		}
	
		public function uninstall(){
			$this->_Parent->Configuration->remove('frontend-authentication');			
			$this->_Parent->saveConfig();
		}
	
		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),
						
						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendPageResolved',
							'callback' => '__authenticate'
						),	
						
						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendEventPostProcess',
							'callback' => '__appendEventXML'
						),
						
						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendParamsResolve',
							'callback' => '__processForgottenPasswordRequest'
						),
											
						array(
							'page' => '/system/preferences/',
							'delegate' => 'Save',
							'callback' => '__SavePreferences'
						),						
													

					);
		}
		
		public function __SavePreferences($context){
			
			$context['settings']['frontend-authentication']['email-subject'] = $context['settings']['frontend-authentication']['email-subject'];
			$context['settings']['frontend-authentication']['email-body'] = $context['settings']['frontend-authentication']['email-body'];
			
			if(!isset($_POST['settings']['frontend-authentication']['use-sessions'])){
				$context['settings']['frontend-authentication']['use-sessions'] = 'no';
			}
		}
		
		private static function __replaceParams($string, $params){
			foreach($params as $key => $value){
				$string = str_replace("{\$$key}", $value, $string);
			}
			
			return $string;
		}
		
		public function __processForgottenPasswordRequest($context){			
			if(isset($_POST['action']['front-end-authentication']['forgot'])){
				
				$username = (function_exists('mysql_real_escape_string') 
					? mysql_real_escape_string($_POST['front-end-authentication']['username']) 
					: addslashes($_POST['front-end-authentication']['username']));
					
				$password = $this->__getPasswordFromUsername($username);		
				
				if(strlen($password) > 0){
					$params = $context['params'];
					$params += array('username' => $username, 'password' => $password);
					
					$subject = self::__replaceParams(stripslashes($this->_Parent->Configuration->get('email-subject', 'frontend-authentication')), $params);
					$body = self::__replaceParams(stripslashes($this->_Parent->Configuration->get('email-body', 'frontend-authentication')), $params);

					General::sendEmail($username, 'noreply@' . parse_url($params['root'], PHP_URL_HOST), $params['website-name'], $subject, $body);
										
				}
				
				define_safe('FRONT_END_AUTHENTICATION_EMAIL_SENT', true);
				return;
			}
		}
		
		public function __appendEventXML($context){
			
			if(defined('FRONT_END_AUTHENTICATION_SUCCESSFUL')){
				$context['xml']->appendChild(new XMLElement('front-end-authentication', NULL, array('status' => (FRONT_END_AUTHENTICATION_SUCCESSFUL == true ? 'authenticated' : 'invalid'))));
			}
			
			elseif(defined('FRONT_END_AUTHENTICATION_EMAIL_SENT')){
				$context['xml']->appendChild(new XMLElement('front-end-authentication', NULL, array('password-retrieval-email-status' => 'sent')));				
			}
			
		}
		
		private function __createCookie($username, $password){
			if($this->_Parent->Configuration->get('use-sessions', 'frontend-authentication') == 'yes'){
				$_SESSION[__SYM_COOKIE_PREFIX_ . 'front-end-authentication'] = array('username' => $username, 'password' => md5($password));
			}
			
			else{
				$Cookie = new Cookie(__SYM_COOKIE_PREFIX_ . 'front-end-authentication', (24 * 60 * 60), __SYM_COOKIE_PATH__);
				$Cookie->set('username', $username);
				$Cookie->set('password', md5($password));		
			}
		}
		
		private function __expireCookie(){
			
			session_destroy();

			$Cookie = new Cookie(__SYM_COOKIE_PREFIX_ . 'front-end-authentication', (24 * 60 * 60), __SYM_COOKIE_PATH__);
			$Cookie->expire();
			
		}
		
		private function __validate($username, $password, $isHash=false){
			
			$username = (function_exists('mysql_real_escape_string') ? mysql_real_escape_string($username) : addslashes($username));
			$password = (function_exists('mysql_real_escape_string') ? mysql_real_escape_string($password) : addslashes($password));
			
			$username_field = $this->_Parent->Configuration->get('username-field', 'frontend-authentication');
			$password_field = $this->_Parent->Configuration->get('password-field', 'frontend-authentication');
			
			$sql = "SELECT `t1`.entry_id 
					FROM `tbl_entries_data_{$username_field}` AS `t1` 
					LEFT JOIN `tbl_entries_data_{$password_field}` AS `t2` ON t1.entry_id = t2.entry_id 
					WHERE `t1`.value = '{$username}' AND ".($isHash ? "MD5(`t2`.`value`)" : "t2.value")." = '{$password}'
					LIMIT 1";
					
			$id = $this->_Parent->Database->fetchVar('entry_id', 0, $sql);
			
			return ((integer)$id > 0);
		}
		
		private function __getPasswordFromUsername($username){
			
			$username = (function_exists('mysql_real_escape_string') ? mysql_real_escape_string($username) : addslashes($username));
			
			$username_field = $this->_Parent->Configuration->get('username-field', 'frontend-authentication');
			$password_field = $this->_Parent->Configuration->get('password-field', 'frontend-authentication');			
			
			$sql = "SELECT `t2`.value AS `password`
					FROM `tbl_entries_data_{$username_field}` AS `t1` 
					LEFT JOIN `tbl_entries_data_{$password_field}` AS `t2` ON t1.entry_id = t2.entry_id 
					WHERE `t1`.value = '{$username}'
					LIMIT 1";
					
	 		return $this->_Parent->Database->fetchVar('password', 0, $sql);			
		}
		
		public function __authenticate($context){
			
			$path = '/' . $this->_Parent->resolvePagePath($context['page_data']['id']);
			$bOnLoginPage = ($path == $context['parent']->Configuration->get('login-page', 'frontend-authentication'));
			
			if($this->_Parent->Configuration->get('use-sessions', 'frontend-authentication') == 'yes'){
				session_start();
			}
			
			if(isset($_GET['front-end-authentication-logout'])){
				$this->__expireCookie();
			}

			$types = $context['page_data']['type'];
			
			if(!$bOnLoginPage && (!is_array($types) || empty($types) || !in_array($context['parent']->Configuration->get('page-type', 'frontend-authentication'), $types))) return;

			## Check for post data, use it for creation of a cookie
			if(isset($_POST['action']['front-end-authentication']['login'])){
				
				$username = $_POST['front-end-authentication']['username'];
				$password = $_POST['front-end-authentication']['password'];
				
				if($this->__validate($username, $password)){
					$this->__createCookie($username, $password);
					define_safe('FRONT_END_AUTHENTICATION_SUCCESSFUL', true);
					if($bOnLoginPage) redirect(URL);
					return;
				}
				
				define_safe('FRONT_END_AUTHENTICATION_SUCCESSFUL', false);
				
			}
			
			## Check for a session, and validate it			
			elseif($this->_Parent->Configuration->get('use-sessions', 'frontend-authentication') == 'yes'){
				if(isset($_SESSION[__SYM_COOKIE_PREFIX_ . 'front-end-authentication'])){
					$username = $_SESSION[__SYM_COOKIE_PREFIX_ . 'front-end-authentication']['username'];
					$password = $_SESSION[__SYM_COOKIE_PREFIX_ . 'front-end-authentication']['password'];
				
					if($this->__validate($username, $password, true)){
						define_safe('FRONT_END_AUTHENTICATION_SUCCESSFUL', true);
						if($bOnLoginPage) redirect(URL);
						return;
					}
				
					$this->__expireCookie();
				}
			}


			## Check for a cookie, and validate it			
			elseif(isset($_COOKIE[__SYM_COOKIE_PREFIX_ . 'front-end-authentication'])){
				
				$username = $_COOKIE[__SYM_COOKIE_PREFIX_ . 'front-end-authentication']['username'];
				$password = $_COOKIE[__SYM_COOKIE_PREFIX_ . 'front-end-authentication']['password'];
				
				if($this->__validate($username, $password, true)){
					define_safe('FRONT_END_AUTHENTICATION_SUCCESSFUL', true);
					if($bOnLoginPage) redirect(URL);
					return;
				}
				
				$this->__expireCookie();
				
			}
						
			## No luck, kick to login page
			$context['page_data'] = $context['page']->resolvePage(trim($context['parent']->Configuration->get('login-page', 'frontend-authentication'), '/'));
			
		}
		
		public function appendPreferences($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Front End Authentication'));
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$label = Widget::Label('Page Type');
			$label->appendChild(Widget::Input('settings[frontend-authentication][page-type]', General::Sanitize($context['parent']->Configuration->get('page-type', 'frontend-authentication'))));		
			$div->appendChild($label);
	
			$pages = $this->_Parent->Database->fetch("SELECT `id`, `title` FROM `tbl_pages` ORDER BY `title` ASC");
			$label = Widget::Label('Login Page');
			$options = array();

			if(is_array($pages) && !empty($pages)){
				foreach($pages as $page){
					$path = '/' . $this->_Parent->resolvePagePath($page['id']);
					$options[] = array($path, $context['parent']->Configuration->get('login-page', 'frontend-authentication') == $path, $path);
				}
			}
			$label->appendChild(Widget::Select('settings[frontend-authentication][login-page]', $options));		
			$div->appendChild($label);
			$group->appendChild($div);
			$group->appendChild(new XMLElement('p', 'Any page with this type will check for a valid cookie, otherwise be thrown to a login screen', array('class' => 'help')));
			
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$fields = $this->_Parent->Database->fetch("SELECT t1.*, t2.name as `section` FROM `tbl_fields` AS `t1` 
													  LEFT JOIN `tbl_sections` AS `t2` ON `t1`.parent_section = t2.id 
													  WHERE `t1`.type NOT IN ('checkbox', 'select', 'sectionlink', 'date', 'textarea', 'upload', 'author')
													  ORDER BY t1.`parent_section` ASC, t1.`element_name` ASC");
										
			$label = Widget::Label('Username Field');
			$options = array();

			if(is_array($fields) && !empty($fields)){
				foreach($fields as $field){
					$options[] = array($field['id'], $context['parent']->Configuration->get('username-field', 'frontend-authentication') == $field['id'], $field['section'] . ' > ' . $field['label']);
				}
			}
			$label->appendChild(Widget::Select('settings[frontend-authentication][username-field]', $options));		
			$div->appendChild($label);


			$label = Widget::Label('Password Field');
			$options = array();

			if(is_array($fields) && !empty($fields)){
				foreach($fields as $field){
					$options[] = array($field['id'], $context['parent']->Configuration->get('password-field', 'frontend-authentication') == $field['id'], $field['section'] . ' > ' . $field['label']);
				}
			}
			$label->appendChild(Widget::Select('settings[frontend-authentication][password-field]', $options));		
			$div->appendChild($label);
			$group->appendChild($div);
			$group->appendChild(new XMLElement('p', 'Both fields must be from the same section', array('class' => 'help')));
			
			$label = Widget::Label();
			$input = Widget::Input('settings[frontend-authentication][use-sessions]', 'yes', 'checkbox');
			if($this->_Parent->Configuration->get('use-sessions', 'frontend-authentication') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Use sessions instead of cookies');
			$group->appendChild($label);
			
			$group->appendChild(new XMLElement('p', 'Sessions expire when the browser is closed. They are stored on the server are considered more secure than cookies.', array('class' => 'help')));
			
			$label = Widget::Label('Password Retrieval Email Subject');
			$label->appendChild(Widget::Input('settings[frontend-authentication][email-subject]', stripslashes($context['parent']->Configuration->get('email-subject', 'frontend-authentication'))));
			$group->appendChild($label);

			$label = Widget::Label('Password Retrieval Email Body');
			$label->appendChild(Widget::Textarea('settings[frontend-authentication][email-body]', 10, 50, stripslashes($context['parent']->Configuration->get('email-body', 'frontend-authentication'))));
			$group->appendChild($label);
				
			$group->appendChild(new XMLElement('p', 'Use <code>{$password}</code> and <code>{$username}</code> for dynamic values. Any parameters in the XSLT can also be used. E.G. <code>{$root}</code>', array('class' => 'help')));	
						
			$context['wrapper']->appendChild($group);
						
		}
		
	}