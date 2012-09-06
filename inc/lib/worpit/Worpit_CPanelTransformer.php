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

include_once( dirname(__FILE__).'/cpanel_api.php' );

class Worpit_CPanelTransformer {
	
	public function __construct( ) { }
	
	public static function GetDataFromApi( &$inoCpanel_Api, $insDataExtract, $insModule, $insFunction, $inaArgs = null ) {
		
		$inoCpanel_Api->doApiFunction( $insModule, $insFunction, $inaArgs );
		$aResponseData = $inoCpanel_Api->getLastResponse();
		
		return self::GetDataFromResponse( $aResponseData, $insDataExtract );
		
	}
	
	public static function GetDataFromResponse( $inaApiResponse, $insDataExtract ) {
		
		$aKeys = explode( '_', $insDataExtract);
		
		$aExtractData = $inaApiResponse;
		foreach( $aKeys as $key ) {
			
			if ( isset( $aExtractData[ $key ] ) ) {
				$aExtractData = &$aExtractData[ $key ];
				continue;
			} else {
				$aExtractData = array();
				break;
			}
		}
		return $aExtractData;
	}

	public static function GetLastSuccess( $inaApiResponse ) {
		
		if ( isset( $inaApiResponse['error'] ) ) {
			return false;
		}
		
		if ( isset( $inaApiResponse['event']['result'] ) && $inaApiResponse['event']['result'] === "1" ) {
			return true;
		}
		else {
			return false;
		}
	
	}
	
	public static function GetLastError( $inaApiResponse ) {
		
		if ( isset( $inaApiResponse['error'] ) ) {
			return $inaApiResponse['error'];
		} else {
			return '';
		}
	}
	
	/**
	 * Returns a 1D array of all MySQL databases on a cPanel account.
	 * 
	 * @param unknown_type $inaApiResponse
	 */
	public static function GetList_MySqlDbNames( $inaApiResponse ) {
		
		$aDbNamesList = array();
		
		if ( !self::GetLastSuccess( $inaApiResponse ) ) {//Last API call failed.
			return $aDbNamesList;
		}
	
		$aDbData = self::GetDataFromResponse( $inaApiResponse, 'data' );
		
		if ( !empty($aDbData) ) {
			if ( array_key_exists('db', $aDbData) ) { //there's only 1 Database in this data set
				$aDbNamesList[] = $aDbData['db'];
			}
			else {
				foreach( $aDbData as $aDb ) {
					$aDbNamesList[] = $aDb['db'];
				}
			}
		}

		return $aDbNamesList;
	}
	
	public static function GetList_AllMySqlUsers( $inaApiResponse ) {
		
		$aUsersList = array();
		
		if ( !self::GetLastSuccess( $inaApiResponse ) ) {//Last API call failed.
			return $aUsersList;
		}
	
		$aDbUserData = self::GetDataFromResponse( $inaApiResponse, 'data' );
		
		if ( !empty($aDbUserData) ) {
			
			if ( array_key_exists('dblist', $aDbUserData) ) { //there's only 1 DB User in this data set
				$aUsersList[] = $aDbUserData['user'];
			}
			else {
				foreach( $aDbUserData as $aDbUser ) {
					$aUsersList[] = $aDbUser['user'];
				}
			}
			
		}
		
		return $aUsersList;
		
	}//GetList_AllMySqlUsers
	
	/**
	 * Returns a 1D array of all MySQL users on a particular database.
	 * 
	 * @param $inaApiResponse
	 */
	public static function GetList_MySqlUsersOnDb( $inaApiResponse, $sDbName ) {
		
		$aUsersList = array();
		
		if ( !self::GetLastSuccess( $inaApiResponse ) ) {//Last API call failed.
			return $aUsersList;
		}
		
		$aDb = self::GetData_MySqlDb( $inaApiResponse, $sDbName );

		if ( !empty($aDb) ) { //The DB even exists.

			$sDbUserCount = $aDb[ 'usercount' ];
			
			if ( $aDb[ 'usercount' ] > 0 ) {
				
				$aDbUserList = $aDb[ 'userlist' ];
				
				if ( $sDbUserCount == 1 ) {
					$aUsersList[] = $aDbUserList['user'];
				}
				else {
					$iCount = 0;
					while( $iCount < $sDbUserCount ) {
						$aUsersList[] = $aDbUserList[$iCount]['user'];
						$iCount++;
					}
				}
			}
		}
		
		if ( !empty($aDbData) ) {
			if ( array_key_exists('db', $aDbData) ) { //there's only 1 Database in this data set
				$aUsersList[] = $aDbData['db'];
			}
			else {
				foreach( $aDbData as $aDb ) {
					$aUsersList[] = $aDb['db'];
				}
			}
		}
	
		return $aUsersList;
	}
	
	public static function GetData_MySqlDb( $inaApiResponse, $sDbName ) {
		
		$aDb = array();
		
		if ( !self::GetLastSuccess( $inaApiResponse ) ) {//Last API call failed.
			return $aDb;
		}
		
		$aAllDbData = self::GetData_MySqlDbs( $inaApiResponse, 'data' );

		if ( !empty($aAllDbData) ) {
			
			foreach( $aAllDbData as $aDbData ) {
				
				if ( $aDbData['db'] == $sDbName ) {
					$aDb = $aDbData;
				}
			}
		}
		
		return $aDb;
	}//GetData_MySqlDb
	
	
	/**
	 * Returns an array of arrays of all DBs data
	 * 
	 * @param unknown_type $inaApiResponse
	 */
	public static function GetData_MySqlDbs( $inaApiResponse ) {
		
		$aDatabases = array();
		
		if ( !self::GetLastSuccess( $inaApiResponse ) ) {//Last API call failed.
			return $aDatabases;
		}
		
		$aDbData = self::GetDataFromResponse( $inaApiResponse, 'data' );
		
		if ( !empty($aDbData) ) {
			
			if ( array_key_exists('db', $aDbData) ) { //there's only 1 Database in this data set
				$aDatabases[] = $aDbData;
			}
			else {
				$aDatabases = $aDbData;
			}
			
		}
		
		return $aDatabases;
	}
	
	
	
	
	
	/**
	 * Given an array of associative arrays, returns an array of the values of a common key
	 * 
	 * @param $inaArray
	 * @param $insKey
	 */
	public static function CreateArrayFromOneKey( $inaArray, $insKey ) {
		
		$aNewArray = array();
		foreach( $inaArray as $aElement ) {
			if ( isset($aElement[$insKey]) ) {
				$aNewArray[] = $aElement[$insKey];
			}
		}
		return $aNewArray;
	}
	
	/**
	 * Given an array of associative arrays, returns an associative array of the same array based on a common key
	 * 
	 * @param unknown_type $inaArray
	 * @param unknown_type $insKey
	 */
	public static function CreateAssocArrayOnKey( $inaArray, $insKey ) {
		
	}
	
	public static function IsAssocArray( $inaArray ) {
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
}