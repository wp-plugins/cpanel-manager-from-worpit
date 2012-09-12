<?php

function getContent_SubdomainsTab( $inaConnectionData, &$inoCpanelApi ) {
	
	$aHtml = array();
	$sHtml = '';
	list($sServerAddress, $sServerPort, $sUsername, $sPassword, $sNonce, $sFormAction ) = $inaConnectionData;
	
	//Perform Main cPanel FTP API query
	$inoCpanelApi->doApiFunction( 'SubDomain', 'listsubdomains' );
	$oLastResponse = $inoCpanelApi->getLastResponse();

	if ( Worpit_CPanelTransformer::GetLastSuccess($oLastResponse) ) { //Last API call was a success.
		
		$aAllSubDomainData = Worpit_CPanelTransformer::GetDataArray( $oLastResponse, 'subdomain' );

		$aAllSubDomainsList = Worpit_CPanelTransformer::GetListFromData( $oLastResponse, 'domain' );

		if ( !empty($aAllSubDomainData) ) {

			$sHtml = '<div class="well">
			<h4>All Sub Domains</h4>
			<ul>';
			foreach( $aAllSubDomainData as $aSubDomain ) {
				$sSubDomain = $aSubDomain[ 'subdomain' ];
				$sRootDomain = $aSubDomain[ 'rootdomain' ];
				$sHomeDir = $aSubDomain[ 'dir' ];
				$sStatus = $aSubDomain[ 'status' ];
				$sHtml .= "<h5>$sSubDomain.<small>$sRootDomain</small></h5>";
				$sHtml .= "
					<ul>
						<li><span class=\"user_homedir\">$sHomeDir</span></li>
						<li>Status: $sStatus</li>
					</ul>
				";
				
			}
			$sHtml .= '</ul></div>';


		} else {
			$sHtml .= "There doesn't appear to be any.";
		}
		
	}
	$aHtml[ 'SubDomainsInfo' ] = $sHtml;
	
	/*
	 * Create HTML for Tab: FtpNewUser
	 */
	ob_start();

	$inoCpanelApi->doApiFunction( 'DomainLookup', 'getbasedomains' );
	$oLastResponse = $inoCpanelApi->getLastResponse();
	$aBaseDomains = Worpit_CPanelTransformer::GetListFromData( $oLastResponse, 'domain' );
	
	$inoCpanelApi->doApiFunction( 'Ftp', 'listftp' );
	$oLastResponse = $inoCpanelApi->getLastResponse();
	$aMainFtpUser = Worpit_CPanelTransformer::GetData_MainFtpUser( $oLastResponse );
	$sHomeDir = $aMainFtpUser['homedir'];

	?>
		<legend>Create New SubDomain</legend>
		<form class="form-horizontal" action="<?php echo $sFormAction; ?>" method="post" >
			<?php wp_nonce_field( $sNonce ); ?>
			<div class="control-group">
				<label class="control-label" for="subdomain_new_domain">New Sub Domain</label>
				<div class="controls">
					<input type="text" name="subdomain_new_domain" id="subdomain_new_domain" placeholder="Sub Domain Name" class="span3"
					value="<?php echo isset($_POST['subdomain_new_domain'])? $_POST['subdomain_new_domain'] : '' ?>" />
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="subdomain_parent_domain">Parent Domain</label>
				<div class="controls">
					<select name="subdomain_parent_domain" id="subdomain_parent_domain">
						<?php foreach( $aBaseDomains as $sBaseDomain ) { echo "<option name=\"$sBaseDomain\" value=\"$sBaseDomain\">$sBaseDomain</option>";	} ?>
					</select>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="subdomain_document_root">Document Root</label>
				<div class="controls">
					<div class="input-prepend">
						<span class="add-on"><?php echo $sHomeDir.'/'; ?></span><input type="text" name="subdomain_document_root" id="subdomain_document_root"
						placeholder="Document Root Directory" class="span3"
						value="<?php echo isset($_POST['subdomain_document_root'])? $_POST['subdomain_document_root'] : '' ?>" />
					</div>
					
				</div>
			</div>
			<?php echo getConfirmBoxHtml(); ?>
			<div class="form-actions">
				<input type="hidden" name="cpm_submit_action" value="domain_create_subdomain" />
				<input type="hidden" name="cpm_form_submit" value="1" />
			 	<button type="submit" class="btn btn-primary" onClick="return confirmSubmit('Are you sure you want to create the new Sub Domain?')">Create New Sub Domain</button>
			</div>
		</form>
		
	<?php
	$aHtml[ 'SubDomainsNew' ] = ob_get_contents();
	ob_end_clean();
	
	/*
	 * Create HTML for Tab: SubDomainsDelete
	 */
	ob_start();
	?>
		<legend>Delete Sub Domains</legend>
		<form class="form-horizontal" action="<?php echo $sFormAction; ?>" method="post" >
			<?php wp_nonce_field( $sNonce ); ?>
			
			<?php

			if ( !empty($aAllSubDomainsList) ) {
			?>
				<div class="control-group">
					<label class="control-label" for="subdomains_to_delete_names">Choose Sub Domains</label>
					<div class="controls">
						<select class="span5" multiple="multiple" size="<?php echo count($aAllSubDomainsList) ?>" name="subdomains_to_delete_names[]" id="subdomains_to_delete_names">
			<?php
				foreach( $aAllSubDomainsList as $sSubDomainName ) {
					echo "<option name=\"$sSubDomainName\" value=\"$sSubDomainName\">$sSubDomainName</option>";
				}
			?>
						</select>
					</div>
				</div>
				<?php echo getConfirmBoxHtml(); ?>
				<div class="form-actions">
					<input type="hidden" name="cpm_submit_action" value="domain_delete_subdomains" />
					<input type="hidden" name="cpm_form_submit" value="1" />
				 	<button type="submit" class="btn btn-primary btn-danger" onClick="return confirmSubmit('Are you sure you want to delete the selected Sub Domain(s)?')">Delete Sub Domain(s)</button>
				</div>
			<?php
			}
			else {
				echo "<p>There doesn't appear to be any FTP users on this cPanel account available for deletion.</p>";
			}
			?>
		</form>
		
	<?php
	$aHtml[ 'SubDomainsDelete' ] = ob_get_contents();
	ob_end_clean();
	
	?>
			<div id="TabsFunctionFtp" class="tabbable tabs-function">
				<ul class="nav nav-pills">
					<li class="active"><a href="#SubDomainsInfo" data-toggle="tab">Info</a></li>
					<li><a href="#SubDomainsNew" data-toggle="tab">New Sub Domain</a></li>
					<li><a href="#SubDomainsDelete" data-toggle="tab">Delete Sub Domains</a></li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane active" id="SubDomainsInfo"><?php echo $aHtml[ 'SubDomainsInfo' ]; ?></div>
					<div class="tab-pane" id="SubDomainsNew"><?php echo $aHtml[ 'SubDomainsNew' ]; ?></div>
					<div class="tab-pane" id="SubDomainsDelete"><?php echo $aHtml[ 'SubDomainsDelete' ]; ?></div>
				</div>
			</div>
	<?php
	
}//getContent_FtpTab