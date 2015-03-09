<?php

class GO_Trending
{
	private $config = NULL;

	/**
	 * constructor, of course
	 */
	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		if ( $this->admin())
		{
			$this->admin();
		}

		// hook to template_redirect so we can handle the endpoint
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}//end __construct

	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-trending-admin.php';
			$this->admin = new GO_Trending_Admin();
		}// end if
		return $this->admin;
	} // END admin
	/**
	 * Hooked to the init action
	 */
	public function init()
	{
		// create an endpoint
		add_rewrite_endpoint( 'go-trending', EP_ROOT );

		if ( function_exists( 'go_ui' ) )
		{
			go_ui();
		}//end if

		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );
		$js_min = ( defined( 'GO_DEV' ) && GO_DEV ) ? 'lib' : 'min';

		wp_register_script(
			'go-trending',
			plugins_url( "/js/{$js_min}/go-trending.js", __FILE__ ),
			array(
				'jquery',
				'handlebars',
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
	}//end init

	/**
	 * Hooked to the template_redirect action
	 */
	public function template_redirect()
	{
		global $wp_query;

		if ( empty( $wp_query->query['go-trending'] ) )
		{
			return;
		}//end if

		$this->trending_posts_json();
	}//end template_redirect

	/**
	 * Hooks into the widgets_init action to initialize plugin widgets
	 */
	public function widgets_init()
	{
		require_once __DIR__ . '/class-go-trending-widget.php';
		register_widget( 'GO_Trending_Widget' );
	}//end widgets_init

	/**
	 * returns our current configuration, or a value in the configuration.
	 *
	 * @param string $key (optional) key to a configuration value
	 * @return mixed Returns the config array, or a config value if
	 *  $key is not NULL
	 */
	public function config( $key = NULL )
	{
		if ( empty( $this->config ) )
		{
			$this->config = apply_filters(
				'go_config',
				array(),
				'go-trending'
			);
		}//END if

		if ( ! empty( $key ) )
		{
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL ;
		}

		return $this->config;
	}//END config

	/**
	 * Hooked to the trending_posts_ajax action
	 */
	public function trending_posts_json()
	{
		if ( $massaged_data = wp_cache_get( 'go-trending' ) )
		{
			wp_send_json_success( $massaged_data );
		}//end if

		$args = array(
			'apikey' => $this->config( 'chartbeat_api_key' ),
			'host' => $this->config( 'chartbeat_host' ),
		);

		$url = 'http://api.chartbeat.com/live/toppages/v3/';
		$url = add_query_arg( $args, $url );

		$data = NULL;

		// fetch content from chartbeat
		if ( function_exists( 'wpcom_vip_file_get_contents' ) )
		{
			$data = wpcom_vip_file_get_contents( $url, 1, MINUTE_IN_SECONDS * 5 );
		}//end if
		else
		{
			$response = wp_remote_get( $url );

			// if the wp_remote_get failed, return a json error
			if ( is_wp_error( $response ) )
			{
				wp_send_json_error();
			}//end if

			$data = $response['body'];
		}//end else

		$data = json_decode( $data );

		$massaged_data = array();

		// start the first post at rank 1
		$rank = 1;

		foreach ( $data->pages as $item )
		{
			if ( 'gigaom.com/' === $item->path )
			{
				continue;
			}//end if

			// formula for a trend line
			// m = ( a - b ) / ( c - d )
			// where:
			// a = n times ( all x-values multiplied by their corresponding y-values )
			// b = the sum of all x-values times the sum of all y-values
			// c = n times the sum of all squared x-values
			// d = the squared sum of all x-values

			$x = 1;
			$x_y_multiply = 0;
			$sum_x = 0;
			$sum_y = 0;
			$sum_squared_x = 0;
			foreach ( $item->stats->visit->hist as $hist )
			{
				$x_y_multiply += $x * $hist;

				$sum_x += $x;
				$sum_y += $hist;

				$sum_squared_x += ( $x * $x );

				$x++;
			}//end foreach

			$num = count( $item->stats->visit->hist );
			$calc_a = $x_y_multiply * $num;
			$calc_b = $sum_x * $sum_y;
			$calc_c = $num * $sum_squared_x;
			$calc_d = $sum_x * $sum_x;

			$trend = ( $calc_a - $calc_b ) / ( $calc_c - $calc_d );
			if ( $trend <= 0.5 )
			{
				$trend_direction = 'dash';
			}//end if
			elseif ( $trend > 0.5 )
			{
				$trend_direction = 'up';
			}//end elseif

			// get the path of the post
			$path = str_replace( 'gigaom.com/', '/', $item->path );

			// build the post
			$post_data = array(
				'url' => 'https://gigaom.com/' . $path,
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

				if ( ! isset( $post_data['sections'][ $key ] ) )
				{
					$post_data['sections'][ $key ] = array();
				}//end if

				$post_data['sections'][ $key ][] = html_entity_decode( $value );
			}//end foreach

			$massaged_data[] = $post_data;
			$rank++;
		}//end foreach

		// cache the data
		wp_cache_set( 'go-trending', $massaged_data, '', MINUTE_IN_SECONDS * 5 );

		wp_send_json_success( $massaged_data );
	}//end trending_posts_json
}//end class

function go_trending()
{
	global $go_trending;

	if ( ! $go_trending )
	{
		$go_trending = new GO_Trending;
	}//end if

	return $go_trending;
}//end go_trending
