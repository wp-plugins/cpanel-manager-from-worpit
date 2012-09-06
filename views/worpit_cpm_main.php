<?php
include_once( dirname(__FILE__).DS.'worpit_options_helper.php' );
include_once( dirname(__FILE__).DS.'widgets'.DS.'worpit_widgets.php' );
?>
<div class="wrap">
	<div class="bootstrap-wpadmin">
		<div class="page-header">
			<a href="http://worpit.com/"><div class="icon32" id="worpit-icon">&nbsp;</div></a>
			<h2>cPanel Manager (from Worpit) :: cPanel Connect Options</h2>
		</div>
		<div class="row">
			<div class="span9">
				<form method="post" action="<?php echo $worpit_form_action; ?>" class="form-horizontal">
					<?php
						wp_nonce_field( $worpit_nonce_field );
						printAllPluginOptionsForm( $worpit_aAllOptions, $worpit_var_prefix, 1 );
					?>
					<div class="form-actions">
						<input type="hidden" name="cpm_form_submit" value="1" />
						<input type="hidden" name="<?php echo $worpit_var_prefix.'all_options_input'; ?>" value="<?php echo $worpit_all_options_input; ?>" />
						<button type="submit" class="btn btn-primary" name="submit">Save All Settings</button>
					</div>
				</form>
			</div><!-- / span9 -->
			<div class="span3" id="side_widgets">
	  			<?php echo getWidgetIframeHtml( 'cpm-side-widgets' ); ?>
			</div>
		</div>
	</div><!-- / bootstrap-wpadmin -->
	<?php include_once( dirname(__FILE__).'/worpit_options_js.php' ); ?>
</div><!-- / wrap -->
