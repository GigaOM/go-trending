<header>Trending on Gigaom</header>
<script id="trending-posts-template" type="text/x-handlebars-template">
	{{#each posts}}
	<div class="trending-post" data-rank="{{rank}}">
		<div class="rank">
			<a href="{{url}}">{{rank}}</a>
		</div>
		<div class="trend">
			<a href="{{url}}"><i class="goicon icon-chevron-{{trend_direction}}"></i></a>
		</div>
		<div class="thumb">
			<a href="{{url}}"><img src="{{thumbnail}}" width="64" height="64"/></a>
		</div>
		<header>
			<a href="{{url}}">{{title}}</a>
		</header>
	</div>
	{{/each}}
</script>
