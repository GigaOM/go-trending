<?php

class GoTrending
{
	private $config = NULL;

	/**
	 * constructor, of course
	 */
	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		if ( $this->admin() ){
			$this->admin();
		}
		/**
		 * hook to template_redirect so we can handle the endpoint
		 * 
		 */
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	public function admin()
	{
		if ( ! $this->admin ){
			require_once __DIR__ . '/class-go-trending-admin.php';
			$this->admin = new GoTrendingAdmin();
		}

		return $this->admin;
	}

	/**
	 * Hooked to the init action
	 * 
	 */
	public function init()
	{
		/**
		 * create an endpoint
		 * 
		 */
		add_rewrite_endpoint( 'go-trending', EP_ROOT );

		if ( function_exists( 'go_ui' ) ){
			go_ui();
		}

		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );
		$js_min = ( defined( 'GO_DEV' ) && GO_DEV ) ? 'lib' : 'min';

		wp_register_script(
			'go-trending',
			plugins_url( "/js/{$js_min}/go-trending.js", __FILE__ ),
			array(
				'jquery',
				'handlebars'
			),
			$script_config['version'],
			TRUE
		);

		wp_register_style(
			'go-trending',
			plugins_url( '/css/go-trending.css', __FILE__ ),
			array(),
			$script_config['version']
		);
	}

	/**
	 * Hooked to the template_redirect action
	 * 
	 * @return [type] [description]
	 */
	public function template_redirect()
	{
		global $wp_query;

		if ( empty( $wp_query->query['go-trending'] ) ) {
			return;
		}

		$this->trending_posts_json();
	}

	/**
	 * Hooks into the widgets_init action to initialize plugin widgets
	 * 
	 * @return [type] [description]
	 */
	public function widgets_init()
	{
		require_once __DIR__ . '/class-go-trending-widget.php';
		register_widget( 'GO_Trending_Widget' );
	}

	/**
	 * returns our current configuration, or a value in the configuration.
	 *
	 * @param string $key (optional) key to a configuration value
	 * @return mixed Returns the config array, or a config value if
	 *  $key is not NULL
	 */
	public function config( $key = NULL )
	{
		if ( empty( $this->config ) ) {
			$this->config = apply_filters(
				'go_config',
				array(),
				'go-trending'
			);
		}

		if ( ! empty( $key ) ) {
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL;
		}

		return $this->config;
	}

	/**
	 * Hooked to the trending_posts_ajax action
	 *
	 * 
	 * @return [type] [description]
	 */
	public function trending_posts_json()
	{

		if ( $massaged_data = wp_cache_get( 'go-trending' ) ) {
			wp_send_json_success( $massaged_data );
		}

		$args = array(
			'apikey'	=> $this->config( 'chartbeat_api_key' ),
			'host'		=> $this->config( 'chartbeat_host' ),
		);

		$url = 'http://api.chartbeat.com/live/toppages/v3/';
		$url = add_query_arg( $args, $url );

		$data = NULL;

		/**
		 * fetch content from chartbeat
		 */
		if ( function_exists( 'wpcom_vip_file_get_contents' ) ){
			$data = wpcom_vip_file_get_contents( $url, 1, MINUTE_IN_SECONDS * 5 );
		}else{
			$response = wp_remote_get( $url );

			// if the wp_remote_get failed, return a json error
			if ( is_wp_error( $response ) ){
				wp_send_json_error();
			}

			$data = $response['body'];
		}

		$data = json_decode( $data );

		$massaged_data = array();

		// start the first post at rank 1
		$rank = 1;

		$excluded_urls = get_option( 'go-trending-settings' );
		foreach ( $data->pages as $item )
		{
			if ('gigaom.com/' === $item->path || in_array( $item->path, $excluded_urls )){
				continue;
			}

			// formula for a trend line
			// m = ( a - b ) / ( c - d )
			// where:
			// a = n times ( all x-values multiplied by their corresponding y-values )
			// b = the sum of all x-values times the sum of all y-values
			// c = n times the sum of all squared x-values
			// d = the squared sum of all x-values
			
			/**
			 * [$x description]
			 * 
			 * @var integer
			 */
			$x = 1;

			/**
			 * [$x_y_multiply description]
			 * 
			 * @var integer
			 */
			$x_y_multiply = 0;

			/**
			 * [$sum_x description]
			 * 
			 * @var integer
			 */
			$sum_x = 0;

			/**
			 * [$sum_y description]
			 * 
			 * @var integer
			 */
			$sum_y = 0;

			/**
			 * [$sum_squared_x description]
			 * 
			 * @var integer
			 */
			$sum_squared_x = 0;

			foreach ( $item->stats->visit->hist as $hist )
			{
				$x_y_multiply += $x * $hist;

				$sum_x += $x;
				$sum_y += $hist;

				$sum_squared_x += ( $x * $x );

				$x++;
			}

			$num = count( $item->stats->visit->hist );
			$calc_a = $x_y_multiply * $num;
			$calc_b = $sum_x * $sum_y;
			$calc_c = $num * $sum_squared_x;
			$calc_d = $sum_x * $sum_x;

			$trend = ( $calc_a - $calc_b ) / ( $calc_c - $calc_d );

			if ( $trend <= 0.5 ){
				$trend_direction = 'dash';
			}

			if ( $trend > 0.5 ){
				$trend_direction = 'up';
			}

			/**
			 * Get the path of the post
			 * 
			 * @var str
			 */
			$path = str_replace( 'gigaom.com/', '/', $item->path );

			/**
			 * Build the post
			 * 
			 * @var array
			 */
			$post_data = array(
				'url' => 'https://gigaom.com' . $path,
				'title' => preg_replace( '/ \| Gigaom$/', '', $item->title ),
				'rank' => $rank,
				'trend' => $trend,
				'trend_direction' => $trend_direction,
				'thumbnail' => esc_url( get_template_directory_uri() . '/img/logo-iphone.gigaom.png' ),
				'sections' => array(),
			);

			foreach ( $item->sections as $section )
			{
				list( $key, $value ) = explode( ':', $section );

				if ( ! isset( $post_data['sections'][ $key ] ) ){
					$post_data['sections'][ $key ] = array();
				}

				$post_data['sections'][ $key ][] = html_entity_decode( $value );
			}

			$massaged_data[] = $post_data;
			$rank++;
		}

		/**
		 * Cache the data
		 */
		wp_cache_set( 'go-trending', $massaged_data, '', MINUTE_IN_SECONDS * 5 );

		wp_send_json_success( $massaged_data );
	}
}

function go_trending()
{
	/**
	 * Instance
	 */
	global $go_trending;

	if ( ! $go_trending ){
		$go_trending = new GoTrending;
	}

	return $go_trending;
}