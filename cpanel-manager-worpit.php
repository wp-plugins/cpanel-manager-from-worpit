<?php
/*
Plugin Name: cPanel Manager (from Worpit)
Plugin URI: http://worpit.com/
Description: A tool to connect to your Web Hosting cPanel account from within your WordPress.
Version: 1.0
Author: Worpit
Author URI: http://worpit.com/
*/

/**
 * Copyright (c) 2012 Worpit <support@worpit.com>
 * All rights reserved.
 *
 * "cPanel Manager for WordPress, from Worpit" is
 * distributed under the GNU General Public License, Version 2,
 * June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110, USA
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

define( 'DS', DIRECTORY_SEPARATOR );

include_once( dirname(__FILE__).'/src/worpit-plugins-base.php' );
include_once( dirname(__FILE__).'/inc/lib/worpit/cpanel_api.php' );
include_once( dirname(__FILE__).'/inc/lib/worpit/Worpit_CPanelTransformer.php' );

class Worpit_CpanelManagerWordPress extends Worpit_Plugins_Base_Cpm {
	
	const OptionPrefix	= 'cpm_';
	
	protected $m_aPluginOptions_EnableSection;
	protected $m_aPluginOptions_CpmCredentialsSection;
	
	protected $m_aSubmitMessages;
	protected $m_aSubmitSuccess;
	
	protected $m_fSubmitCpmMainAttempt;
	
	static public $VERSION			= '1.0'; //SHOULD BE UPDATED UPON EACH NEW RELEASE
	
	public function __construct(){
		parent::__construct();

		register_activation_hook( __FILE__, array( &$this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'onWpDeactivatePlugin' ) );
	//	register_uninstall_hook( __FILE__, array( &$this, 'onWpUninstallPlugin' ) );
		
		self::$PLUGIN_NAME	= basename(__FILE__);
		self::$PLUGIN_PATH	= plugin_basename( dirname(__FILE__) );
		self::$PLUGIN_DIR	= WP_PLUGIN_DIR.DS.self::$PLUGIN_PATH.DS;
		self::$PLUGIN_URL	= WP_PLUGIN_URL.'/'.self::$PLUGIN_PATH.'/';
		self::$OPTION_PREFIX = self::BaseOptionPrefix . self::OptionPrefix;
		
		$this->m_fSubmitCpmMainAttempt = false;
		
		$this->m_sParentMenuIdSuffix = 'cpm';
	}//__construct

	public function onWpInit() {
		parent::onWpInit();
	}

	public function onWpAdminInit() {
		parent::onWpAdminInit();
	}
	
	protected function createPluginSubMenuItems(){
		$this->m_aPluginMenu = array(
				//Menu Page Title => Menu Item name, page ID (slug), callback function for this page - i.e. what to do/load.
				$this->getSubmenuPageTitle( 'cPanel Connect' ) => array( 'cPanel Connect', $this->getSubmenuId('main'), 'onDisplayCpmMain' ),
				$this->getSubmenuPageTitle( 'cPanel Tasks' ) => array( 'cPanel Tasks', $this->getSubmenuId('tasks'), 'onDisplayCpmCpanelTasks' ),
			);
	}//createPluginSubMenuItems
	
	public function onWpAdminNotices() {
		
		//Do we have admin priviledges?
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->adminNoticeOptionsUpdated();
		$this->adminNoticeVersionUpgrade();
		$this->adminNoticeSubmitMessages();
	}
	
	public function onWpDeactivatePlugin() {
		
		if ( !$this->initPluginOptions() ) {
			return;
		}

		$this->deleteAllPluginDbOptions();
	
	}//onWpDeactivatePlugin
	
	public function onWpActivatePlugin() {
	}//onWpActivatePlugin
	
	protected function handlePluginUpgrade() {
		
		//Someone clicked the button to acknowledge the update
		if ( isset( $_POST[self::$OPTION_PREFIX.'hide_update_notice'] ) && isset( $_POST['worpit_user_id'] ) ) {
			$result = update_user_meta( $_POST['worpit_user_id'], self::$OPTION_PREFIX.'current_version', self::$VERSION );
			header( "Location: admin.php?page=".$this->getFullParentMenuId() );
		}
	}
	
	/**
	 * Override for specify the plugin's options
	 */
	protected function initPluginOptions() {
		
		$this->m_aPluginOptions_EnableSection = 	array(
				'section_title' => 'Enable cPanel Manager for WordPress Feature',
				'section_options' => array(
					array( 'enable_cpanel_manager_wordpress',	'',		'N', 		'checkbox',		'cPanel Manager', 'Enable cPanel Manager for WordPress Features', "Provides the ability to connect to your cPanel web hosting account." ),
			),
		);
		
		$this->m_aPluginOptions_CpmCredentialsSection = 	array(
				'section_title' => 'cPanel Connection Credentials',
				'section_options' => array(
					array( 'cpanel_server_address',		'',		'', 		'text',		'cPanel Server Address:', '', 'Can either be a valid domain name, or an IP Address.' ),
					array( 'cpanel_server_port',		'',		'2083',		'text',		'cPanel Server Port:', '', 'Currently locked to 2083 in this version of the plugin.' ),
					array( 'cpanel_username',			'',		'', 		'text',		'cPanel Username:', '', 'This is your cPanel administrator username.' ),
					array( 'cpanel_password',			'',		'', 		'text',		'cPanel Password:', '', 'This is your cPanel administrator password.' ),
			),
		);

		$this->m_aAllPluginOptions = array( &$this->m_aPluginOptions_EnableSection, &$this->m_aPluginOptions_CpmCredentialsSection);
		
		return true;
		
	}//initPluginOptions
	
	
	protected function handlePluginFormSubmit() {

		if ( !$this->isWorpitPluginAdminPage() ) {
			return;
		}

		if ( !isset( $_POST['cpm_form_submit'] ) ) {
			return;
		}

		//Don't need to run isset() because previous function does this
		switch ( $_GET['page'] ) {
			case $this->getSubmenuId('main'):
				if ( isset( $_POST[self::$OPTION_PREFIX.'all_options_input'] ) ) {
					$this->handleSubmit_main( );
				}
				return;
			case $this->getSubmenuId('tasks'):

				$this->handleSubmit_tasks( );
				return;
		}
	
	}//handlePluginFormSubmit
	
	protected function handleSubmit_main() {

		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('main') );
		
		$this->m_fSubmitCpmMainAttempt = true;
		
		//Validate all the entries.
		$sAddress = strtolower( $_POST[self::$OPTION_PREFIX.'cpanel_server_address'] );
		$sAddress = preg_replace('/(\s|\*|\@|\&|\$)/', '', $sAddress);
		$iPort = intval( trim($_POST[self::$OPTION_PREFIX.'cpanel_server_port']) );
		$sUsername = trim( $_POST[self::$OPTION_PREFIX.'cpanel_username'] );
		
		if ( !empty( $sAddress ) && !self::IsValidDomainName($sAddress) ) {
			$sAddress = '';
		}
		if ( !empty( $iPort ) ) {
			if ( $iPort <= 1023 || $iPort >= 65535 ) {
				$iPort = 2083;
			}
		} else {
			$iPort = 2083;
		}
		$_POST[self::$OPTION_PREFIX.'cpanel_server_address'] = $sAddress;
		$_POST[self::$OPTION_PREFIX.'cpanel_server_port'] = $iPort;
		$_POST[self::$OPTION_PREFIX.'cpanel_username'] = $sUsername;

		$this->updatePluginOptionsFromSubmit( $_POST[self::$OPTION_PREFIX.'all_options_input'] );
	}//handleSubmit_main
	
	protected function handleSubmit_tasks() {

		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('tasks') );
		
		if ( isset( $_POST['cpm_submit_action'] ) ) {
			
			list( $sActionGroup, $sActionMember ) = explode( '_', $_POST['cpm_submit_action'], 2 );
			$sActionInclude = dirname( __FILE__ ).'/src/actions/CPM_ActionDelegate_'.ucfirst( $sActionGroup ).'.php';
			
			if ( file_exists( $sActionInclude ) ) {
				
				$aCpanelCredentials = array (
						self::getOption('cpanel_server_address'),
						self::getOption('cpanel_server_port'),
						self::getOption('cpanel_username'),
						self::getOption('cpanel_password'),
				);
				
				include_once( $sActionInclude );
				
			 	$sClassName = 'CPM_ActionDelegate_'.ucfirst( $sActionGroup );
				$oActionDelegate = new $sClassName( $_POST, $aCpanelCredentials );
				
				if ( $oActionDelegate->getIsValidState() ) {
					$oActionDelegate->reset();
					$this->m_aSubmitSuccess = $oActionDelegate->{$sActionMember}();
					$this->m_aSubmitMessages = $oActionDelegate->getMessages();
				}
			} else {
				//not implemented
			}
		}
		
	}//handleSubmit_tasks
	
	/**
	 * For each display, if you're creating a form, define the form action page and the form_submit_id
	 * that you can then use as a guard to handling the form submit.
	 */
	public function onDisplayCpmMain() {
		
		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();
		
		//Specify what set of options are available for this page
		$aAvailableOptions = array( &$this->m_aPluginOptions_EnableSection, &$this->m_aPluginOptions_CpmCredentialsSection) ;
		
		$sAllInputOptions = $this->collateAllFormInputsForOptionsSection( $this->m_aPluginOptions_EnableSection );
		$sAllInputOptions .= ','.$this->collateAllFormInputsForOptionsSection( $this->m_aPluginOptions_CpmCredentialsSection );
		
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::$OPTION_PREFIX,
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllInputOptions,
			'nonce_field'		=> $this->getSubmenuId('main'),
			'form_action'		=> 'admin.php?page='.$this->getFullParentMenuId().'-main'
		);
		
		$this->display( 'worpit_cpm_main', $aData );
	}//onDisplayCpmMain
	
	/**
	 * For each display, if you're creating a form, define the form action page and the form_submit_id
	 * that you can then use as a guard to handling the form submit.
	 */
	public function onDisplayCpmCpanelTasks() {
		
		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();
		
		//Specify what set of options are available for this page
		$aAvailableOptions = array( &$this->m_aPluginOptions_EnableSection, &$this->m_aPluginOptions_CpmCredentialsSection) ;
		
		$sAllInputOptions = $this->collateAllFormInputsForOptionsSection( $this->m_aPluginOptions_EnableSection );
		$sAllInputOptions .= ','.$this->collateAllFormInputsForOptionsSection( $this->m_aPluginOptions_CpmCredentialsSection );
		
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::$OPTION_PREFIX,
			'cpanel_enabled'	=> self::getOption('enable_cpanel_manager_wordpress'),
			'cpanel_server_address'		=> self::getOption('cpanel_server_address'),
			'cpanel_server_port'		=> self::getOption('cpanel_server_port'),
			'cpanel_username'	=> self::getOption('cpanel_username'),
			'cpanel_password'	=> self::getOption('cpanel_password'),
			'aAllOptions'		=> $aAvailableOptions,
			'page_link_options'	=> $this->getSubmenuId('main'),
			'nonce_field'		=> $this->getSubmenuId('tasks'),
			'form_action'		=> 'admin.php?page='.$this->getFullParentMenuId().'-tasks'
		);
		$this->display( 'worpit_cpm_tasks', $aData );
	}//onDisplayCpmCpanelTasks
	
	protected function initShortcodes() {
	
		$this->defineShortcodes();
		
		if ( function_exists('add_shortcode') && !empty( $this->m_aShortcodes ) ) {
			foreach( $this->m_aShortcodes as $shortcode => $function_to_call ) {
				add_shortcode($shortcode, array(&$this, $function_to_call) );
			}//foreach
		}
	}//initShortcodes

	/**
	 * Add desired shortcodes to this array.
	 */
	protected function defineShortcodes() {
		
		$this->m_aShortcodes = array();
	}//defineShortcodes

	private function adminNoticeOptionsUpdated() {
		
		//Admin notice for Main Options page submit.
		if ( $this->m_fSubmitCpmMainAttempt ) {
			
			if ( $this->m_fUpdateSuccessTracker ) {
				$sNotice = '<p>Updating CPM Plugin Options was a <strong>Success</strong>.</p>';
				$sClass = 'updated';
			} else {
				$sNotice = '<p>Updating CPM Plugin Options <strong>Failed</strong>.</p>';
				$sClass = 'error';
			}
			$this->getAdminNotice($sNotice, $sClass, true);
		}
	}//adminNoticeOptionsUpdated
	
	private function adminNoticeVersionUpgrade() {

		global $current_user;
		$user_id = $current_user->ID;

		$sCurrentVersion = get_user_meta( $user_id, self::$OPTION_PREFIX.'current_version', true );

		if ( $sCurrentVersion !== self::$VERSION ) {
			$sNotice = '
					<form method="post" action="admin.php?page='.$this->getFullParentMenuId().'">
						<p><strong>cPanel Manager for WordPress</strong> plugin has been updated. Worth checking out the latest docs.
						<input type="hidden" value="1" name="'.self::$OPTION_PREFIX.'hide_update_notice" id="'.self::$OPTION_PREFIX.'hide_update_notice">
						<input type="hidden" value="'.$user_id.'" name="worpit_user_id" id="worpit_user_id">
						<input type="submit" value="Okay, show me and hide this notice" name="submit" class="button-primary">
						</p>
					</form>
			';
			
			$this->getAdminNotice( $sNotice, 'updated', true );
		}
		
	}//adminNoticeVersionUpgrade
	
	private function adminNoticeSubmitMessages() {

		if ( $this->m_aSubmitSuccess ) {
			$sClasses = 'alert alert-success span12 updated';
		} else {
			$sClasses = 'alert alert-error span12 updated';
		}
		
		if ( !empty( $this->m_aSubmitMessages ) ) {
			
			foreach ($this->m_aSubmitMessages as $sMessage ) {
				$this->getAdminNotice( $sMessage, $sClasses, true );
			}
		}
		
	}//adminNoticeSubmitMessages
	

	/**
	 * Meat and Potatoes of the CBC plugin
	 * 
	 * By default, $insContent will be "shown" for whatever countries are specified.
	 * 
	 * Alternatively, set to 'n' if you want to hide.
	 * 
	 * Logic is: if visitor is coming from a country in the 'country' list and show='y', then show the content.
	 * OR
	 * If the visitor is not from a country in the 'country' list and show='n', then show the content.
	 * 
	 * Otherwise display 'message' if defined.
	 * 
	 * 'message' is displayed where the the content isn't displayed.
	 * 
	 * @param $inaAtts
	 * @param $insContent
	 */
	 
	static public function IsValidDomainName( $insUrl ) {

		$aPieces = explode( ".", $insUrl );
		foreach($aPieces as $sPiece) {
			if ( !preg_match('/^[a-z\d][a-z\d-]{0,62}$/i', $sPiece) || preg_match('/-$/', $sPiece) ) {
				return false;
			}
		}
		return true;
	}
	
}//CLASS


new Worpit_CpanelManagerWordPress( );