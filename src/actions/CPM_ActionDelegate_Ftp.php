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
include_once( dirname(__FILE__).'/CPM_ActionDelegate_Base.php' );

class CPM_ActionDelegate_Ftp extends CPM_ActionDelegate_Base {
		
	/**
	 * Assuming inputs are valid, will create a new database and new database user and assign the user will full
	 * permissions to it.
	 * 
	 * $this->m_aData must contain database_name, database_user, database_user_password
	 */
	public function create_ftpuser() {
		
		$aVars = array( 'ftp_new_user', 'ftp_new_user_password', 'ftp_new_user_quota', 'ftp_new_user_homedir' );
		
		if ( !$this->preActionBasicValidate( $aVars, 'create a new FTP user' ) ) {
			return false;
		}
		
		$fValidState = true;
		$fValidState = self::ValidateFtpUser( $this->m_aData['ftp_new_user'], $this->m_aMessages ) && $fValidState;
		$fValidState = self::ValidateUserPassword( $this->m_aData['ftp_new_user_password'], $this->m_aMessages ) && $fValidState;
		$fValidState = self::ValidateFtpQuota( $this->m_aData['ftp_new_user_quota'], $this->m_aMessages ) && $fValidState;
		$this->m_fGoodToGo = self::ValidateDirectory( $this->m_aData['ftp_new_user_homedir'], $this->m_aMessages ) && $fValidState;
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix."Your inputs had problems. Please Check.";
			return false;
		}
		
		$fSuccess = false;
		
		$fSuccess = $this->createNewFtpUser(
						$this->m_aData['ftp_new_user'],
						$this->m_aData['ftp_new_user_password'],
						$this->m_aData['ftp_new_user_quota'],
						$this->m_aData['ftp_new_user_homedir'] );
		
		return $fSuccess;
		
	}//create_ftpuser

	public function createNewFtpUser( $insUsername, $insPassword, $insQuota, $insHomedir ) {
		
		$aVars = array();
		
		if ( !$this->preActionBasicValidate( $aVars, 'create a new FTP User' ) ) {
			return false;
		}
		
		$aArgs = array(
					'user'		=> $insUsername,
					'pass'		=> $insPassword,
					'quota'		=> intval($insQuota),
					'homedir'	=> $insHomedir
				);
		
		$this->m_oCpanel_Api->doApiFunction( "Ftp", "addftp", $aArgs );
		$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
		
		if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
			$fSuccess = true;
			$this->m_aMessages[] = "Creating new FTP User on cPanel account succeeded: ".$insUsername.'|'.$insPassword.'|'.$insQuota.'|'.$insHomedir; 
		}
		else {
			$fSuccess = false;
			$this->m_aMessages[] = "Creating new FTP User ( $insUsername | $insPassword | $insQuota | $insHomedir ) on cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse ); 
		}
		
		return $fSuccess;
		
	}//createNewFtpUser
	
	public function create_ftpusersbulk() {
		
		$aVars = array( 'ftp_new_user_bulk' );
		
		if ( !$this->preActionBasicValidate( $aVars, 'create new FTP users' ) ) {
			return false;
		}
		
		if ( !isset($this->m_aData['ftp_new_user_bulk']) || empty($this->m_aData['ftp_new_user_bulk']) ) {
			$this->m_aMessages[] = "No new FTP User details were provided.";
			return false;
		}
		
		$fValidState = true;
		$aAllNewUsers = array();
		$fValidState = self::ValidateFtpUsersBulk( $this->m_aData['ftp_new_user_bulk'], $aAllNewUsers, $this->m_aMessages ) && $fValidState;

		if ( $fValidState && !empty($aAllNewUsers) ) {
			
			$sBaseHomedir = trim( $this->m_aData['ftp_new_user_bulk_homedir'], '/' );
			if ( !empty($sBaseHomedir) ) {
				$sBaseHomedir .= '/';
			}
			
			foreach ( $aAllNewUsers as $sNewFtpUser ) {
				
				$aNewFtpUserDetails = explode( ',', $sNewFtpUser );
				list( $sUsername, $sPassword, $sQuota ) = $aNewFtpUserDetails;
				
				$this->m_aData['ftp_new_user'] = $sUsername;
				$this->m_aData['ftp_new_user_password'] = $sPassword;
				$this->m_aData['ftp_new_user_quota'] = $sQuota;
				$this->m_aData['ftp_new_user_homedir'] = $sBaseHomedir . $sUsername;
				
				$fValidState = $this->create_ftpuser();
				
				if (!$fValidState) {
					break;
				}
			}
		}
		
		
		return $fValidState;
	}
	/**
	 * Will delete all databases from the cPanel account with names that correspond to elements
	 * in the array that is populated in position 'databases_to_delete_names' in the main data array. 
	 * 
	 */
	public function delete_ftpusers() {
		
		$aVars = array();
		
		if ( !$this->preActionBasicValidate( $aVars, 'to delete FTP users' ) ) {
			return false;
		}
		
		if ( !isset( $this->m_aData['users_to_delete_names'] ) || !is_array( $this->m_aData['users_to_delete_names'] ) ) {
			$this->m_aMessages[] = "No FTP users were selected.";
			return false;
		}
		
		$aUserNames = $this->m_aData['users_to_delete_names'];
		
		$fSuccess = true;
		foreach( $aUserNames as $sUserName ) {
		
			$aArgs = array (
					'user' => $sUserName,
					'destroy' => ( ( isset($this->m_aData['ftp_delete_users_disk']) )? 1 : 0 ),
					);
			
			$this->m_oCpanel_Api->doApiFunction( "Ftp", "delftp", $aArgs );
			$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
			
			if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
				$fSuccess = true;
				$this->m_aMessages[] = "Deleting FTP user from cPanel account succeeded: ".$sUserName; 
			}
			else {
				$fSuccess = false;
				$this->m_aMessages[] = "Deleting FTP user from cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse );
				$this->m_aMessages[] = "Stopping further processing due to previous failure.";
			}
			
			if ( !$fSuccess ) {
				break;
			}
		}
		
		return $fSuccess;
	}
	
	public static function ValidateFtpUsersBulk( $insFtpUserDataBulk, &$inaAllNewUsers, &$inaMessages ) {
		
		$fValidState = true;
		if ( !empty( $insFtpUserDataBulk ) ) {
			
			$inaAllNewUsers = explode( "\n", $insFtpUserDataBulk );

			$iCount = -1;
			foreach( $inaAllNewUsers as $sNewUserLine ) {
				
				$iCount++;
				
				//Remove Empty lines from array to process
				$sNewUserLine = self::CleanupFtpUserBulkString( $sNewUserLine );
				if ( empty($sNewUserLine) ) {
					unset( $inaAllNewUsers[$iCount] );
					continue;
				}
				
				$iCommaCount = substr_count( $sNewUserLine, ',' );

				if ( $iCommaCount != 2 ) {
					$inaMessages[] = "One of the new user entries doesn't have the correct number of values. Check that you have 3 values separated by (2) commas.";
					$fValidState = false;
					break;
				}
				
			}
		}
		else {
			$inaMessages[] = "The new FTP User data is blank.";
			$fValidState = false;
		}
		
		return $fValidState;
	}
	
	protected static function CleanupFtpUserBulkString( $insUserString ) {
		
		$insUserString = preg_replace( '/\s+/', '', $insUserString);
		return $insUserString;
	}
	
	
}//CPM_ActionDelegate_Ftp
