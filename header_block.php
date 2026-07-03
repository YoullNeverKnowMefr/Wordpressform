<head>
		<meta charset="utf-8">
		<link href="https://cdn.prod.website-files.com" rel="preconnect" crossorigin="anonymous">
		
		
		
		<meta content="width=device-width, initial-scale=1" name="viewport">
		
		
		<link href="https://fonts.googleapis.com" rel="preconnect">
		<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
		<script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
		<script type="text/javascript">WebFont.load({  google: {    families: ["Open Sans:300,300italic,400,400italic,600,600italic,700,700italic,800,800italic","Lato:100,100italic,300,300italic,400,400italic,700,700italic,900,900italic","Montserrat:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic","Manrope:300,400,500,600,700"]  }});</script>
		<script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
		
		
		
		
		<link rel="stylesheet" href="https://unpkg.com/simplebar@latest/dist/simplebar.css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.default.min.css" integrity="sha512-pTaEn+6gF1IeWv3W1+7X7eM60TFu/agjgoHmYhAfLEU8Phuf6JKiiE8YmsNC0aCgQv4192s4Vai8YZ6VNM6vyQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
	<script id="query_vars">var query_vars='<?php global $wp_query;echo serialize($wp_query->query)?>';</script>
<?php wp_head(); ?>
<?php if(function_exists('get_field')) { echo get_field('head_code', 'option'); } ?>
<?php if(file_exists(dirname( __FILE__ ).'/header_code.php')){ include_once 'header_code.php'; } ?></head>