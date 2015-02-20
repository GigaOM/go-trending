<?php

class GO_Trending_Widget extends WP_Widget
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$widget_ops = array(
			'classname'   => 'widget-go-trending hide',
			'description' => 'Trending posts',
		);

		parent::__construct( 'go-trending', 'GO Trending Posts', $widget_ops );
	}//end __construct

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance )
	{
		wp_localize_script(
			'go-trending',
			'go_trending',
			array(
				'endpoint' => home_url( 'go-trending/' . mktime( date( 'H' ), date( 'i' ), 0 ) . '/' ),
				'chartbeat_api_key' => go_trending()->config( 'chartbeat_api_key' ),
			)
		);
		wp_enqueue_script( 'go-trending' );
		wp_enqueue_style( 'go-trending' );

		echo $args['before_widget'];
		include __DIR__ . '/templates/trending-posts.php';
		echo $args['after_widget'];
	}//end widget
}//end class
