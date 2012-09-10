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

class CPM_ActionDelegate_Base {
	
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
		
		foreach( $this->m_aData as &$data ) {
			if ( is_string($data) ) {
				$data = trim($data);
			}
		}
	}

	protected function preActionBasicValidate( $inaVars = array(), $insAction = 'perform this action' ) {
		
		if ( !empty($inaVars) ) {
			foreach ( $inaVars as $sVar ) {
				if ( is_string($this->m_aData[$sVar]) ) {
					$this->m_aData[$sVar] = trim( $this->m_aData[$sVar] );
				}
			}
		}
	
		$sErrorPrefix = 'No attempt was made to <u>'.$insAction.'</u> because: ';
		
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
	
		return true;
	}//preActionBasicValidate
	
	public static function ValidateDatabaseName( $insDatabaseName, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $insDatabaseName ) ) {
			
			$insDatabaseName = trim($insDatabaseName);
		
			if ( !self::IsAlphaNumeric($insDatabaseName) ) {
				$aMessages[] = "The database name option is not numbers/letters (abc123...).";
				$fValidState = false;
			}
			if ( strlen($insDatabaseName) > 63 ) {
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
	
	public static function ValidateDatabaseUser( $insDatabaseUser, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $insDatabaseUser ) ) {
			
			$insDatabaseUser = trim($insDatabaseUser);
		
			if ( !self::IsAlphaNumeric($insDatabaseUser) ) {
				$aMessages[] = "The database user option is not numbers/letters only (abc123...).";
				$fValidState = false;
			}
			if ( strlen($insDatabaseUser ) > 7 ) {
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
	
	public static function ValidateUserPassword( $insDatabaseUserPassword, &$aMessages ) {
		
		$fValidState = true;
		if ( !empty( $insDatabaseUserPassword ) ) {
			
			$insDatabaseUserPassword = trim($insDatabaseUserPassword);
			
			if ( strlen($insDatabaseUserPassword ) < 6 ) {
				$aMessages[] = "The user password provided ($insDatabaseUserPassword) is too short (6 characters or more).";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The user password provided is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//validateDatabaseUser
	
	public static function ValidateFtpUser( $insFtpUser, &$aMessages ) {
	
		$fValidState = true;
		if ( !empty( $insFtpUser ) ) {
			
			$insFtpUser = trim($insFtpUser);
	
			if ( !self::IsAlphaNumeric($insFtpUser) ) {
				$aMessages[] = "The FTP user provided ($insFtpUser) is not numbers/letters only (abc123...).";
				$fValidState = false;
			}
			if ( strlen($insFtpUser ) > 25 ) {
				$aMessages[] = "The FTP user provided ($insFtpUser) is too long (25 characters or less).";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The FTP user option is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//ValidateFtpUser
	
	public static function ValidateFtpQuota( $insQuota, &$aMessages ) {
	
		$fValidState = true;
		if ( !empty( $insQuota ) ) {
			
			$insQuota = trim($insQuota);
	
			if ( !self::IsNumeric($insQuota) ) {
				$aMessages[] = "The FTP quota provided ($insQuota) is not a number.";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The FTP quota option is blank.";
			$fValidState = false;
		}
		return $fValidState;
	}//ValidateFtpQuota
	
	public static function ValidateUserHomedir( $insHomedir, &$aMessages ) {
	
		$fValidState = true;
		if ( !empty( $insHomedir ) ) {
			
			$insHomedir = trim($insHomedir);
			$insHomedir = trim($insHomedir, '/');
	
			if ( !self::IsDirectory($insHomedir) ) {
				$aMessages[] = "The Home Directory provided isn't a valid directory name.";
				$fValidState = false;
			}
		}
		else {
			$aMessages[] = "The Home Directory option is blank.";
			$fValidState = false;
		}
		
		return $fValidState;
		
	}//ValidateUserHomedir
	
	public static function ValidateConfirmAction( $sConfirmText, &$aMessages ) {
		
		$fValidState = true;
		
		if ( empty( $sConfirmText ) || !preg_match( '/^CONFIRM$/', $sConfirmText ) ) {
			$fValidState = false;
		}
		
		return $fValidState;
	}//validateDatabaseUser
	
	protected static function IsDirectory( $insString = '' ) {
		return preg_match( '/^[A-Za-z0-9\/]+$/', $insString );
	}
	protected static function IsAlphaNumeric( $insString = '' ) {
		return preg_match( '/^[A-Za-z0-9]+$/', $insString );
	}
	protected static function IsNumeric( $insString = '' ) {
		return preg_match( '/^[0-9]+$/', $insString );
	} 

}//CPM_ActionDelegate_Ftp
