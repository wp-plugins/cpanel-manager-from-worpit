<?php

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

include_once( dirname(__FILE__).'/../../inc/lib/worpit/Worpit_CPanelTransformer.php' );

class CPM_ActionDelegate_Database {
	
	protected $m_aData; // likely comes from $_POST
	protected $m_oCpanel_Api;
	protected $m_oLastApiResponse;
	
	protected $m_aMessages;
	
	protected $m_fGoodToGo;
	
	public function __construct( $inaData, $inaCpanelCreds ) {
		$this->m_aData = $inaData;
		$this->m_fGoodToGo = $this->connectToCpanel( $inaCpanelCreds );
	}
	
	public function getMessages() {
		return $this->m_aMessages;
	}
	
	public function getIsValidState() {
		return $this->m_fGoodToGo;
	}
	
	/**
	 * Given certain cPanel Credentials attempts to create a CPanel_Api object.
	 * 
	 * Returns true upon success. False otherwise.
	 * 
	 * @param $inaData - cPanel Credentials
	 */
	public function connectToCpanel( $inaCpanelCreds ) {
		
		list( $sServerAddress, $sServerPort, $sUsername, $sPassword ) = $inaCpanelCreds;
		
		$this->m_oCpanel_Api = null;
		$this->m_aMessages = array();
		$fValidConnectionData = true;
		
		if ( empty($sServerAddress) ) {
			$this->m_aMessages[] = 'cPanel Server Address is empty.';
			$fValidConnectionData = false;
		}
		
		if ( empty($sServerPort) ) {
			$this->m_aMessages[] = 'cPanel Server Port is empty.';
			$fValidConnectionData = false;
		}
		if ( empty($sUsername) ) {
			$this->m_aMessages[] = 'cPanel Username is empty.';
			$fValidConnectionData = false;
		}
		if ( empty($sPassword) ) {
			$this->m_aMessages[] = 'cPanel Password is empty.';
			$fValidConnectionData = false;
		}
		
		if ( $fValidConnectionData ) {
			try {
				$this->m_oCpanel_Api = new CPanel_Api($sServerAddress, $sUsername, $sPassword);
			} catch (Exception $oE) {
				$this->m_aMessages[] = 'Failed to connect to cPanel with credentials provided. Error returned was... <strong>'.$oE->getMessage().'</strong>';
			}
		}

		return !is_null( $this->m_oCpanel_Api );
	}
	
	public function reset( $inaData = null ) {
		
		$this->m_aMessages = array();
		
		if ( !is_null( $inaData ) ) {
			$this->m_aData = $inaData;
		}
	}
	
	/**
	 * Assuming inputs are valid, will create a new database and new database user and assign the user will full
	 * permissions to it.
	 * 
	 * $this->m_aData must contain database_name, database_user, database_user_password
	 */
	public function createdb_adduser() {
		
		$sErrorPrefix = "No attempt was made to create a new database and add new user because: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty( $this->m_aData ) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		if ( !isset( $this->m_aData['confirm_action'] ) || !self::ValidateConfirmAction( $this->m_aData['confirm_action'], $this->m_aMessages ) ) {
			$this->m_aMessages[] = $sErrorPrefix."You need to type CONFIRM in the confirmation box before any action occurs.";
			return false;
		}
		
		$fValidState = true;
		$fValidState = self::ValidateDatabaseName( $this->m_aData['database_name'], $this->m_aMessages ) && $fValidState;
		$fValidState = self::ValidateDatabaseUser( $this->m_aData['database_user'], $this->m_aMessages ) && $fValidState;
		$this->m_fGoodToGo = self::ValidateDatabaseUserPassword( $this->m_aData['database_user_password'], $this->m_aMessages ) && $fValidState;
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix."Your inputs had problems. Please Check.";
			return false;
		}
		
		$fSuccess = false;
		if ( $this->createNewMySqlDb( $this->m_aData['database_name'] ) ) { // Successfully created database
			
			// Successfully created new user
			if ( $this->createNewMySqlUser( $this->m_aData['database_user'], $this->m_aData['database_user_password'] ) ) {
				$fSuccess = $this->addMySqlUserToDb( $this->m_aData['database_name'], $this->m_aData['database_user'] );
			}
			else {
				$this->m_aMessages[] = "Did not attempt to add user to DB due to previous error.";
			}
			
		}
		else {
			$this->m_aMessages[] = "Did not attempt to create new user due to previous error.";
		}
		
		return $fSuccess;
		
	}//createdb_adduser
	
	/**
	 * Will create new MySQL user and add it to a list of Databases if specified.
	 * 
	 * 'database_new_user'
	 * 'database_new_user_password'
	 * 'databases_to_add_new_user[]'
	 */
	public function create_mysqluser() {
		
		$sErrorPrefix = "No attempt was made to create new MySQL user because: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty( $this->m_aData ) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		if ( !isset( $this->m_aData['confirm_action'] ) || !self::ValidateConfirmAction( $this->m_aData['confirm_action'], $this->m_aMessages ) ) {
			$this->m_aMessages[] = $sErrorPrefix."You need to type CONFIRM in the confirmation box before any action occurs.";
			return false;
		}

		$fValidState = true;
		$fValidState = self::ValidateDatabaseUser( $this->m_aData['database_new_user'], $this->m_aMessages ) && $fValidState;
		$this->m_fGoodToGo = self::ValidateDatabaseUserPassword( $this->m_aData['database_new_user_password'], $this->m_aMessages ) && $fValidState;
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix."Your inputs had problems. Please Check.";
			return false;
		}
		
		$fSuccess = false;
		if ( $this->createNewMySqlUser( $this->m_aData['database_new_user'], $this->m_aData['database_new_user_password'] ) ) { // Successfully created user
			
			// Add User to DBs if they're specified
			if ( isset( $this->m_aData['databases_to_add_new_user'] ) && is_array( $this->m_aData['databases_to_add_new_user'] ) ) {
				
				foreach( $this->m_aData['databases_to_add_new_user'] as $sDb ) {
					$fSuccess = $this->addMySqlUserToDb( $sDb, $this->m_aData['database_new_user'] );
				}
			}
		}
		else {
			$this->m_aMessages[] = "Did not attempt to add new user to databases due to previous error.";
		}
		
		return true;
		
	}//create_mysqluser
	
	
	/**
	 * Will delete all databases from the cPanel account with names that correspond to elements
	 * in the array that is populated in position 'databases_to_delete_names' in the main data array. 
	 * 
	 */
	public function delete_mysqldbs() {
		
		$sErrorPrefix = "No attempt was made to delete MySQL database because: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty( $this->m_aData ) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		if ( !isset( $this->m_aData['confirm_action'] ) || !self::ValidateConfirmAction( $this->m_aData['confirm_action'], $this->m_aMessages ) ) {
			$this->m_aMessages[] = $sErrorPrefix."You need to type CONFIRM in the confirmation box before any action occurs.";
			return false;
		}
		if ( !isset( $this->m_aData['databases_to_delete_names'] ) || !is_array( $this->m_aData['databases_to_delete_names'] ) ) {
			$this->m_aMessages[] = $sErrorPrefix."No MySQL databases were selected.";
			return false;
		}
		
		$aDatabaseNames = $this->m_aData['databases_to_delete_names'];
		
		$fSuccess = true;
		foreach( $aDatabaseNames as $sDatabaseName ) {
		
			$this->m_oCpanel_Api->doApiFunction( "Mysql", "deldb", array( $sDatabaseName ) );
			$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
			
			if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
				$fSuccess = true;
				$this->m_aMessages[] = "Deleting MySQL database from cPanel account succeeded: ".$sDatabaseName; 
			}
			else {
				$fSuccess = false;
				$this->m_aMessages[] = "Deleting MySQL database from cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse );
				$this->m_aMessages[] = "Stopping further processing due to previous failure.";
			}
			
			if ( !$fSuccess ) {
				break;
			}
		}
		
		return $fSuccess;
	}
	
	
	/**
	 * Will delete all databases from the cPanel account with names that correspond to elements
	 * in the array that is populated in position 'databases_to_delete_names' in the main data array. 
	 * 
	 */
	public function delete_mysqlusers() {
		
		$sErrorPrefix = "No attempt was made to delete MySQL users because: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty( $this->m_aData ) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		if ( !isset( $this->m_aData['confirm_action'] ) || !self::ValidateConfirmAction( $this->m_aData['confirm_action'], $this->m_aMessages ) ) {
			$this->m_aMessages[] = $sErrorPrefix."You need to type CONFIRM in the confirmation box before any action occurs.";
			return false;
		}
		
		if ( !isset( $this->m_aData['users_to_delete_names'] ) || !is_array( $this->m_aData['users_to_delete_names'] ) ) {
			$this->m_aMessages[] = $sErrorPrefix."No MySQL users were selected.";
			return false;
		}
		
		$aUserNames = $this->m_aData['users_to_delete_names'];
		
		$fSuccess = true;
		foreach( $aUserNames as $sUserName ) {
		
			$this->m_oCpanel_Api->doApiFunction( "Mysql", "deluser", array( $sUserName ) );
			$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
			
			if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
				$fSuccess = true;
				$this->m_aMessages[] = "Deleting MySQL user from cPanel account succeeded: ".$sUserName; 
			}
			else {
				$fSuccess = false;
				$this->m_aMessages[] = "Deleting MySQL user from cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse );
				$this->m_aMessages[] = "Stopping further processing due to previous failure.";
			}
			
			if ( !$fSuccess ) {
				break;
			}
		}
		
		return $fSuccess;
	}
	
	/**
	 * Creates a new database on the current cPanel connection with the given name provided in the data.
	 * 
	 * $inaData must contain the alphanumeric field: database_name
	 * 
	 * There is the option to send new data with $inaData.
	 */
	protected function createNewMySqlDb( $sDbName ) {
		
		$sErrorPrefix = "No attempt was made to create a new MySQL database: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty($this->m_aData) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		
		$this->m_oCpanel_Api->doApiFunction( "Mysql", "adddb", array( $sDbName ) );
		$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
		
		if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
			$fSuccess = true;
			$this->m_aMessages[] = "Adding new MySQL database to cPanel account succeeded: ".$this->m_aData['database_name']; 
		}
		else {
			$fSuccess = false;
			$this->m_aMessages[] = "Adding new MySQL database to cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse ); 
		}
		
		return $fSuccess;
		
	}
	
	public function createNewMySqlUser( $sUsername, $sPassword ) {
		
		$sErrorPrefix = "No attempt was made to create a new MySQL User: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty($this->m_aData) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		
		$this->m_oCpanel_Api->doApiFunction( "Mysql", "adduser", array( $sUsername, $sPassword ) );
		$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
		
		if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
			$fSuccess = true;
			$this->m_aMessages[] = "Creating new MySQL User on cPanel account succeeded: ".$sUsername .' / '. $sPassword; 
		}
		else {
			$fSuccess = false;
			$this->m_aMessages[] = "Creating new MySQL User on cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse ); 
		}
		
		return $fSuccess;
		
	}//createNewMySqlUser
	
	public function addMySqlUserToDb( $sDbName, $sUsername ) {
		
		$sErrorPrefix = "No attempt was made to add new MySQL User to the DB because: ";
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix.'The system is not currently in a valid state.';
			return false;
		}
		if ( empty($this->m_aData) ) {
			$this->m_aMessages[] = $sErrorPrefix."The data from which we're supposed to work is empty/null.";
			return false;
		}
		
		$this->m_oCpanel_Api->doApiFunction( "Mysql", "adduserdb", array( $sDbName, $sUsername, 'all'  ) );
		$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
		
		if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
			$fSuccess = true;
			$this->m_aMessages[] = "Adding new MySQL User ('$sUsername') to DB ('$sDbName') succeeded: "; 
		}
		else {
			$fSuccess = false;
			$this->m_aMessages[] = "Adding new MySQL User to DB FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse ); 
		}
		
		return $fSuccess;
		
	}
	
	public function deleteMySqlDatabases() {
		
	}//deleteMySqlDatabases
	
	public static function ValidateDatabaseName( $sDatabaseName, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $sDatabaseName ) ) {
		
			if ( !self::IsAlphaNumeric($sDatabaseName) ) {
				$aMessages[] = "The database name option is not numbers/letters (abc123...).";
				$fValidState = false;
			}
			if ( strlen($sDatabaseName) > 63 ) {
				$aMessages[] = "The database name option is too long ( 63 characters or less ).";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The database name option is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//ValidateDatabaseName
	
	public static function ValidateDatabaseUser( $sDatabaseUser, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $sDatabaseUser ) ) {
		
			if ( !self::IsAlphaNumeric($sDatabaseUser) ) {
				$aMessages[] = "The database user option is not numbers/letters only (abc123...).";
				$fValidState = false;
			}
			if ( strlen($sDatabaseUser ) > 7 ) {
				$aMessages[] = "The database user option is too long ( 7 characters or less ).";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The database user option is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//validateDatabaseUser
	
	public static function ValidateDatabaseUserPassword( $sDatabaseUserPassword, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $sDatabaseUserPassword ) ) {
			
			if ( strlen($sDatabaseUserPassword ) < 6 ) {
				$aMessages[] = "The database user password option is too short ( 6 characters or more ).";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The database user password option is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//validateDatabaseUser
	
	public static function ValidateConfirmAction( $sConfirmText, &$aMessages ) {
		
		$fValidState = true;
		
		if ( empty( $sConfirmText ) || !preg_match( '/^CONFIRM$/', $sConfirmText ) ) {
			$fValidState = false;
		}
		
		return $fValidState;
	}//validateDatabaseUser
	
	protected static function IsAlphaNumeric( $insString = '' ) {
		return preg_match( '/^[A-Za-z0-9]+$/', $insString );
	} 

}//class
			