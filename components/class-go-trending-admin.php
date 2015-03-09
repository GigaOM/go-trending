<?php
class GO_Trending_Admin
{
	private $name = 'Trending';
	private $slug = 'go-trending';
	private $settings;

	public function __construct()
	{
		$this->settings = get_option( $this->slug . '-settings' );

		if ( $this->settings )
		{
			// New Relic claims we are not tracking because the code should occur before any other scripts in the head, let's move it up!
			add_action( 'wp_head', array( $this, 'output_browser_tracking_code' ), 0 );
			// admin_print_scripts is more appropriate, but this needs to happed as soon as possible
			add_action( 'admin_enqueue_scripts', array( $this, 'output_browser_tracking_code' ), 0 );
		}//end if

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}// end __construct

	public function admin_menu()
	{
		add_options_page( $this->name . ' Settings', $this->name . ' Settings', 'manage_options', $this->slug . '-settings', array( $this, 'settings' ) );
	}// end admin_menu

	public function settings()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return;
		}// end if

		if (
			   'POST' == $_SERVER['REQUEST_METHOD']
			&& $this->slug . '-settings' == $_GET['page']
			&& wp_verify_nonce( $_POST[ $this->slug . '-nonce' ], plugin_basename( __FILE__ ) )
		)
		{

			$this->update_settings( $_POST['go-trending-script'] );
		}//end if

		?>
		<div class="wrap">
			<h2><?php echo esc_html( $this->name ); ?> Settings</h2>
			<form method="post">
				<?php
				wp_nonce_field( plugin_basename( __FILE__ ), $this->slug . '-nonce' );
				?>
				<h3>Paste the URL to exclude it from the Trending Widget</h3>
				<textarea cols="100" rows="15" name="go-trending-script" style="white-space: nowrap; overflow: auto;">
				<?php
				foreach ( $this->settings as $url => $value )
				{
					echo esc_html( $url );
				}// end foreach
				?>
				</textarea>
				<p class="submit">
					<input type="submit" value="Submit" class="button-primary"/>
				</p>
			</form>
			<?php

			if ( $this->settings )
			{
				?>
				<h3>Excluded URLs</h3>
				<?php
				foreach ( $this->settings as $urls => $value )
				{
					?>
					<div>
						<span id="<?php echo esc_attr( $urls ); ?>"><?php echo esc_html( $urls ); ?></span>
					</div>
					<?php
				}// end foreach
			}//end if
			?>
		</div>
		<?php
	}// end settings


	private function update_settings( $script )
	{
		//$script = stripslashes( $script );
		$urls = explode( "\n", $script);
		foreach ( $urls as $url )
		{
			$settings[ $url ] = $value;
		}// end foreach
		$this->settings = $settings;
		return update_option( $this->slug . '-settings', $settings );
	}//end update_settings
}//end class
