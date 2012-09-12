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

class CPM_ActionDelegate_Domain extends CPM_ActionDelegate_Base {
		
	/**
	 * Assuming inputs are valid, will create a new database and new database user and assign the user will full
	 * permissions to it.
	 * 
	 * $this->m_aData must contain database_name, database_user, database_user_password
	 */
	public function create_subdomain() {
		
		$aVars = array( 'subdomain_new_domain', 'subdomain_parent_domain', 'subdomain_document_root' );
		
		if ( !$this->preActionBasicValidate( $aVars, 'create a new sub domain' ) ) {
			return false;
		}
		
		$this->m_aData['subdomain_parent_domain'] = trim( $this->m_aData['subdomain_parent_domain'], '/' );
		
		$fValidState = true;
		$fValidState = self::ValidateSubDomain( $this->m_aData['subdomain_new_domain'], $this->m_aMessages ) && $fValidState;
		$fValidState = self::ValidateFullDomain( $this->m_aData['subdomain_parent_domain'], $this->m_aMessages ) && $fValidState;
		$this->m_fGoodToGo = self::ValidateDirectory( $this->m_aData['subdomain_document_root'], $this->m_aMessages ) && $fValidState;
		
		if ( !$this->m_fGoodToGo ) {
			$this->m_aMessages[] = $sErrorPrefix."Your inputs had problems. Please Check.";
			return false;
		}
		
		$fSuccess = false;
		$fSuccess = $this->createNewSubDomain(
							$this->m_aData['subdomain_new_domain'],
							$this->m_aData['subdomain_parent_domain'],
							$this->m_aData['subdomain_document_root']
		);
		
		return $fSuccess;
		
	}//create_ftpuser

	public function createNewSubDomain( $insDomain, $insParentDomain, $insRootDir ) {
		
		$aArgs = array(
					'domain'		=> $insDomain,
					'rootdomain'	=> $insParentDomain,
					'dir'			=> $insRootDir,
					'disallowdot'	=> 1
				);
		
		$this->m_oCpanel_Api->doApiFunction( "SubDomain", "addsubdomain", $aArgs );
		$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
		
		if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
			$fSuccess = true;
			$this->m_aMessages[] = "Creating new Sub Domain on cPanel account succeeded: ".$insDomain.'|'.$insParentDomain.'|'.$insRootDir; 
		}
		else {
			$fSuccess = false;
			$this->m_aMessages[] = "Creating new Sub Domain ( $insDomain | $insParentDomain | $insRootDir ) on cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse ); 
		}
		
		return $fSuccess;
		
	}//createNewSubDomain
	
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
	public function delete_subdomains() {
		
		$aVars = array();
		
		if ( !$this->preActionBasicValidate( $aVars, 'to delete Sub Domains' ) ) {
			return false;
		}
		
		if ( !isset( $this->m_aData['subdomains_to_delete_names'] ) || !is_array( $this->m_aData['subdomains_to_delete_names'] ) ) {
			$this->m_aMessages[] = "No Sub Domains were selected.";
			return false;
		}
		
		$aSubDomains = $this->m_aData['subdomains_to_delete_names'];
		
		$fSuccess = true;
		foreach( $aSubDomains as $sSubDomain ) {
		
			$aArgs = array ( 'domain' => $sSubDomain );

			$this->m_oCpanel_Api->doApiFunction( "SubDomain", "delsubdomain", $aArgs );
			$this->m_oLastApiResponse = $this->m_oCpanel_Api->getLastResponse();
			
			if ( Worpit_CPanelTransformer::GetLastSuccess( $this->m_oLastApiResponse ) ) {
				$fSuccess = true;
				$this->m_aMessages[] = "Deleting Sub Domain ($sSubDomain) from cPanel account succeeded."; 
			}
			else {
				$fSuccess = false;
				$this->m_aMessages[] = "Deleting Sub Domain ($sSubDomain) from cPanel account FAILED: ". Worpit_CPanelTransformer::GetLastError( $this->m_oLastApiResponse );
				$this->m_aMessages[] = "Stopping further processing due to previous failure.";
			}
			
			if ( !$fSuccess ) {
				break;
			}
		}
		
		return $fSuccess;
	}
	
	public static function ValidateSubDomain( $insTestString, &$aMessages ) {
	
		$fValidState = true;
		if ( !empty( $insTestString ) ) {
	
			if ( !self::IsValidSubDomain($insTestString) ) {
				$aMessages[] = "The Sub Domain provided isn't a valid sub domain name.";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The Sub Domain option is blank.";
			$fValidState = false;
		}
	
		return $fValidState;
	}
	
	public static function ValidateFullDomain( $insTestString, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $insTestString ) ) {
	
			if ( !self::IsValidDomainName($insTestString) ) {
				$aMessages[] = "The Domain provided isn't a valid domain name.";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The Domain option is blank.";
			$fValidState = false;
		}
		
		return $fValidState;
	}
	
}//CPM_ActionDelegate_Domain
