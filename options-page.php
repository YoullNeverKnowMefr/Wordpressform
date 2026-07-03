<?php
/*
Template name: Опции сайта
*/
?>
    <!DOCTYPE html>
<html data-wf-page="69d25fcfeacc8b62fad4baca" data-wf-site="5e7142bca461d1651b7ea6fb" lang="ru-RU">
	<?php get_template_part("header_block", ""); ?>
	<body>
<?php if(function_exists('get_field')) { echo get_field('body_code', 'option'); } ?>

		<?php get_template_part('template-parts/cookies', ''); ?>
		
		
		
		
		
		
		
		
	
<!-- FOOTER CODE --><?php get_template_part("footer_block", ""); ?>
<script type="text/javascript" src="<?php bloginfo('template_url'); ?>/js/options-page.js?ver=1778595962"></script></body>
</html>
