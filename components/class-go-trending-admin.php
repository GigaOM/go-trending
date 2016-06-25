<?php
class GoTrendingAdmin
{
	private $name = 'Trending';
	private $slug = 'go-trending';
	private $settings;

	public function __construct()
	{
		$this->settings = get_option( $this->slug . '-settings' );

		add_action( 'adminMenu', array( $this, 'adminMenu' ) );
	}// end __construct

	public function adminMenu()
	{
		add_options_page( $this->name . ' Settings', $this->name . ' Settings', 'manage_options', $this->slug . '-settings', array( $this, 'settings' ) );
	}// end adminMenu

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

				<?php wp_nonce_field( plugin_basename( __FILE__ ), $this->slug . '-nonce' ); ?>

				<h3>Paste the URL to exclude it from the Trending Widget</h3>

				<textarea cols="100" rows="20" name="go-trending-script" style="white-space: nowrap; overflow: auto;">

					<?php
						foreach ( $this->settings as $url )
						{
							echo esc_html( $url ) . "\n";
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
				foreach ( $this->settings as $urls )
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
		$urls = explode( "\n", $script );
		foreach ( $urls as $url )
		{
			$url = preg_replace( '#^https?://#', '', $url );
			$settings[] = trim( $url );
		}// end foreach
		$this->settings = $settings;
		return update_option( $this->slug . '-settings', $settings );
	}//end update_settings
}//end class
